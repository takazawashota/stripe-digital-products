# Stripe Digital Products

WordPressでデジタル商品を販売し、Stripe決済を統合するプラグインです。

## 機能

- ✅ Stripe Checkoutによる安全な決済処理
- ✅ デジタル商品（ファイル）の販売と配信
- ✅ 自動ダウンロードリンク生成とメール送信
- ✅ ダウンロード回数制限
- ✅ 商品管理画面
- ✅ 注文履歴管理
- ✅ ショートコードによる簡単な表示
- ✅ テストモードと本番モードの切り替え
- ✅ Webhook対応

## インストール方法

### 1. ファイルのアップロード

プラグインフォルダ全体を `/wp-content/plugins/` ディレクトリにアップロードします。

### 2. Stripeライブラリのインストール

プラグインディレクトリで以下のコマンドを実行してください：

```bash
cd /path/to/wordpress/wp-content/plugins/stripe-digital-products
composer install
```

### 3. プラグインの有効化

WordPress管理画面の「プラグイン」メニューからプラグインを有効化します。

## セットアップ

### 1. StripeのAPIキーを取得

1. [Stripe Dashboard](https://dashboard.stripe.com/)にログイン
2. 「開発者」→「APIキー」からAPIキーを取得
3. テストキーと本番キーの両方を取得

### 2. プラグイン設定

1. WordPress管理画面で「デジタル商品」→「設定」を開く
2. 取得したAPIキーを入力
3. 通貨や成功/キャンセルページを設定
4. 設定を保存

### 3. Webhookの設定（推奨）

1. Stripe Dashboardで「開発者」→「Webhook」を開く
2. エンドポイントを追加: `https://yourdomain.com/sdp-webhook/`
3. イベントを選択:
   - `checkout.session.completed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
4. Webhookシークレットをコピーして、プラグイン設定に入力

## 使い方

### 商品の追加

1. 「デジタル商品」→「新規追加」を開く
2. 商品名、説明、価格を入力
3. 販売するファイルをアップロード
4. 「追加」ボタンをクリック

### フロントエンドでの表示

#### 商品一覧を表示

```
[sdp_products]
```

オプション:
- `columns`: 表示カラム数（デフォルト: 3）

例:
```
[sdp_products columns="2"]
```

#### 特定の商品を表示

```
[sdp_product id="1"]
```

#### マイ注文履歴を表示

```
[sdp_my_orders]
```

※ログインユーザーのみ表示されます

## 購入フロー

1. 顧客が「購入する」ボタンをクリック
2. Stripe Checkoutページにリダイレクト
3. クレジットカード情報を入力して決済
4. 決済完了後、ダウンロードリンク付きのメールが自動送信
5. メール内のリンクからファイルをダウンロード

## ダウンロード制限

- デフォルトで各注文につき5回までダウンロード可能
- ダウンロード回数は管理画面で確認可能

## セキュリティ

- ファイルは `/wp-content/uploads/sdp-products/` に保存
- `.htaccess` により直接アクセスを防止
- ダウンロードは専用トークンを使用して認証
- ダウンロード回数制限による不正利用防止

## 必要要件

- WordPress 5.0以上
- PHP 7.4以上
- Composer（インストール時のみ）
- Stripeアカウント

## よくある質問

### Q: テストモードで動作確認できますか？

A: はい。設定で「テストモード」を選択し、テスト用のAPIキーを入力してください。Stripeのテストカード番号（4242 4242 4242 4242）で決済テストが可能です。

### Q: どのようなファイル形式をアップロードできますか？

A: PDFファイル、ZIPアーカイブ、画像、動画など、WordPressでアップロード可能なファイル形式であれば利用できます。

### Q: 複数の通貨に対応していますか？

A: はい。設定で日本円（JPY）、米ドル（USD）、ユーロ（EUR）などが選択できます。

### Q: ダウンロード回数制限を変更できますか？

A: 現在はコードの変更が必要です。`class-sdp-payment.php` の `download_limit` の値を変更してください。

## サポート

問題が発生した場合は、以下を確認してください：

1. Composer依存関係がインストールされているか
2. APIキーが正しく設定されているか
3. WordPressのパーマリンク設定が有効になっているか
4. PHP error logを確認

## ライセンス

GPL v2 or later

## 開発者向け情報

### ファイル構造

```
stripe-digital-products/
├── stripe-digital-products.php  # メインプラグインファイル
├── composer.json                # Composer設定
├── includes/                    # PHPクラスファイル
│   ├── class-sdp-admin.php
│   ├── class-sdp-products.php
│   ├── class-sdp-payment.php
│   ├── class-sdp-download.php
│   ├── class-sdp-frontend.php
│   └── stripe-php/
│       └── init.php
├── templates/                   # 管理画面テンプレート
│   ├── admin-settings.php
│   ├── admin-products-list.php
│   ├── admin-product-form.php
│   └── admin-orders-list.php
└── assets/                      # CSS/JSファイル
    ├── css/
    │   ├── frontend.css
    │   └── admin.css
    └── js/
        ├── frontend.js
        └── admin.js
```

### データベーステーブル

#### wp_sdp_products

商品情報を保存

#### wp_sdp_orders

注文情報を保存

## 変更履歴

### 1.0.0
- 初回リリース
- 基本的な商品販売機能
- Stripe決済統合
- ダウンロード機能
