<?php
/**
 * Stripe PHP Library Loader
 * 
 * このファイルはStripe PHPライブラリを読み込むためのプレースホルダーです。
 * 実際にプラグインを使用する前に、Composer経由でStripeライブラリをインストールする必要があります。
 */

// Composerのオートローダーを読み込み
$autoload_path = SDP_PLUGIN_DIR . 'vendor/autoload.php';

if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    // Stripeライブラリがインストールされていない場合の警告
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><strong>Stripe Digital Products:</strong> Stripe PHPライブラリがインストールされていません。</p>
            <p>プラグインディレクトリで <code>composer install</code> を実行してください。</p>
        </div>
        <?php
    });
}
