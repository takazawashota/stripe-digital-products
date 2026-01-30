<?php
/**
 * データベース更新スクリプト
 */

// データベース接続情報
$host = '127.0.0.1';
$port = '8889'; // MAMPのデフォルトポート
$dbname = 'takazawa_work1';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "データベースに接続しました。\n\n";
    
    // カラムが存在するか確認
    $stmt = $pdo->query("SHOW COLUMNS FROM wp_sdp_products LIKE 'image_url'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✓ image_url カラムは既に存在しています。\n";
    } else {
        echo "image_url カラムを追加中...\n";
        $pdo->exec("ALTER TABLE wp_sdp_products ADD COLUMN image_url varchar(500) DEFAULT NULL AFTER description");
        echo "✓ image_url カラムを追加しました！\n";
    }
    
    // テーブル構造を表示
    echo "\n現在のテーブル構造:\n";
    echo str_repeat("-", 60) . "\n";
    $stmt = $pdo->query("DESCRIBE wp_sdp_products");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-20s %-30s\n", $row['Field'], $row['Type']);
    }
    echo str_repeat("-", 60) . "\n";
    
    echo "\n✓ 完了しました！商品の保存が正常に動作するようになります。\n";
    
} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
