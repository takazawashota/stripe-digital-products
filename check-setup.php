<?php
require_once('../../../wp-load.php');
$settings = get_option('sdp_settings');
echo "=== 設定状況 ===\n\n";
echo "動作モード: " . ($settings['stripe_mode'] ?? 'test') . "\n";
echo "通貨: " . strtoupper($settings['currency'] ?? 'jpy') . "\n";
echo "テストキー設定: " . (empty($settings['test_secret_key']) ? 'なし' : 'あり') . "\n";
echo "Webhook URL: " . home_url('/sdp-webhook/') . "\n";
