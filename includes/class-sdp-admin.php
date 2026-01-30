<?php
/**
 * 管理画面クラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDP_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // 管理者用ファイルダウンロードエンドポイント
        add_action('wp_ajax_sdp_admin_download_file', array($this, 'admin_download_file'));
        // 管理者用ファイルプレビューエンドポイント
        add_action('wp_ajax_sdp_admin_preview_file', array($this, 'admin_preview_file'));
    }
    
    public function add_admin_menu() {
        // メインメニュー
        add_menu_page(
            'Stripe Digital Products',
            'デジタル商品',
            'manage_options',
            'stripe-digital-products',
            array($this, 'render_products_page'),
            'dashicons-cart',
            30
        );
        
        // 商品一覧
        add_submenu_page(
            'stripe-digital-products',
            '商品一覧',
            '商品一覧',
            'manage_options',
            'stripe-digital-products',
            array($this, 'render_products_page')
        );
        
        // 新規追加
        add_submenu_page(
            'stripe-digital-products',
            '新規追加',
            '新規追加',
            'manage_options',
            'sdp-add-product',
            array($this, 'render_add_product_page')
        );
        
        // 注文一覧
        add_submenu_page(
            'stripe-digital-products',
            '注文一覧',
            '注文一覧',
            'manage_options',
            'sdp-orders',
            array($this, 'render_orders_page')
        );
        
        // 設定
        add_submenu_page(
            'stripe-digital-products',
            '設定',
            '設定',
            'manage_options',
            'sdp-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('sdp_settings_group', 'sdp_settings');
    }
    
    public function render_products_page() {
        include SDP_PLUGIN_DIR . 'templates/admin-products-list.php';
    }
    
    public function render_add_product_page() {
        include SDP_PLUGIN_DIR . 'templates/admin-product-form.php';
    }
    
    public function render_orders_page() {
        include SDP_PLUGIN_DIR . 'templates/admin-orders-list.php';
    }
    
    public function render_settings_page() {
        include SDP_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * 管理者用ファイルダウンロード
     */
    public function admin_download_file() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません', 'Forbidden', array('response' => 403));
        }
        
        // nonceチェック
        check_ajax_referer('sdp_admin_nonce', 'nonce');
        
        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        
        if (empty($file_path)) {
            wp_send_json_error('ファイルパスが指定されていません');
        }
        
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/' . $file_path;
        
        // ファイルの存在確認
        if (!file_exists($full_path)) {
            wp_send_json_error('ファイルが見つかりません: ' . basename($file_path));
        }
        
        // セキュリティチェック: sdp-productsディレクトリ内のファイルのみ許可
        if (strpos($file_path, 'sdp-products/') !== 0) {
            wp_send_json_error('不正なファイルパスです');
        }
        
        // 一時的なトークンを生成してリダイレクトURLを返す
        $token = wp_generate_password(32, false);
        set_transient('sdp_admin_download_' . $token, $file_path, 60); // 60秒有効
        
        $download_url = add_query_arg(array(
            'sdp_admin_download' => $token,
        ), admin_url('admin-ajax.php'));
        
        wp_send_json_success(array(
            'download_url' => $download_url,
        ));
    }
    
    /**
     * 管理者用ファイルプレビュー（画像用）
     */
    public function admin_preview_file() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません', 'Forbidden', array('response' => 403));
        }
        
        // nonceチェック
        check_ajax_referer('sdp_admin_nonce', 'nonce');
        
        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        
        if (empty($file_path)) {
            wp_send_json_error('ファイルパスが指定されていません');
        }
        
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/' . $file_path;
        
        // ファイルの存在確認
        if (!file_exists($full_path)) {
            wp_send_json_error('ファイルが見つかりません');
        }
        
        // セキュリティチェック
        if (strpos($file_path, 'sdp-products/') !== 0) {
            wp_send_json_error('不正なファイルパスです');
        }
        
        // 一時的なトークンを生成
        $token = wp_generate_password(32, false);
        set_transient('sdp_admin_preview_' . $token, $file_path, 300); // 5分有効
        
        $preview_url = add_query_arg(array(
            'sdp_admin_preview' => $token,
        ), home_url('/'));
        
        wp_send_json_success(array(
            'preview_url' => $preview_url,
        ));
    }
}
