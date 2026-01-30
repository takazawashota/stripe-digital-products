<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Stripe Digital Products マニュアル</h1>
    
    <div style="max-width: 1200px;">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            
            <!-- 左カラム -->
            <div>
                
                <!-- 基本的な使い方 -->
                <div class="card" style="padding: 20px; margin-bottom: 20px;">
                    <h2>📖 基本的な使い方</h2>
                    
                    <ol style="line-height: 1.8;">
                        <li><strong>Stripe APIキーの設定</strong><br>
                            「設定」メニューから、StripeのAPIキー（公開可能キーとシークレットキー）を設定します。<br>
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

            </div>
            
            <!-- 右カラム -->
            <div>
                
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
                        <code style="font-size: 14px; word-break: break-all;"><?php echo esc_url(home_url('/webhook')); ?></code>
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
            
        </div>
        
    </div>
</div>
