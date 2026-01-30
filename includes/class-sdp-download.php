<?php
/**
 * ダウンロード処理クラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDP_Download {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'handle_download'));
        add_action('init', array($this, 'handle_admin_download'));
        add_action('init', array($this, 'handle_admin_preview'));
    }
    
    /**
     * 管理者用ダウンロード処理
     */
    public function handle_admin_download() {
        if (!isset($_GET['sdp_admin_download'])) {
            return;
        }
        
        // 管理者権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません', 'Forbidden', array('response' => 403));
        }
        
        $token = sanitize_text_field($_GET['sdp_admin_download']);
        
        // トークンからファイルパスを取得
        $file_path = get_transient('sdp_admin_download_' . $token);
        
        if (!$file_path) {
            wp_die('無効またはトークンの有効期限が切れています。');
        }
        
        // トークンを削除（一度きり）
        delete_transient('sdp_admin_download_' . $token);
        
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/' . $file_path;
        
        // ファイルの存在確認
        if (!file_exists($full_path)) {
            wp_die('ファイルが見つかりません。');
        }
        
        // セキュリティチェック
        if (strpos($file_path, 'sdp-products/') !== 0) {
            wp_die('不正なファイルパスです。');
        }
        
        // ファイルをダウンロード
        $this->serve_file($full_path, basename($file_path));
    }
    
    /**
     * 管理者用プレビュー処理（画像表示用）
     */
    public function handle_admin_preview() {
        if (!isset($_GET['sdp_admin_preview'])) {
            return;
        }
        
        // 管理者権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません', 'Forbidden', array('response' => 403));
        }
        
        $token = sanitize_text_field($_GET['sdp_admin_preview']);
        
        // トークンからファイルパスを取得
        $file_path = get_transient('sdp_admin_preview_' . $token);
        
        if (!$file_path) {
            wp_die('無効またはトークンの有効期限が切れています。');
        }
        
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/' . $file_path;
        
        // ファイルの存在確認
        if (!file_exists($full_path)) {
            wp_die('ファイルが見つかりません。');
        }
        
        // セキュリティチェック
        if (strpos($file_path, 'sdp-products/') !== 0) {
            wp_die('不正なファイルパスです。');
        }
        
        // 画像を表示
        $this->serve_preview_file($full_path);
    }
    
    /**
     * ダウンロード処理
     */
    public function handle_download() {
        if (!isset($_GET['sdp_download'])) {
            return;
        }
        
        $download_token = sanitize_text_field($_GET['sdp_download']);
        
        // トークンで注文を検索
        $order = $this->get_order_by_token($download_token);
        
        if (!$order) {
            wp_die('無効なダウンロードリンクです。');
        }
        
        // 有効期限をチェック
        if (!empty($order->expires_at)) {
            $expiry_time = strtotime($order->expires_at);
            if ($expiry_time && time() > $expiry_time) {
                wp_die('ダウンロードリンクの有効期限が切れています。');
            }
        }
        
        // ダウンロード制限をチェック
        if ($order->download_count >= $order->download_limit) {
            wp_die('ダウンロード回数の上限に達しました。');
        }
        
        // 商品情報を取得
        $product = SDP_Products::get_instance()->get_product($order->product_id);
        
        if (!$product || empty($product->file_path)) {
            wp_die('ファイルが見つかりません。');
        }
        
        // ファイルパス
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $product->file_path;
        
        if (!file_exists($file_path)) {
            wp_die('ファイルが見つかりません。');
        }
        
        // ダウンロード回数を更新
        $this->increment_download_count($order->id);
        
        // ファイルをダウンロード
        $this->serve_file($file_path, basename($file_path));
    }
    
    /**
     * トークンで注文を取得
     */
    private function get_order_by_token($token) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'sdp_orders';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE download_token = %s AND status = 'completed'",
            $token
        ));
    }
    
    /**
     * ダウンロード回数をインクリメント
     */
    private function increment_download_count($order_id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'sdp_orders';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $orders_table SET download_count = download_count + 1 WHERE id = %d",
            $order_id
        ));
    }
    
    /**
     * ファイルを配信
     */
    private function serve_file($file_path, $filename) {
        // バッファをクリア
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // ヘッダーを設定
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private');
        header('Pragma: private');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        
        // ファイルを出力
        readfile($file_path);
        exit;
    }
    
    /**
     * プレビュー用ファイル配信（画像・PDF）
     */
    private function serve_preview_file($file_path) {
        // バッファをクリア
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // MIMEタイプを取得
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        
        // ヘッダーを設定（インライン表示）
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=300');
        
        // ファイルを出力
        readfile($file_path);
        exit;
    }
    
    /**
     * 注文情報を取得
     */
    public function get_order($order_id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'sdp_orders';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
    }
    
    /**
     * すべての注文を取得
     */
    public function get_all_orders($limit = 100, $offset = 0) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'sdp_orders';
        $products_table = $wpdb->prefix . 'sdp_products';
        
        // product_typeカラムの存在確認
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $products_table LIKE 'product_type'");
        
        if (!empty($column_exists)) {
            // product_typeカラムが存在する場合
            return $wpdb->get_results($wpdb->prepare(
                "SELECT o.*, p.name as product_name, p.product_type 
                 FROM $orders_table o
                 LEFT JOIN $products_table p ON o.product_id = p.id
                 ORDER BY o.created_at DESC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ));
        } else {
            // product_typeカラムが存在しない場合はデフォルト値を設定
            return $wpdb->get_results($wpdb->prepare(
                "SELECT o.*, p.name as product_name, 'digital' as product_type 
                 FROM $orders_table o
                 LEFT JOIN $products_table p ON o.product_id = p.id
                 ORDER BY o.created_at DESC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ));
        }
    }
    
    /**
     * メールアドレスで注文を取得
     */
    public function get_orders_by_email($email) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'sdp_orders';
        $products_table = $wpdb->prefix . 'sdp_products';
        
        // product_typeカラムの存在確認
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $products_table LIKE 'product_type'");
        
        if (!empty($column_exists)) {
            // product_typeカラムが存在する場合
            return $wpdb->get_results($wpdb->prepare(
                "SELECT o.*, p.name as product_name, p.product_type 
                 FROM $orders_table o
                 LEFT JOIN $products_table p ON o.product_id = p.id
                 WHERE o.customer_email = %s
                 ORDER BY o.created_at DESC",
                $email
            ));
        } else {
            // product_typeカラムが存在しない場合はデフォルト値を設定
            return $wpdb->get_results($wpdb->prepare(
                "SELECT o.*, p.name as product_name, 'digital' as product_type 
                 FROM $orders_table o
                 LEFT JOIN $products_table p ON o.product_id = p.id
                 WHERE o.customer_email = %s
                 ORDER BY o.created_at DESC",
                $email
            ));
        }
    }
}
