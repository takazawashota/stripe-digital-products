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
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT o.*, p.name as product_name 
             FROM $orders_table o
             LEFT JOIN {$wpdb->prefix}sdp_products p ON o.product_id = p.id
             ORDER BY o.created_at DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }
    
    /**
     * メールアドレスで注文を取得
     */
    public function get_orders_by_email($email) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'sdp_orders';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT o.*, p.name as product_name 
             FROM $orders_table o
             LEFT JOIN {$wpdb->prefix}sdp_products p ON o.product_id = p.id
             WHERE o.customer_email = %s
             ORDER BY o.created_at DESC",
            $email
        ));
    }
}
