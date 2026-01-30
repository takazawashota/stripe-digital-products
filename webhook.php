<?php
/**
 * Stripe Webhook Endpoint
 * 独立したWebhookエンドポイント
 */

// WordPressを読み込む
$wp_load_paths = array(
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
);

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    http_response_code(500);
    echo json_encode(['error' => 'WordPress not found']);
    exit;
}

// ログ関数
function sdp_log($message) {
    $log_file = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

sdp_log('=== Webhook received ===');
sdp_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
sdp_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sdp_log('ERROR: Not a POST request');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Stripe設定を取得
$settings = get_option('sdp_settings');
$stripe_mode = isset($settings['stripe_mode']) ? $settings['stripe_mode'] : 'test';
$secret_key = $stripe_mode === 'live' 
    ? $settings['live_secret_key'] 
    : $settings['test_secret_key'];

if (empty($secret_key)) {
    sdp_log('ERROR: Stripe API key not configured');
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured']);
    exit;
}

\Stripe\Stripe::setApiKey($secret_key);
sdp_log('Stripe initialized in ' . $stripe_mode . ' mode');

// Webhookペイロードを取得
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';

sdp_log('Payload length: ' . strlen($payload));
sdp_log('Has signature: ' . (!empty($sig_header) ? 'yes' : 'no'));
sdp_log('Has endpoint secret: ' . (!empty($endpoint_secret) ? 'yes' : 'no'));

try {
    // 署名検証
    if ($endpoint_secret && $sig_header) {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        sdp_log('Event verified with signature');
    } else {
        $event = json_decode($payload);
        sdp_log('Event parsed without signature verification (WARNING: Not secure)');
    }
    
    if (!$event || !isset($event->type)) {
        throw new Exception('Invalid event data');
    }
    
    sdp_log('Event Type: ' . $event->type);
    sdp_log('Event ID: ' . ($event->id ?? 'unknown'));
    
    // イベント処理
    switch ($event->type) {
        case 'checkout.session.completed':
            sdp_log('Processing checkout.session.completed');
            handle_checkout_completed($event->data->object);
            break;
            
        case 'payment_intent.succeeded':
            sdp_log('Processing payment_intent.succeeded');
            break;
            
        case 'payment_intent.payment_failed':
            sdp_log('Processing payment_intent.payment_failed');
            break;
            
        default:
            sdp_log('Unhandled event type: ' . $event->type);
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    sdp_log('Webhook processed successfully');
    
} catch (\Exception $e) {
    sdp_log('ERROR: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

exit;

/**
 * Checkout完了処理
 */
function handle_checkout_completed($session) {
    global $wpdb;
    
    sdp_log('handle_checkout_completed called');
    
    // Stripe設定を取得
    $settings = get_option('sdp_settings');
    $stripe_mode = isset($settings['stripe_mode']) ? $settings['stripe_mode'] : 'test';
    $secret_key = $stripe_mode === 'live' 
        ? $settings['live_secret_key'] 
        : $settings['test_secret_key'];
    
    \Stripe\Stripe::setApiKey($secret_key);
    
    try {
        // セッション詳細を取得
        $full_session = \Stripe\Checkout\Session::retrieve([
            'id' => $session->id,
            'expand' => ['line_items', 'customer_details'],
        ]);
        
        $product_id = $session->metadata->product_id ?? 0;
        sdp_log('Product ID: ' . $product_id);
        
        if (!$product_id) {
            sdp_log('ERROR: No product_id in metadata');
            return;
        }
        
        // 商品情報を取得
        $products_table = $wpdb->prefix . 'sdp_products';
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $products_table WHERE id = %d",
            $product_id
        ));
        
        if (!$product) {
            sdp_log('ERROR: Product not found for ID: ' . $product_id);
            return;
        }
        
        sdp_log('Product found: ' . $product->name);
        
        // ダウンロードトークンを生成
        $download_token = wp_generate_password(32, false);
        sdp_log('Generated token: ' . $download_token);
        
        // 注文を作成
        $orders_table = $wpdb->prefix . 'sdp_orders';
        
        // 金額を正しく処理（ゼロディシマル通貨対応）
        $zero_decimal_currencies = array('jpy', 'krw');
        $currency = strtolower($full_session->currency);
        $amount = in_array($currency, $zero_decimal_currencies) 
            ? $full_session->amount_total 
            : $full_session->amount_total / 100;
        
        $order_data = array(
            'product_id' => $product_id,
            'customer_email' => $full_session->customer_details->email,
            'customer_name' => $full_session->customer_details->name,
            'amount' => $amount,
            'currency' => $currency,
            'stripe_payment_intent_id' => $full_session->payment_intent,
            'stripe_checkout_session_id' => $session->id,
            'status' => 'completed',
            'download_token' => $download_token,
            'download_count' => 0,
            'download_limit' => 5,
        );
        
        sdp_log('Inserting order: ' . print_r($order_data, true));
        
        $insert_result = $wpdb->insert($orders_table, $order_data);
        
        if ($insert_result === false) {
            sdp_log('ERROR: Failed to insert order - ' . $wpdb->last_error);
            return;
        }
        
        $order_id = $wpdb->insert_id;
        sdp_log('Order created successfully - ID: ' . $order_id);
        
        // メール送信
        $customer_email = $full_session->customer_details->email;
        sdp_log('Sending email to: ' . $customer_email);
        
        $download_url = add_query_arg(array(
            'sdp_download' => $download_token,
        ), home_url('/'));
        
        $subject = sprintf('[%s] 商品購入ありがとうございます', get_bloginfo('name'));
        
        $price_display = (floor($product->price) == $product->price) 
            ? number_format((int)$product->price) 
            : number_format($product->price, 2);
        
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
        
        sdp_log('Email subject: ' . $subject);
        sdp_log('Download URL: ' . $download_url);
        
        $mail_result = wp_mail($customer_email, $subject, $message, $headers);
        
        if ($mail_result) {
            sdp_log('Email sent successfully');
        } else {
            sdp_log('ERROR: wp_mail() returned false');
        }
        
    } catch (\Exception $e) {
        sdp_log('ERROR in handle_checkout_completed: ' . $e->getMessage());
    }
}
