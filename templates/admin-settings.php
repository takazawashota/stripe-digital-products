<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('sdp_settings');
$email_settings = get_option('sdp_email_settings');
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

// データベース更新処理
if (isset($_POST['sdp_update_database'])) {
    check_admin_referer('sdp_database_update_nonce');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sdp_products';
    $orders_table = $wpdb->prefix . 'sdp_orders';
    $updates_made = array();
    
    // image_urlカラムが存在するかチェック
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'image_url'");
    
    if (empty($column_exists)) {
        $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN image_url varchar(500) AFTER description");
        
        if ($result !== false) {
            $updates_made[] = 'image_urlカラムを追加';
        } else {
            echo '<div class="notice notice-error"><p>✗ image_urlカラムの追加に失敗: ' . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
    
    // expires_atカラムが存在するかチェック
    $expires_column_exists = $wpdb->get_results("SHOW COLUMNS FROM $orders_table LIKE 'expires_at'");
    
    if (empty($expires_column_exists)) {
        $result = $wpdb->query("ALTER TABLE $orders_table ADD COLUMN expires_at datetime AFTER download_limit");
        
        if ($result !== false) {
            $updates_made[] = 'expires_atカラムを追加';
        } else {
            echo '<div class="notice notice-error"><p>✗ expires_atカラムの追加に失敗: ' . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
    
    if (!empty($updates_made)) {
        echo '<div class="notice notice-success"><p>✓ データベースを更新しました: ' . implode(', ', $updates_made) . '</p></div>';
    } else {
        echo '<div class="notice notice-info"><p>データベースは既に最新です。</p></div>';
    }
}

if (isset($_POST['sdp_save_settings'])) {
    check_admin_referer('sdp_settings_nonce');
    
    $settings = array(
        'stripe_mode' => sanitize_text_field($_POST['stripe_mode']),
        'test_publishable_key' => sanitize_text_field($_POST['test_publishable_key']),
        'test_secret_key' => sanitize_text_field($_POST['test_secret_key']),
        'test_webhook_secret' => sanitize_text_field($_POST['test_webhook_secret']),
        'live_publishable_key' => sanitize_text_field($_POST['live_publishable_key']),
        'live_secret_key' => sanitize_text_field($_POST['live_secret_key']),
        'live_webhook_secret' => sanitize_text_field($_POST['live_webhook_secret']),
        'currency' => sanitize_text_field($_POST['currency']),
        'success_page' => intval($_POST['success_page']),
        'cancel_page' => intval($_POST['cancel_page']),
        'download_limit' => max(1, intval($_POST['download_limit'])),
        'download_expiration_days' => max(1, intval($_POST['download_expiration_days'])),
    );
    
    update_option('sdp_settings', $settings);
    echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
}

if (isset($_POST['sdp_save_email_settings'])) {
    check_admin_referer('sdp_email_settings_nonce');
    
    $email_settings = array(
        'enable_email' => isset($_POST['enable_email']) ? 1 : 0,
        'from_name' => sanitize_text_field($_POST['from_name']),
        'from_email' => sanitize_email($_POST['from_email']),
        'reply_to' => sanitize_email($_POST['reply_to']),
        'return_path' => sanitize_email($_POST['return_path']),
        'subject' => sanitize_text_field($_POST['subject']),
        'body' => wp_kses_post($_POST['body']),
    );
    
    update_option('sdp_email_settings', $email_settings);
    echo '<div class="notice notice-success"><p>メール設定を保存しました。</p></div>';
    $email_settings = get_option('sdp_email_settings'); // 再読み込み
}
?>

<div class="wrap">
    <h1>Stripe Digital Products 設定</h1>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=sdp-settings&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">設定</a>
        <a href="?page=sdp-settings&tab=email" class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">メール設定</a>
        <a href="?page=sdp-settings&tab=manual" class="nav-tab <?php echo $active_tab === 'manual' ? 'nav-tab-active' : ''; ?>">マニュアル</a>
    </nav>
    
    <?php if ($active_tab === 'settings'): ?>
    
    <!-- 設定タブ -->
    <form method="post" action="?page=sdp-settings&tab=settings">
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
                <th scope="row">
                    <label>テストWebhookシークレット</label>
                </th>
                <td>
                    <input type="text" name="test_webhook_secret" value="<?php echo esc_attr($settings['test_webhook_secret'] ?? ''); ?>" class="regular-text" />
                    <p class="description">whsec_ で始まるキー</p>
                    <p class="description"><strong>WebhookエンドポイントURL:</strong><br><code><?php echo plugins_url('webhook.php', dirname(__FILE__)); ?></code></p>
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
                    <label>本番Webhookシークレット</label>
                </th>
                <td>
                    <input type="text" name="live_webhook_secret" value="<?php echo esc_attr($settings['live_webhook_secret'] ?? ''); ?>" class="regular-text" />
                    <p class="description">whsec_ で始まるキー</p>
                    <p class="description"><strong>WebhookエンドポイントURL:</strong><br><code><?php echo plugins_url('webhook.php', dirname(__FILE__)); ?></code></p>
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
            
            <tr>
                <th colspan="2">
                    <h2>ダウンロード設定</h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>ダウンロード可能回数</label>
                </th>
                <td>
                    <input type="number" name="download_limit" value="<?php echo esc_attr($settings['download_limit'] ?? 5); ?>" min="1" step="1" class="small-text" />
                    <p class="description">1つの購入につき、ダウンロードできる最大回数（デフォルト: 5回）</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>ダウンロードリンク有効期限</label>
                </th>
                <td>
                    <input type="number" name="download_expiration_days" value="<?php echo esc_attr($settings['download_expiration_days'] ?? 30); ?>" min="1" step="1" class="small-text" />
                    <span>日間</span>
                    <p class="description">購入完了後、ダウンロードリンクが有効な日数（デフォルト: 30日）</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="sdp_save_settings" class="button button-primary" value="設定を保存" />
        </p>
    </form>
    
    <!-- データベース更新セクション -->
    <div style="margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #ccc; border-radius: 4px;">
        <h2 style="margin-top: 0;">データベース更新</h2>
        <p>プラグインのアップデート後や、データベースエラーが発生した場合は、こちらからデータベース構造を更新してください。</p>
        
        <form method="post" action="?page=sdp-settings&tab=settings" style="margin-top: 15px;">
            <?php wp_nonce_field('sdp_database_update_nonce'); ?>
            <input type="submit" name="sdp_update_database" class="button" value="データベースを更新" 
                   onclick="return confirm('データベースを更新しますか？\n\n※通常は必要ありません。エラーが発生している場合のみ実行してください。');" />
            <p class="description" style="margin-top: 10px;">※このボタンは、データベースに必要なカラムが不足している場合に使用します。</p>
        </form>
    </div>
    
    <?php elseif ($active_tab === 'email'): ?>
    
    <!-- メール設定タブ -->
    <form method="post" action="?page=sdp-settings&tab=email">
        <?php wp_nonce_field('sdp_email_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label>自動返信メール</label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_email" value="1" <?php checked($email_settings['enable_email'] ?? 1, 1); ?> />
                        購入完了時に自動返信メールを送信する
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>送信者名</label>
                </th>
                <td>
                    <input type="text" name="from_name" value="<?php echo esc_attr($email_settings['from_name'] ?? get_bloginfo('name')); ?>" class="regular-text" />
                    <p class="description">メールの送信者名（デフォルト: サイト名）</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>送信者メールアドレス</label>
                </th>
                <td>
                    <input type="email" name="from_email" value="<?php echo esc_attr($email_settings['from_email'] ?? get_option('admin_email')); ?>" class="regular-text" />
                    <p class="description">
                        メールの送信元アドレス（From）<br>
                        <strong style="color: #d63638;">⚠️ 重要: サーバーのドメイン（<?php echo parse_url(home_url(), PHP_URL_HOST); ?>）のメールアドレスを使用してください</strong><br>
                        <small>Gmail等の外部ドメインを使用すると、スパム判定される可能性があります（SPF/DKIM認証失敗）</small>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>返信先メールアドレス</label>
                </th>
                <td>
                    <input type="email" name="reply_to" value="<?php echo esc_attr($email_settings['reply_to'] ?? get_option('admin_email')); ?>" class="regular-text" />
                    <p class="description">
                        顧客が返信する際の宛先（Reply-To）<br>
                        <small>こちらはGmail等の外部アドレスでも問題ありません</small>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>Return-Path</label>
                </th>
                <td>
                    <input type="email" name="return_path" value="<?php echo esc_attr($email_settings['return_path'] ?? get_option('admin_email')); ?>" class="regular-text" />
                    <p class="description">
                        配信エラー時のバウンスメール受信先<br>
                        <strong style="color: #d63638;">⚠️ サーバーのドメイン（<?php echo parse_url(home_url(), PHP_URL_HOST); ?>）のメールアドレスを推奨</strong>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>メール件名</label>
                </th>
                <td>
                    <input type="text" name="subject" value="<?php echo esc_attr($email_settings['subject'] ?? 'ご購入ありがとうございます - {product_name}'); ?>" class="large-text" />
                    <p class="description">使用可能なタグ: <code>{site_name}</code>, <code>{product_name}</code>, <code>{customer_name}</code>, <code>{customer_email}</code></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label>メール本文</label>
                </th>
                <td>
                    <?php 
                    $default_body = "{customer_name} 様\n\nこの度は {product_name} をご購入いただき、誠にありがとうございます。\n\n以下のリンクから商品をダウンロードいただけます：\n{download_link}\n\nダウンロードリンクの有効期限: {expiry_date}\nダウンロード可能回数: {download_limit}回\n\n※このメールは自動送信されています。\n\n{site_name}";
                    ?>
                    <textarea name="body" rows="15" class="large-text" style="font-family: monospace;"><?php echo esc_textarea($email_settings['body'] ?? $default_body); ?></textarea>
                    <p class="description">使用可能なタグ: <code>{site_name}</code>, <code>{customer_name}</code>, <code>{customer_email}</code>, <code>{product_name}</code>, <code>{download_link}</code>, <code>{expiry_date}</code>, <code>{download_limit}</code></p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="sdp_save_email_settings" class="button button-primary" value="メール設定を保存" />
        </p>
    </form>
    
    <?php elseif ($active_tab === 'manual'): ?>
    
    <!-- マニュアルタブ -->
    <div style="margin-top: 20px;">
        
        <!-- 基本的な使い方 -->
        <div class="card" style="padding: 20px; margin-bottom: 20px;">
            <h2>📖 基本的な使い方</h2>
            
            <ol style="line-height: 1.8;">
                <li><strong>Stripe APIキーの設定</strong><br>
                    「設定」タブから、StripeのAPIキー（公開可能キーとシークレットキー）を設定します。<br>
                    <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe ダッシュボード</a>でAPIキーを取得できます。
                </li>
                <li><strong>商品の追加</strong><br>
                    「新規追加」メニューから、商品名・説明・価格・ファイルを設定して商品を登録します。<br>
                    商品画像も設定できます（オプション）。
                </li>
                <li><strong>ショートコードの設置</strong><br>
                    「商品一覧」に表示されているショートコードをコピーして、投稿や固定ページに貼り付けます。
                </li>
                <li><strong>決済とダウンロード</strong><br>
                    顧客が購入ボタンをクリックすると、Stripe決済ページに移動します。<br>
                    決済完了後、購入完了ページでファイルをダウンロードできます。
                </li>
            </ol>
        </div>

        <!-- ショートコード -->
        <div class="card" style="padding: 20px; margin-bottom: 20px;">
            <h2>🔖 ショートコード一覧</h2>
            
            <h3>1. 単一商品の購入ボタン</h3>
            <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #2271b1; margin: 10px 0;">
                <code style="font-size: 14px;">[sdp_product id="1"]</code>
            </div>
            <p class="description">
                <strong>パラメータ:</strong><br>
                • <code>id</code> (必須): 商品IDを指定<br><br>
                <strong>表示内容:</strong> 商品名、画像、説明、価格、購入ボタン
            </p>

            <h3 style="margin-top: 25px;">2. 商品一覧の表示</h3>
            <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #2271b1; margin: 10px 0;">
                <code style="font-size: 14px;">[sdp_products]</code>
            </div>
            <p class="description">
                <strong>パラメータ（オプション）:</strong><br>
                • <code>limit</code>: 表示する商品数（デフォルト: -1 = 全件）<br>
                • <code>columns</code>: グリッドの列数（デフォルト: 3）<br><br>
                <strong>表示内容:</strong> 有効な全商品をグリッド形式で表示
            </p>
            <div style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; margin: 10px 0;">
                <strong>使用例:</strong><br>
                <code>[sdp_products]</code> → 全商品を3列で表示<br>
                <code>[sdp_products limit="6" columns="2"]</code> → 6商品を2列で表示
            </div>

            <h3 style="margin-top: 25px;">3. マイ注文履歴</h3>
            <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #2271b1; margin: 10px 0;">
                <code style="font-size: 14px;">[sdp_my_orders]</code>
            </div>
            <p class="description">
                <strong>パラメータ:</strong> なし<br><br>
                <strong>表示内容:</strong> ログインユーザーの注文履歴とダウンロードリンク<br>
                <strong>注意:</strong> ログインしていない場合はログインを促すメッセージを表示
            </p>
        </div>

        <!-- ファイル管理 -->
        <div class="card" style="padding: 20px; margin-bottom: 20px;">
            <h2>📁 ファイル管理</h2>
            
            <h3>対応ファイル形式</h3>
            <ul style="line-height: 1.8;">
                <li><strong>画像</strong>: JPG, JPEG, PNG, GIF, WEBP</li>
                <li><strong>PDF</strong>: PDF文書</li>
                <li><strong>圧縮ファイル</strong>: ZIP, RAR, 7Z</li>
                <li><strong>その他</strong>: MP3, MP4, EPUB, EXE など、あらゆるファイル形式に対応</li>
            </ul>

            <h3 style="margin-top: 20px;">保存場所</h3>
            <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #2271b1; margin: 10px 0;">
                <code><?php echo esc_html(wp_upload_dir()['basedir']); ?>/sdp-products/</code>
            </div>
            <p class="description">
                アップロードされたファイルは上記ディレクトリに保存され、.htaccessで直接アクセスが制限されています。<br>
                購入者のみがダウンロードリンクを通じてファイルにアクセスできます。
            </p>

            <h3 style="margin-top: 20px;">ファイルの更新</h3>
            <p>商品編集画面で新しいファイルを選択して「更新」ボタンをクリックすると、古いファイルは自動的に削除され、新しいファイルに置き換わります。</p>
        </div>

        <!-- セキュリティ -->
        <div class="card" style="padding: 20px; margin-bottom: 20px;">
            <h2>🔒 セキュリティ</h2>
            
            <ul style="line-height: 1.8;">
                <li><strong>ファイル保護</strong>: .htaccessによりファイルへの直接アクセスを禁止</li>
                <li><strong>一時トークン</strong>: ダウンロードリンクは一時的なトークンで保護</li>
                <li><strong>ダウンロード制限</strong>: 各注文に対してダウンロード回数と有効期限を設定可能</li>
                <li><strong>注文確認</strong>: Webhookによる決済確認後のみダウンロード可能</li>
            </ul>
        </div>

        <!-- Webhook設定 -->
        <div class="card" style="padding: 20px; margin-bottom: 20px;">
            <h2>🔔 Webhook設定（重要）</h2>
            
            <p>決済完了時に自動的に注文を記録するため、StripeにWebhookを設定する必要があります。</p>
            
            <h3>Webhook URL</h3>
            <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #d63638; margin: 10px 0;">
                <code style="font-size: 14px; word-break: break-all;"><?php echo esc_url(plugins_url('webhook.php', dirname(__FILE__))); ?></code>
            </div>

            <h3 style="margin-top: 20px;">設定手順</h3>
            <ol style="line-height: 1.8;">
                <li><a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Webhooks設定</a>を開く</li>
                <li>「エンドポイントを追加」をクリック</li>
                <li>上記のWebhook URLを入力</li>
                <li>「リッスンするイベント」で <code>checkout.session.completed</code> を選択</li>
                <li>「エンドポイントを追加」をクリックして保存</li>
            </ol>
        </div>

        <!-- トラブルシューティング -->
        <div class="card" style="padding: 20px; margin-bottom: 20px;">
            <h2>⚠️ トラブルシューティング</h2>
            
            <h3>商品が「未連携」と表示される</h3>
            <p>商品を編集して「更新」ボタンをクリックすると、Stripeに自動的に連携されます。<br>
            Stripe APIキーが正しく設定されているか確認してください。</p>

            <h3 style="margin-top: 20px;">ファイルがダウンロードできない</h3>
            <ul style="line-height: 1.8;">
                <li>Webhookが正しく設定されているか確認</li>
                <li>ダウンロード回数の上限に達していないか確認</li>
                <li>ダウンロードリンクの有効期限が切れていないか確認</li>
            </ul>

            <h3 style="margin-top: 20px;">決済完了後にエラーが出る</h3>
            <p>Webhook設定を確認してください。Stripe側でWebhookが正常に受信されているか、<br>
            <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripeダッシュボード</a>のWebhookログで確認できます。</p>
        </div>

        <!-- サポート情報 -->
        <div class="card" style="padding: 20px; margin-bottom: 20px; background: #f0f6fc; border-left: 4px solid #2271b1;">
            <h2>💡 サポート情報</h2>
            <p style="line-height: 1.8;">
                <strong>プラグインバージョン:</strong> 1.0.0<br>
                <strong>WordPress バージョン:</strong> <?php echo get_bloginfo('version'); ?><br>
                <strong>PHP バージョン:</strong> <?php echo phpversion(); ?>
            </p>
        </div>

    </div>
    
    <?php endif; ?>
    
</div>

<script>
jQuery(document).ready(function($) {
    // メール設定フォームの送信チェック
    $('form').on('submit', function(e) {
        var formAction = $(this).attr('action');
        
        // メール設定タブのフォームかチェック
        if (formAction && formAction.indexOf('tab=email') !== -1) {
            var emailBody = $('textarea[name="body"]').val();
            
            // {download_link}が含まれているかチェック
            if (emailBody.indexOf('{download_link}') === -1) {
                var confirmed = confirm('メール本文に {download_link} タグが含まれていませんが、問題ないですか？\n\n顧客が商品をダウンロードできなくなる可能性があります。');
                
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            }
        }
    });
});
</script>