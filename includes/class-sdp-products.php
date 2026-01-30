<?php
/**
 * 商品管理クラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDP_Products {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_sdp_save_product', array($this, 'ajax_save_product'));
        add_action('wp_ajax_sdp_delete_product', array($this, 'ajax_delete_product'));
        add_action('wp_ajax_sdp_upload_file', array($this, 'ajax_upload_file'));
        add_action('wp_ajax_sdp_delete_file', array($this, 'ajax_delete_file'));
    }
    
    /**
     * 商品を取得
     */
    public function get_product($product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sdp_products';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $product_id
        ));
    }
    
    /**
     * すべての商品を取得
     */
    public function get_all_products($status = 'active') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sdp_products';
        
        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = %s ORDER BY created_at DESC",
                $status
            ));
        } else {
            return $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY created_at DESC"
            );
        }
    }
    
    /**
     * 商品を保存
     */
    public function save_product($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sdp_products';
        
        $product_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => wp_kses_post($data['description']),
            'image_url' => isset($data['image_url']) ? sanitize_text_field($data['image_url']) : '',
            'price' => floatval($data['price']),
            'file_path' => sanitize_text_field($data['file_path']),
            'status' => sanitize_text_field($data['status']),
        );
        
        // product_typeカラムが存在する場合のみ追加
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'product_type'");
        if (!empty($column_exists)) {
            $product_data['product_type'] = isset($data['product_type']) ? sanitize_text_field($data['product_type']) : 'digital';
        }
        
        if (isset($data['id']) && $data['id']) {
            // 更新
            $wpdb->update(
                $table_name,
                $product_data,
                array('id' => intval($data['id']))
            );
            return intval($data['id']);
        } else {
            // 新規作成
            $wpdb->insert($table_name, $product_data);
            return $wpdb->insert_id;
        }
    }
    
    /**
     * 商品を削除
     */
    public function delete_product($product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sdp_products';
        
        return $wpdb->delete($table_name, array('id' => intval($product_id)));
    }
    
    /**
     * Stripe商品とPriceを作成
     */
    public function create_stripe_product($product_id) {
        $product = $this->get_product($product_id);
        
        if (!$product) {
            return false;
        }
        
        // 既存のStripe Product/Price IDをチェック
        $has_existing_stripe_data = !empty($product->stripe_product_id) && !empty($product->stripe_price_id);
        
        if ($has_existing_stripe_data) {
            error_log('SDP: Product already has Stripe data. Creating new Price for updated product.');
        }
        
        $settings = get_option('sdp_settings');
        $stripe_mode = isset($settings['stripe_mode']) ? $settings['stripe_mode'] : 'test';
        $secret_key = $stripe_mode === 'live' 
            ? $settings['live_secret_key'] 
            : $settings['test_secret_key'];
        
        if (empty($secret_key)) {
            return false;
        }
        
        \Stripe\Stripe::setApiKey($secret_key);
        
        try {
            // 通貨を取得
            $currency = isset($settings['currency']) ? strtolower($settings['currency']) : 'jpy';
            
            // ゼロデシマル通貨（最小単位が通貨単位そのもの）のリスト
            $zero_decimal_currencies = array('bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf');
            
            // 金額を最小通貨単位に変換
            if (in_array($currency, $zero_decimal_currencies)) {
                // JPYなどのゼロデシマル通貨：そのまま使用（20円 = 20）
                $unit_amount = intval($product->price);
            } else {
                // USDなどの通常通貨：100倍（$20.00 = 2000セント）
                $unit_amount = intval($product->price * 100);
            }
            
            error_log('SDP Create Stripe Product: Currency=' . $currency . ', Price=' . $product->price . ', Unit Amount=' . $unit_amount);
            
            // 既存のStripe Productがある場合は再利用、ない場合は新規作成
            if ($has_existing_stripe_data && !empty($product->stripe_product_id)) {
                $stripe_product_id = $product->stripe_product_id;
                error_log('SDP: Using existing Stripe Product ID: ' . $stripe_product_id);
                
                // 既存のProductを更新
                try {
                    \Stripe\Product::update($stripe_product_id, [
                        'name' => $product->name,
                        'description' => $product->description,
                    ]);
                } catch (\Exception $e) {
                    error_log('SDP: Failed to update existing product, creating new one: ' . $e->getMessage());
                    $has_existing_stripe_data = false;
                }
            }
            
            if (!$has_existing_stripe_data) {
                // 新規Stripe商品を作成
                $stripe_product = \Stripe\Product::create([
                    'name' => $product->name,
                    'description' => $product->description,
                ]);
                $stripe_product_id = $stripe_product->id;
                error_log('SDP: Created new Stripe Product ID: ' . $stripe_product_id);
            }
            
            // 常に新しいPriceを作成（価格が変更されている可能性があるため）
            $stripe_price = \Stripe\Price::create([
                'product' => $stripe_product_id,
                'unit_amount' => $unit_amount,
                'currency' => $currency,
            ]);
            
            error_log('SDP: Created new Stripe Price ID: ' . $stripe_price->id . ' with amount: ' . $unit_amount);
            
            // DBに保存
            global $wpdb;
            $table_name = $wpdb->prefix . 'sdp_products';
            $wpdb->update(
                $table_name,
                array(
                    'stripe_product_id' => $stripe_product_id,
                    'stripe_price_id' => $stripe_price->id,
                ),
                array('id' => $product_id)
            );
            
            return array(
                'product_id' => $stripe_product_id,
                'price_id' => $stripe_price->id,
            );
            
        } catch (\Exception $e) {
            error_log('Stripe Product Creation Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * AJAX: 商品を保存
     */
    public function ajax_save_product() {
        check_ajax_referer('sdp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $product_id = $this->save_product($_POST);
        
        if ($product_id) {
            // Stripe商品を作成
            $this->create_stripe_product($product_id);
            wp_send_json_success(array('product_id' => $product_id));
        } else {
            wp_send_json_error('保存に失敗しました');
        }
    }
    
    /**
     * AJAX: 商品を削除
     */
    public function ajax_delete_product() {
        check_ajax_referer('sdp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $product_id = intval($_POST['product_id']);
        
        if ($this->delete_product($product_id)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('削除に失敗しました');
        }
    }
    
    /**
     * AJAX: ファイルアップロード
     */
    public function ajax_upload_file() {
        // Nonceチェック
        if (!check_ajax_referer('sdp_admin_nonce', 'nonce', false)) {
            wp_send_json_error('セキュリティチェックに失敗しました');
            return;
        }
        
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
            return;
        }
        
        // ファイルチェック
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error_message = '不明なエラー';
            if (isset($_FILES['file']['error'])) {
                switch ($_FILES['file']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = 'ファイルサイズが大きすぎます';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = 'ファイルが部分的にしかアップロードされませんでした';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = 'ファイルが選択されていません';
                        break;
                    default:
                        $error_message = 'アップロードエラー (コード: ' . $_FILES['file']['error'] . ')';
                }
            }
            wp_send_json_error($error_message);
            return;
        }
        
        // アップロードディレクトリの準備
        $upload_dir = wp_upload_dir();
        $sdp_upload_dir = $upload_dir['basedir'] . '/sdp-products';
        
        if (!file_exists($sdp_upload_dir)) {
            if (!wp_mkdir_p($sdp_upload_dir)) {
                wp_send_json_error('アップロードディレクトリの作成に失敗しました');
                return;
            }
            // セキュリティファイルの作成
            file_put_contents($sdp_upload_dir . '/.htaccess', 'deny from all');
            file_put_contents($sdp_upload_dir . '/index.php', '<?php // Silence is golden');
        }
        
        // ファイル名のサニタイズ
        $file = $_FILES['file'];
        $filename = sanitize_file_name($file['name']);
        $filename = wp_unique_filename($sdp_upload_dir, $filename);
        $target_path = $sdp_upload_dir . '/' . $filename;
        
        // ファイル移動
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $relative_path = 'sdp-products/' . $filename;
            wp_send_json_success(array(
                'file_path' => $relative_path,
                'filename' => $filename
            ));
        } else {
            wp_send_json_error('ファイルの保存に失敗しました。ディレクトリの書き込み権限を確認してください。');
        }
    }
    
    /**
     * AJAX: ファイル削除
     */
    public function ajax_delete_file() {
        // Nonceチェック
        if (!check_ajax_referer('sdp_admin_nonce', 'nonce', false)) {
            error_log('SDP Delete File: Nonce verification failed');
            wp_send_json_error('セキュリティチェックに失敗しました');
            return;
        }
        
        // 権限チェック
        if (!current_user_can('manage_options')) {
            error_log('SDP Delete File: Permission denied');
            wp_send_json_error('権限がありません');
            return;
        }
        
        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (empty($file_path)) {
            error_log('SDP Delete File: File path is empty');
            wp_send_json_error('ファイルパスが指定されていません');
            return;
        }
        
        error_log('SDP Delete File: Attempting to delete: ' . $file_path . ' (Product ID: ' . $product_id . ')');
        
        // フルパスを構築
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/' . $file_path;
        
        error_log('SDP Delete File: Full path: ' . $full_path);
        
        // ファイルの存在確認
        $file_exists = file_exists($full_path);
        
        if (!$file_exists) {
            error_log('SDP Delete File: File not found at: ' . $full_path);
            
            // ファイルが存在しない場合、データベースだけクリアするオプション
            if ($product_id > 0) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'sdp_products';
                $updated = $wpdb->update(
                    $table_name,
                    array('file_path' => ''),
                    array('id' => $product_id),
                    array('%s'),
                    array('%d')
                );
                
                if ($updated !== false) {
                    error_log('SDP Delete File: File not found, but database cleared for product ID: ' . $product_id);
                    wp_send_json_success(array('message' => 'ファイルは既に削除されていました。データベースを更新しました。'));
                    return;
                } else {
                    error_log('SDP Delete File: Failed to update database for product ID: ' . $product_id . ' - Error: ' . $wpdb->last_error);
                }
            }
            
            wp_send_json_error('ファイルが見つかりません: ' . basename($file_path));
            return;
        }
        
        // sdp-productsディレクトリ内のファイルかチェック
        if (strpos($file_path, 'sdp-products/') !== 0) {
            error_log('SDP Delete File: Invalid file path (not in sdp-products)');
            wp_send_json_error('無効なファイルパスです');
            return;
        }
        
        // ファイルを削除
        if (unlink($full_path)) {
            error_log('SDP Delete File: Successfully deleted: ' . $full_path);
            
            // データベースのfile_pathも更新（商品が存在する場合）
            if ($product_id > 0) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'sdp_products';
                $updated = $wpdb->update(
                    $table_name,
                    array('file_path' => ''),
                    array('id' => $product_id),
                    array('%s'),
                    array('%d')
                );
                
                if ($updated !== false) {
                    error_log('SDP Delete File: Database updated for product ID: ' . $product_id);
                } else {
                    error_log('SDP Delete File: Failed to update database for product ID: ' . $product_id . ' - Error: ' . $wpdb->last_error);
                    wp_send_json_error('ファイルは削除されましたが、データベースの更新に失敗しました。');
                    return;
                }
            }
            
            wp_send_json_success(array('message' => 'ファイルを削除しました'));
        } else {
            error_log('SDP Delete File: Failed to delete file: ' . $full_path);
            wp_send_json_error('ファイルの削除に失敗しました。ファイルのパーミッションを確認してください。');
        }
    }
}
