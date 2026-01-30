<?php
/**
 * Plugin Name: Stripe Digital Products
 * Plugin URI: https://example.com/stripe-digital-products
 * Description: Stripeを使用してデジタル商品を販売し、決済完了後にダウンロードを提供するプラグイン
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: stripe-digital-products
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの定数を定義
define('SDP_VERSION', '1.0.0');
define('SDP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SDP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SDP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Composer autoloaderの読み込み
require_once SDP_PLUGIN_DIR . 'vendor/autoload.php';

// 主要クラスの読み込み
require_once SDP_PLUGIN_DIR . 'includes/class-sdp-admin.php';
require_once SDP_PLUGIN_DIR . 'includes/class-sdp-products.php';
require_once SDP_PLUGIN_DIR . 'includes/class-sdp-payment.php';
require_once SDP_PLUGIN_DIR . 'includes/class-sdp-download.php';
require_once SDP_PLUGIN_DIR . 'includes/class-sdp-frontend.php';

class Stripe_Digital_Products {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // プラグイン有効化・無効化フック
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // 初期化
        add_action('plugins_loaded', array($this, 'init'));
        
        // アセットの読み込み
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function init() {
        // 各クラスの初期化
        SDP_Admin::get_instance();
        SDP_Products::get_instance();
        SDP_Payment::get_instance();
        SDP_Download::get_instance();
        SDP_Frontend::get_instance();
    }
    
    public function activate() {
        // データベーステーブルの作成
        $this->create_tables();
        
        // デフォルトオプションの設定
        $default_options = array(
            'stripe_mode' => 'test',
            'test_publishable_key' => '',
            'test_secret_key' => '',
            'live_publishable_key' => '',
            'live_secret_key' => '',
            'currency' => 'jpy',
            'success_page' => '',
            'cancel_page' => '',
        );
        
        add_option('sdp_settings', $default_options);
        
        // アップロードディレクトリの作成
        $upload_dir = wp_upload_dir();
        $sdp_upload_dir = $upload_dir['basedir'] . '/sdp-products';
        
        if (!file_exists($sdp_upload_dir)) {
            wp_mkdir_p($sdp_upload_dir);
            
            // .htaccessで画像以外の直接アクセスを防ぐ
            $htaccess_content = "# Deny access to all files by default\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n\n";
            $htaccess_content .= "# Allow access to image files only\n";
            $htaccess_content .= "<FilesMatch \"\.(jpg|jpeg|png|gif|webp)$\">\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            
            file_put_contents($sdp_upload_dir . '/.htaccess', $htaccess_content);
            file_put_contents($sdp_upload_dir . '/index.php', '<?php // Silence is golden');
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'sdp_products';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            image_url varchar(500),
            price decimal(10,2) NOT NULL,
            file_path varchar(500),
            stripe_product_id varchar(100),
            stripe_price_id varchar(100),
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // 注文テーブル
        $orders_table = $wpdb->prefix . 'sdp_orders';
        
        $sql = "CREATE TABLE IF NOT EXISTS $orders_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_name varchar(255),
            amount decimal(10,2) NOT NULL,
            currency varchar(10) NOT NULL,
            stripe_payment_intent_id varchar(100),
            stripe_checkout_session_id varchar(100),
            status varchar(20) DEFAULT 'pending',
            download_token varchar(100),
            download_count int(11) DEFAULT 0,
            download_limit int(11) DEFAULT 5,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY customer_email (customer_email),
            KEY download_token (download_token)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style('sdp-frontend', SDP_PLUGIN_URL . 'assets/css/frontend.css', array(), SDP_VERSION);
        wp_enqueue_script('sdp-frontend', SDP_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SDP_VERSION, true);
        
        wp_localize_script('sdp-frontend', 'sdp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sdp_nonce'),
        ));
    }
    
    public function enqueue_admin_assets($hook) {
        // デジタル商品関連のページでのみ読み込む
        if (strpos($hook, 'stripe-digital-products') === false && strpos($hook, 'sdp-') === false) {
            return;
        }
        
        wp_enqueue_style('sdp-admin', SDP_PLUGIN_URL . 'assets/css/admin.css', array(), SDP_VERSION);
        wp_enqueue_script('sdp-admin', SDP_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SDP_VERSION, true);
        
        wp_localize_script('sdp-admin', 'sdp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sdp_admin_nonce'),
            'upload_url' => wp_upload_dir()['baseurl'] . '/',
        ));
    }
}

// プラグインのインスタンスを初期化
function sdp() {
    return Stripe_Digital_Products::get_instance();
}

sdp();
