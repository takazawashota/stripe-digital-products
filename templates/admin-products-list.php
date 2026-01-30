<?php
if (!defined('ABSPATH')) {
    exit;
}

$products = SDP_Products::get_instance()->get_all_products(null);

// 削除処理
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['product_id'])) {
    check_admin_referer('sdp_delete_product_' . $_GET['product_id']);
    SDP_Products::get_instance()->delete_product($_GET['product_id']);
    echo '<div class="notice notice-success"><p>商品を削除しました。</p></div>';
    $products = SDP_Products::get_instance()->get_all_products(null);
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">商品一覧</h1>
    <a href="<?php echo admin_url('admin.php?page=sdp-add-product'); ?>" class="page-title-action">新規追加</a>
    
    <hr class="wp-header-end">
    
    <?php if (empty($products)): ?>
        <p>商品が登録されていません。</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>商品名</th>
                    <th>価格</th>
                    <th>Stripe連携</th>
                    <th>ステータス</th>
                    <th>作成日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo esc_html($product->id); ?></td>
                        <td>
                            <strong><?php echo esc_html($product->name); ?></strong>
                        </td>
                        <td>¥<?php echo (floor($product->price) == $product->price) ? number_format((int)$product->price) : number_format($product->price, 2); ?></td>
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
                            <a href="<?php echo admin_url('admin.php?page=sdp-add-product&product_id=' . $product->id); ?>">編集</a> |
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=stripe-digital-products&action=delete&product_id=' . $product->id), 'sdp_delete_product_' . $product->id); ?>" onclick="return confirm('本当に削除しますか?');">削除</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
