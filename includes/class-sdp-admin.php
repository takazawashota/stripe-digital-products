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
}
