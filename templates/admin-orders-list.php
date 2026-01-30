<?php
if (!defined('ABSPATH')) {
    exit;
}

$orders = SDP_Download::get_instance()->get_all_orders(100);
?>

<div class="wrap">
    <h1>注文一覧</h1>
    
    <?php if (empty($orders)): ?>
        <p>注文がありません。</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>注文日</th>
                    <th>商品名</th>
                    <th>顧客名</th>
                    <th>メールアドレス</th>
                    <th>金額</th>
                    <th>ステータス</th>
                    <th>ダウンロード回数</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo esc_html($order->id); ?></td>
                        <td><?php echo esc_html(date('Y/m/d H:i', strtotime($order->created_at))); ?></td>
                        <td><?php echo esc_html($order->product_name); ?></td>
                        <td><?php echo esc_html($order->customer_name); ?></td>
                        <td><?php echo esc_html($order->customer_email); ?></td>
                        <td>¥<?php echo number_format($order->amount); ?></td>
                        <td>
                            <?php 
                            $status_labels = array(
                                'pending' => '保留中',
                                'completed' => '完了',
                                'failed' => '失敗',
                            );
                            $status = $status_labels[$order->status] ?? $order->status;
                            $status_class = $order->status === 'completed' ? 'status-completed' : 'status-pending';
                            ?>
                            <span class="<?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($status); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html($order->download_count); ?> / <?php echo esc_html($order->download_limit); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
