<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'sdp_products';

// 現在のビュー（すべて/有効/無効/ゴミ箱）
$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$product_type_filter = isset($_GET['product_type']) ? sanitize_text_field($_GET['product_type']) : '';

// ゴミ箱に移動
if (isset($_GET['action']) && $_GET['action'] === 'trash' && isset($_GET['product_id'])) {
    check_admin_referer('sdp_trash_product_' . $_GET['product_id']);
    $wpdb->update($table_name, array('status' => 'trash'), array('id' => intval($_GET['product_id'])));
    echo '<div class="notice notice-success"><p>商品をゴミ箱に移動しました。</p></div>';
}

// ゴミ箱から復元
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['product_id'])) {
    check_admin_referer('sdp_restore_product_' . $_GET['product_id']);
    $wpdb->update($table_name, array('status' => 'active'), array('id' => intval($_GET['product_id'])));
    echo '<div class="notice notice-success"><p>商品を復元しました。</p></div>';
}

// 完全削除
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['product_id'])) {
    check_admin_referer('sdp_delete_product_' . $_GET['product_id']);
    $product = SDP_Products::get_instance()->get_product(intval($_GET['product_id']));
    if ($product && !empty($product->file_path)) {
        $upload_dir = wp_upload_dir();
        $file_full_path = $upload_dir['basedir'] . '/' . $product->file_path;
        if (file_exists($file_full_path)) {
            @unlink($file_full_path);
        }
    }
    SDP_Products::get_instance()->delete_product(intval($_GET['product_id']));
    echo '<div class="notice notice-success"><p>商品を完全に削除しました。</p></div>';
}

// 商品取得のクエリ構築
$where = array();
$where_values = array();

if ($current_status === 'all') {
    $where[] = "status != 'trash'";
} elseif ($current_status === 'trash') {
    $where[] = "status = 'trash'";
} else {
    $where[] = "status = %s";
    $where_values[] = $current_status;
}

if ($search) {
    $where[] = "name LIKE %s";
    $where_values[] = '%' . $wpdb->esc_like($search) . '%';
}

// product_typeカラムの存在確認
$product_type_column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'product_type'");

if ($product_type_filter && !empty($product_type_column_exists)) {
    $where[] = "product_type = %s";
    $where_values[] = $product_type_filter;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC";

if (!empty($where_values)) {
    $products = $wpdb->get_results($wpdb->prepare($query, ...$where_values));
} else {
    $products = $wpdb->get_results($query);
}

// ステータスごとの件数を取得
$status_counts = array(
    'all' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status != 'trash'"),
    'active' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'"),
    'inactive' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'inactive'"),
    'trash' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'trash'"),
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">商品一覧</h1>
    <a href="<?php echo admin_url('admin.php?page=sdp-add-product'); ?>" class="page-title-action">新規追加</a>
    
    <hr class="wp-header-end">
    
    <!-- ステータスフィルター -->
    <ul class="subsubsub">
        <li><a href="<?php echo admin_url('admin.php?page=stripe-digital-products&status=all'); ?>" class="<?php echo $current_status === 'all' ? 'current' : ''; ?>">すべて <span class="count">(<?php echo $status_counts['all']; ?>)</span></a> |</li>
        <li><a href="<?php echo admin_url('admin.php?page=stripe-digital-products&status=active'); ?>" class="<?php echo $current_status === 'active' ? 'current' : ''; ?>">有効 <span class="count">(<?php echo $status_counts['active']; ?>)</span></a> |</li>
        <li><a href="<?php echo admin_url('admin.php?page=stripe-digital-products&status=inactive'); ?>" class="<?php echo $current_status === 'inactive' ? 'current' : ''; ?>">無効 <span class="count">(<?php echo $status_counts['inactive']; ?>)</span></a> |</li>
        <li><a href="<?php echo admin_url('admin.php?page=stripe-digital-products&status=trash'); ?>" class="<?php echo $current_status === 'trash' ? 'current' : ''; ?>">ゴミ箱 <span class="count">(<?php echo $status_counts['trash']; ?>)</span></a></li>
    </ul>
    
    <!-- 検索・フィルターフォーム -->
    <div class="tablenav top" style="margin-top: 15px;">
        <div class="alignleft actions">
            <form method="get" style="display: inline-flex; gap: 8px;">
                <input type="hidden" name="page" value="stripe-digital-products" />
                <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>" />
                
                <select name="product_type" style="vertical-align: top;">
                    <option value="">すべての商品種類</option>
                    <option value="digital" <?php selected($product_type_filter, 'digital'); ?>>デジタル商品</option>
                    <option value="physical" <?php selected($product_type_filter, 'physical'); ?>>物理商品</option>
                    <option value="service" <?php selected($product_type_filter, 'service'); ?>>サービス</option>
                </select>
                
                <input type="submit" class="button" value="フィルター" />
            </form>
        </div>
        
        <div class="alignright" style="margin: 6px 0;">
            <form method="get">
                <input type="hidden" name="page" value="stripe-digital-products" />
                <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>" />
                <?php if ($product_type_filter): ?>
                <input type="hidden" name="product_type" value="<?php echo esc_attr($product_type_filter); ?>" />
                <?php endif; ?>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="商品名で検索..." />
                <input type="submit" class="button" value="検索" />
            </form>
        </div>
    </div>
    
    <?php if (empty($products)): ?>
        <p>商品が見つかりませんでした。</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 40px;">ID</th>
                    <th>商品名</th>
                    <th style="width: 80px;">商品種類</th>
                    <th style="width: 80px;">価格</th>
                    <th style="width: 70px;">ファイル</th>
                    <th style="width: 120px;">ショートコード</th>
                    <th style="width: 100px;">Stripe連携</th>
                    <th style="width: 70px;">ステータス</th>
                    <th style="width: 120px;">作成日</th>
                    <th style="width: 100px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo esc_html($product->id); ?></td>
                        <td>
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=sdp-add-product&product_id=' . $product->id); ?>" class="row-title">
                                    <?php echo esc_html($product->name); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <?php 
                            $type_labels = array(
                                'digital' => 'デジタル',
                                'physical' => '物理',
                                'service' => 'サービス',
                            );
                            echo esc_html($type_labels[$product->product_type ?? 'digital'] ?? 'デジタル');
                            ?>
                        </td>
                        <td>¥<?php echo (floor($product->price) == $product->price) ? number_format((int)$product->price) : number_format($product->price, 2); ?></td>
                        <td>
                            <?php 
                            if (!empty($product->file_path)) {
                                $file_extension = strtoupper(pathinfo($product->file_path, PATHINFO_EXTENSION));
                                $color = '#666';
                                if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                    $color = '#9e9e9e';
                                } elseif (strtolower($file_extension) === 'pdf') {
                                    $color = '#d32f2f';
                                } elseif (in_array(strtolower($file_extension), ['zip', 'rar', '7z'])) {
                                    $color = '#7b1fa2';
                                }
                                echo '<span style="display: inline-block; background: ' . esc_attr($color) . '; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold;">' . esc_html($file_extension) . '</span>';
                            } else {
                                echo '<span style="color: #999;">-</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <div style="position: relative;">
                                <input type="text" value="[sdp_product id=&quot;<?php echo esc_attr($product->id); ?>&quot;]" readonly class="sdp-shortcode-input" data-shortcode='[sdp_product id="<?php echo esc_attr($product->id); ?>"]' style="width: 100%; font-size: 11px; padding: 2px 5px; background: #f0f0f1; border: 1px solid #c3c4c7; cursor: pointer; box-sizing: border-box;" title="クリックしてコピー" />
                                <span class="sdp-copy-message" style="display: none; position: absolute; left: 50%; top: -30px; transform: translateX(-50%); background: #00a32a; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px; white-space: nowrap; z-index: 1000;">コピーしました！</span>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($product->stripe_price_id)): ?>
                                <span style="color: green;">✓ 連携済み</span>
                            <?php else: ?>
                                <span style="color: red;">✗ 未連携</span><br>
                                <small style="color: #d63638;">商品を編集して保存してください</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $status_labels = array('active' => '有効', 'inactive' => '無効');
                            echo esc_html($status_labels[$product->status] ?? $product->status);
                            ?>
                        </td>
                        <td><?php echo esc_html(date('Y/m/d H:i', strtotime($product->created_at))); ?></td>
                        <td>
                            <?php if ($current_status === 'trash'): ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=stripe-digital-products&status=trash&action=restore&product_id=' . $product->id), 'sdp_restore_product_' . $product->id); ?>">復元</a> |
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=stripe-digital-products&status=trash&action=delete&product_id=' . $product->id), 'sdp_delete_product_' . $product->id); ?>" onclick="return confirm('完全に削除しますか？この操作は取り消せません。');" style="color: #d63638;">完全削除</a>
                            <?php else: ?>
                                <a href="<?php echo admin_url('admin.php?page=sdp-add-product&product_id=' . $product->id); ?>">編集</a> |
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=stripe-digital-products&status=' . $current_status . '&action=trash&product_id=' . $product->id), 'sdp_trash_product_' . $product->id); ?>" onclick="return confirm('ゴミ箱に移動しますか?');">ゴミ箱へ移動</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('.sdp-shortcode-input').on('click', function() {
        var input = $(this);
        var shortcode = input.data('shortcode');
        var message = input.siblings('.sdp-copy-message');
        
        // クリップボードにコピー
        navigator.clipboard.writeText(shortcode).then(function() {
            // 成功時の視覚フィードバック
            var originalBg = input.css('background-color');
            input.css('background-color', '#d4edda').select();
            
            // メッセージを表示
            message.fadeIn(200);
            
            setTimeout(function() {
                input.css('background-color', originalBg);
                message.fadeOut(200);
            }, 1500);
        }).catch(function(err) {
            // フォールバック: 選択のみ
            input.select();
            alert('ショートコードを選択しました。Ctrl+C (または Cmd+C) でコピーしてください。');
        });
    });
});
</script>
