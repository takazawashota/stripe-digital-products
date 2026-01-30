<?php
/**
 * データベースにimage_urlカラムを追加するスクリプト
 * ブラウザで直接アクセス: http://localhost:8889/wp-content/plugins/stripe-digital-products/add-image-column.php
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
    die('WordPress not found');
}

global $wpdb;

$table_name = $wpdb->prefix . 'sdp_products';

// カラムが既に存在するか確認
$column_check = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'image_url'");

if (count($column_check) > 0) {
    echo "✓ image_url カラムは既に存在しています。<br>";
} else {
    echo "image_url カラムを追加中...<br>";
    
    $sql = "ALTER TABLE $table_name ADD COLUMN image_url varchar(500) DEFAULT NULL AFTER description";
    
    $result = $wpdb->query($sql);
    
    if ($result === false) {
        echo "✗ エラー: " . $wpdb->last_error . "<br>";
    } else {
        echo "✓ image_url カラムを追加しました！<br>";
    }
}

// 現在のテーブル構造を表示
echo "<br><strong>現在のテーブル構造:</strong><br><pre>";
$columns = $wpdb->get_results("DESCRIBE $table_name");
foreach ($columns as $column) {
    echo $column->Field . " - " . $column->Type . "\n";
}
echo "</pre>";

echo "<br><a href='" . admin_url('admin.php?page=stripe-digital-products') . "'>商品管理画面に戻る</a>";
