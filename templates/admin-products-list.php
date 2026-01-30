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
                    <th style="width: 40px;">ID</th>
                    <th>商品名</th>
                    <th style="width: 80px;">価格</th>
                    <th style="width: 70px;">ファイル</th>
                    <th style="width: 200px;">ショートコード</th>
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
                            <strong><?php echo esc_html($product->name); ?></strong>
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
                            <a href="<?php echo admin_url('admin.php?page=sdp-add-product&product_id=' . $product->id); ?>">編集</a> |
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=stripe-digital-products&action=delete&product_id=' . $product->id), 'sdp_delete_product_' . $product->id); ?>" onclick="return confirm('本当に削除しますか?');">削除</a>
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
