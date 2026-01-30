<?php
/**
 * 画像プレビュー修正スクリプト
 * ブラウザでアクセス: http://localhost:8889/wp-content/plugins/stripe-digital-products/fix-image-preview.php
 */

echo "<h1>画像プレビュー修正</h1>";

// wp-contentディレクトリのパスを検索
$possible_paths = array(
    __DIR__ . '/../../../wp-content/uploads/sdp-products',
    __DIR__ . '/../../../../wp-content/uploads/sdp-products',
    '/var/www/html/wp-content/uploads/sdp-products',
);

$sdp_dir = null;
foreach ($possible_paths as $path) {
    if (is_dir($path)) {
        $sdp_dir = $path;
        break;
    }
}

if (!$sdp_dir) {
    echo "<p style='color: red;'>❌ sdp-productsディレクトリが見つかりません</p>";
    echo "<p>手動で以下のディレクトリを探してください: wp-content/uploads/sdp-products</p>";
    exit;
}

echo "<p>✓ ディレクトリが見つかりました: <code>$sdp_dir</code></p>";

// .htaccessを更新
$htaccess_path = $sdp_dir . '/.htaccess';
$htaccess_content = "# Deny access to all files by default
Order Deny,Allow
Deny from all

# Allow access to image files only
<FilesMatch \"\.(jpg|jpeg|png|gif|webp)$\">
    Allow from all
</FilesMatch>
";

$result = file_put_contents($htaccess_path, $htaccess_content);

if ($result !== false) {
    echo "<p style='color: green;'>✓ .htaccess を更新しました！</p>";
    echo "<pre>" . htmlspecialchars($htaccess_content) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ .htaccess の更新に失敗しました</p>";
}

// ファイル一覧を表示
echo "<h2>アップロード済みファイル</h2>";
$files = scandir($sdp_dir);
echo "<ul>";
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    $color = $is_image ? 'green' : 'gray';
    echo "<li style='color: $color;'><strong>$file</strong> ($ext)" . ($is_image ? " - 表示可能" : " - 保護中") . "</li>";
}
echo "</ul>";

echo "<p><a href='javascript:history.back()'>← 戻る</a></p>";
