<?php
/**
 * 決済処理クラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDP_Payment {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_sdp_create_checkout_session', array($this, 'ajax_create_checkout_session'));
        add_action('wp_ajax_nopriv_sdp_create_checkout_session', array($this, 'ajax_create_checkout_session'));
        
        // Webhook用のAJAXアクション（nopriv必須）
        add_action('wp_ajax_nopriv_sdp_stripe_webhook', array($this, 'handle_webhook_ajax'));
        add_action('wp_ajax_sdp_stripe_webhook', array($this, 'handle_webhook_ajax'));
        
        // REST APIエンドポイントの登録
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // 従来のWebhookエンドポイント登録
        add_action('init', array($this, 'register_webhook_endpoint'));
    }
    
    /**
     * admin-ajax.php経由のWebhookハンドラー
     */
    public function handle_webhook_ajax() {
        $this->log_to_file('=== Webhook received via admin-ajax ===');
        $this->log_to_file('POST data: ' . print_r($_POST, true));
        
        $settings = get_option('sdp_settings');
        $stripe_mode = isset($settings['stripe_mode']) ? $settings['stripe_mode'] : 'test';
        $secret_key = $stripe_mode === 'live' 
            ? $settings['live_secret_key'] 
            : $settings['test_secret_key'];
        
        \Stripe\Stripe::setApiKey($secret_key);
        
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $endpoint_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
        
        $this->log_to_file('Payload length: ' . strlen($payload));
        $this->log_to_file('Has signature: ' . (!empty($sig_header) ? 'yes' : 'no'));
        
        try {
            if ($endpoint_secret && $sig_header) {
                $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
                $this->log_to_file('Event verified with signature');
            } else {
                $event = json_decode($payload);
                $this->log_to_file('Event parsed without signature verification');
            }
            
            // イベントタイプに応じて処理
            $this->log_to_file('Event Type: ' . $event->type);
            
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->log_to_file('Processing checkout.session.completed');
                    $this->handle_checkout_completed($event->data->object);
                    break;
                    
                case 'payment_intent.succeeded':
                    $this->log_to_file('Processing payment_intent.succeeded');
                    $this->handle_payment_succeeded($event->data->object);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->log_to_file('Processing payment_intent.payment_failed');
                    $this->handle_payment_failed($event->data->object);
                    break;
                default:
                    $this->log_to_file('Unhandled event type: ' . $event->type);
            }
            
            wp_send_json_success(array('status' => 'success'));
            
        } catch (\Exception $e) {
            $this->log_to_file('Webhook Error: ' . $e->getMessage());
            wp_send_json_error(array('error' => $e->getMessage()), 400);
        }
    }
    
    /**
     * REST APIルートを登録
     */
    public function register_rest_routes() {
        register_rest_route('sdp/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook_rest'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Webhookエンドポイントを登録（従来方式）
     */
    public function register_webhook_endpoint() {
        add_rewrite_rule('^sdp-webhook/?', 'index.php?sdp_webhook=1', 'top');
        add_filter('query_vars', function($vars) {
            $vars[] = 'sdp_webhook';
            return $vars;
        });
        
        add_action('template_redirect', function() {
            if (get_query_var('sdp_webhook')) {
                $this->handle_webhook();
                exit;
            }
        });
    }
    
    /**
     * REST API経由のWebhookハンドラー
     */
    public function handle_webhook_rest($request) {
        $this->log_to_file('=== Webhook received via REST API ===');
        $this->log_to_file('Request body: ' . $request->get_body());
        
        $settings = get_option('sdp_settings');
        $stripe_mode = isset($settings['stripe_mode']) ? $settings['stripe_mode'] : 'test';
        $secret_key = $stripe_mode === 'live' 
            ? $settings['live_secret_key'] 
            : $settings['test_secret_key'];
        
        \Stripe\Stripe::setApiKey($secret_key);
        
        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe_signature');
        $endpoint_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
        
        try {
            if ($endpoint_secret && $sig_header) {
                $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
                $this->log_to_file('Event verified with signature');
            } else {
                $event = json_decode($payload);
                $this->log_to_file('Event parsed without signature verification');
            }
            
            // イベントタイプに応じて処理
            $this->log_to_file('Event Type: ' . $event->type);
            
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->log_to_file('Processing checkout.session.completed');
                    $this->handle_checkout_completed($event->data->object);
                    break;
                    
                case 'payment_intent.succeeded':
                    $this->log_to_file('Processing payment_intent.succeeded');
                    $this->handle_payment_succeeded($event->data->object);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->log_to_file('Processing payment_intent.payment_failed');
                    $this->handle_payment_failed($event->data->object);
                    break;
                default:
                    $this->log_to_file('Unhandled event type: ' . $event->type);
            }
            
            return array('status' => 'success');
            
        } catch (\Exception $e) {
            $this->log_to_file('Webhook Error: ' . $e->getMessage());
            return new \WP_Error('webhook_error', $e->getMessage(), array('status' => 400));
        }
    }
    
    /**
     * Stripe Checkout セッションを作成
     */
    public function create_checkout_session($product_id) {
        $product = SDP_Products::get_instance()->get_product($product_id);
        
        if (!$product) {
            error_log('SDP Checkout: Product not found - ID: ' . $product_id);
            return false;
        }
        
        $settings = get_option('sdp_settings');
        $stripe_mode = isset($settings['stripe_mode']) ? $settings['stripe_mode'] : 'test';
        $secret_key = $stripe_mode === 'live' 
            ? $settings['live_secret_key'] 
            : $settings['test_secret_key'];
        
        if (empty($secret_key)) {
            error_log('SDP Checkout: Stripe API key not configured');
            return false;
        }
        
        // Stripe Price IDが存在するかチェック
        if (empty($product->stripe_price_id)) {
            error_log('SDP Checkout: Stripe Price ID is missing for product ID: ' . $product_id);
            error_log('SDP Checkout: Product data: ' . print_r($product, true));
            return false;
        }
        
        \Stripe\Stripe::setApiKey($secret_key);
        
        try {
            $success_url = !empty($settings['success_page']) 
                ? get_permalink($settings['success_page']) 
                : home_url('/');
            $cancel_url = !empty($settings['cancel_page']) 
                ? get_permalink($settings['cancel_page']) 
                : home_url('/');
            
            // セッションURLにトークンを含める
            $success_url = add_query_arg('session_id', '{CHECKOUT_SESSION_ID}', $success_url);
            
            error_log('SDP Checkout: Creating session for Price ID: ' . $product->stripe_price_id);
            
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $product->stripe_price_id,
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'customer_email' => is_user_logged_in() ? wp_get_current_user()->user_email : null,
                'metadata' => [
                    'product_id' => $product_id,
                ],
            ]);
            
            error_log('SDP Checkout: Session created successfully - ID: ' . $session->id);
            return $session;
            
        } catch (\Exception $e) {
            error_log('Stripe Checkout Session Error: ' . $e->getMessage());
            error_log('Stripe Checkout Session Error Details: ' . print_r($e, true));
            return false;
        }
    }
    
    /**
     * AJAX: Checkoutセッションを作成
     */
    public function ajax_create_checkout_session() {
        check_ajax_referer('sdp_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        error_log('SDP Checkout AJAX: Product ID: ' . $product_id);
        
        $session = $this->create_checkout_session($product_id);
        
        if ($session) {
            error_log('SDP Checkout AJAX: Success - Session URL: ' . $session->url);
            wp_send_json_success(array(
                'session_id' => $session->id,
                'url' => $session->url,
            ));
        } else {
            error_log('SDP Checkout AJAX: Failed to create session');
            wp_send_json_error('セッションの作成に失敗しました。Stripe設定とStripe Price IDを確認してください。');
        }
    }
    
    /**
     * カスタムログファイルに書き込み
     */
    private function log_to_file($message) {
        $log_file = SDP_PLUGIN_DIR . 'debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
        error_log($message); // 通常のerror_logにも出力
    }
    
    /**
     * Webhookハンドラー
     */
    public function handle_webhook() {
        $this->log_to_file('=== SDP Webhook: Received ===');
        $this->log_to_file('Request Method: ' . $_SERVER['REQUEST_METHOD']);
        $this->log_to_file('Request URI: ' . $_SERVER['REQUEST_URI']);
        
        $settings = get_option('sdp_settings');
        $stripe_mode = isset($settings['stripe_mode']) ? $settings['stripe_mode'] : 'test';
        $secret_key = $stripe_mode === 'live' 
            ? $settings['live_secret_key'] 
            : $settings['test_secret_key'];
        
        $this->log_to_file('Stripe Mode: ' . $stripe_mode);
        \Stripe\Stripe::setApiKey($secret_key);
        
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $endpoint_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
        
        try {
            if ($endpoint_secret) {
                $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
            } else {
                $event = json_decode($payload);
            }
            
            // イベントタイプに応じて処理
            $this->log_to_file('Event Type: ' . $event->type);
            
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->log_to_file('Processing checkout.session.completed');
                    $this->handle_checkout_completed($event->data->object);
                    break;
                    
                case 'payment_intent.succeeded':
                    $this->log_to_file('Processing payment_intent.succeeded');
                    $this->handle_payment_succeeded($event->data->object);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->log_to_file('Processing payment_intent.payment_failed');
                    $this->handle_payment_failed($event->data->object);
                    break;
                default:
                    $this->log_to_file('Unhandled event type: ' . $event->type);
            }
            
            http_response_code(200);
            echo json_encode(['status' => 'success']);
            
        } catch (\Exception $e) {
            $this->log_to_file('Webhook Error: ' . $e->getMessage());
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        exit;
    }
    
    /**
     * Checkout完了時の処理
     */
    private function handle_checkout_completed($session) {
        global $wpdb;
        
        // セッション情報を取得
        $settings = get_option('sdp_settings');
        $stripe_mode = isset($settings['stripe_mode']) ? $settings['stripe_mode'] : 'test';
        $secret_key = $stripe_mode === 'live' 
            ? $settings['live_secret_key'] 
            : $settings['test_secret_key'];
        
        \Stripe\Stripe::setApiKey($secret_key);
        
        try {
            $full_session = \Stripe\Checkout\Session::retrieve([
                'id' => $session->id,
                'expand' => ['line_items', 'customer_details'],
            ]);
            
            $product_id = $session->metadata->product_id ?? 0;
            $this->log_to_file('Product ID from metadata: ' . $product_id);
            
            $product = SDP_Products::get_instance()->get_product($product_id);
            
            if (!$product) {
                $this->log_to_file('ERROR: Product not found for ID: ' . $product_id);
                return;
            }
            
            $this->log_to_file('Product found: ' . $product->name);
            
            // 注文を作成
            $download_token = wp_generate_password(32, false);
            $this->log_to_file('Generated download token: ' . $download_token);
            
            $orders_table = $wpdb->prefix . 'sdp_orders';
            
            $order_data = array(
                'product_id' => $product_id,
                'customer_email' => $full_session->customer_details->email,
                'customer_name' => $full_session->customer_details->name,
                'amount' => $full_session->amount_total / 100,
                'currency' => $full_session->currency,
                'stripe_payment_intent_id' => $full_session->payment_intent,
                'stripe_checkout_session_id' => $session->id,
                'status' => 'completed',
                'download_token' => $download_token,
                'download_count' => 0,
                'download_limit' => 5,
            );
            
            $this->log_to_file('Inserting order data: ' . print_r($order_data, true));
            
            $insert_result = $wpdb->insert($orders_table, $order_data);
            
            if ($insert_result === false) {
                $this->log_to_file('ERROR: Failed to insert order - ' . $wpdb->last_error);
                return;
            }
            
            $order_id = $wpdb->insert_id;
            $this->log_to_file('Order created successfully - Order ID: ' . $order_id);
            
            // メール送信
            $this->log_to_file('Attempting to send email to: ' . $full_session->customer_details->email);
            $mail_result = $this->send_purchase_email($full_session->customer_details->email, $product, $download_token);
            $this->log_to_file('Email send result: ' . ($mail_result ? 'SUCCESS' : 'FAILED'));
            
        } catch (\Exception $e) {
            $this->log_to_file('Checkout Completed Handler Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 決済成功時の処理
     */
    private function handle_payment_succeeded($payment_intent) {
        // 必要に応じて追加処理
    }
    
    /**
     * 決済失敗時の処理
     */
    private function handle_payment_failed($payment_intent) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'sdp_orders';
        
        $wpdb->update(
            $orders_table,
            array('status' => 'failed'),
            array('stripe_payment_intent_id' => $payment_intent->id)
        );
    }
    
    /**
     * 購入完了メールを送信
     */
    private function send_purchase_email($email, $product, $download_token) {
        $download_url = add_query_arg(array(
            'sdp_download' => $download_token,
        ), home_url('/'));
        
        $subject = sprintf('[%s] 商品購入ありがとうございます', get_bloginfo('name'));
        
        $price_display = (floor($product->price) == $product->price) ? number_format((int)$product->price) : number_format($product->price, 2);
        
        $message = sprintf(
            "ご購入ありがとうございます。\n\n" .
            "商品名: %s\n" .
            "価格: ¥%s\n\n" .
            "以下のリンクからダウンロードできます:\n%s\n\n" .
            "※このリンクは5回までダウンロード可能です。\n\n" .
            "ご不明な点がございましたら、お気軽にお問い合わせください。",
            $product->name,
            $price_display,
            $download_url
        );
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        $this->log_to_file('Mail details:');
        $this->log_to_file('To: ' . $email);
        $this->log_to_file('Subject: ' . $subject);
        $this->log_to_file('Download URL: ' . $download_url);
        
        $result = wp_mail($email, $subject, $message, $headers);
        
        if (!$result) {
            $this->log_to_file('wp_mail() returned false');
        }
        
        return $result;
    }
}
