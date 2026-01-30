<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('sdp_settings');

if (isset($_POST['sdp_save_settings'])) {
    check_admin_referer('sdp_settings_nonce');
    
    $settings = array(
        'stripe_mode' => sanitize_text_field($_POST['stripe_mode']),
        'test_publishable_key' => sanitize_text_field($_POST['test_publishable_key']),
        'test_secret_key' => sanitize_text_field($_POST['test_secret_key']),
        'live_publishable_key' => sanitize_text_field($_POST['live_publishable_key']),
        'live_secret_key' => sanitize_text_field($_POST['live_secret_key']),
        'webhook_secret' => sanitize_text_field($_POST['webhook_secret']),
        'currency' => sanitize_text_field($_POST['currency']),
        'success_page' => intval($_POST['success_page']),
        'cancel_page' => intval($_POST['cancel_page']),
    );
    
    update_option('sdp_settings', $settings);
    echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
}
?>

<div class="wrap">
    <h1>Stripe Digital Products 設定</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('sdp_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label>動作モード</label>
                </th>
                <td>
                    <select name="stripe_mode">
                        <option value="test" <?php selected($settings['stripe_mode'] ?? 'test', 'test'); ?>>テストモード</option>
                        <option value="live" <?php selected($settings['stripe_mode'] ?? 'test', 'live'); ?>>本番モード</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th colspan="2">
                    <h2>テストモード用キー</h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>テスト公開可能キー</label>
                </th>
                <td>
                    <input type="text" name="test_publishable_key" value="<?php echo esc_attr($settings['test_publishable_key'] ?? ''); ?>" class="regular-text" />
                    <p class="description">pk_test_ で始まるキー</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>テストシークレットキー</label>
                </th>
                <td>
                    <input type="password" name="test_secret_key" value="<?php echo esc_attr($settings['test_secret_key'] ?? ''); ?>" class="regular-text" />
                    <p class="description">sk_test_ で始まるキー</p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2">
                    <h2>本番モード用キー</h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>本番公開可能キー</label>
                </th>
                <td>
                    <input type="text" name="live_publishable_key" value="<?php echo esc_attr($settings['live_publishable_key'] ?? ''); ?>" class="regular-text" />
                    <p class="description">pk_live_ で始まるキー</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>本番シークレットキー</label>
                </th>
                <td>
                    <input type="password" name="live_secret_key" value="<?php echo esc_attr($settings['live_secret_key'] ?? ''); ?>" class="regular-text" />
                    <p class="description">sk_live_ で始まるキー</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>Webhook シークレット</label>
                </th>
                <td>
                    <input type="text" name="webhook_secret" value="<?php echo esc_attr($settings['webhook_secret'] ?? ''); ?>" class="regular-text" />
                    <p class="description">whsec_ で始まるキー（オプション）</p>
                    <p class="description">WebhookエンドポイントURL: <code><?php echo home_url('/sdp-webhook/'); ?></code></p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2">
                    <h2>その他の設定</h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>通貨</label>
                </th>
                <td>
                    <select name="currency">
                        <option value="jpy" <?php selected($settings['currency'] ?? 'jpy', 'jpy'); ?>>日本円 (JPY)</option>
                        <option value="usd" <?php selected($settings['currency'] ?? 'jpy', 'usd'); ?>>米ドル (USD)</option>
                        <option value="eur" <?php selected($settings['currency'] ?? 'jpy', 'eur'); ?>>ユーロ (EUR)</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>決済成功ページ</label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'success_page',
                        'selected' => $settings['success_page'] ?? 0,
                        'show_option_none' => 'ページを選択',
                    ));
                    ?>
                    <p class="description">決済完了後にリダイレクトするページ</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>決済キャンセルページ</label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'cancel_page',
                        'selected' => $settings['cancel_page'] ?? 0,
                        'show_option_none' => 'ページを選択',
                    ));
                    ?>
                    <p class="description">決済キャンセル時にリダイレクトするページ</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="sdp_save_settings" class="button button-primary" value="設定を保存" />
        </p>
    </form>
</div>
