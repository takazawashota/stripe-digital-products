<?php
/**
 * フロントエンド表示クラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDP_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('sdp_products', array($this, 'render_products_shortcode'));
        add_shortcode('sdp_product', array($this, 'render_product_shortcode'));
        add_shortcode('sdp_my_orders', array($this, 'render_my_orders_shortcode'));
    }
    
    /**
     * 商品一覧ショートコード
     * 使用方法: [sdp_products]
     */
    public function render_products_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => -1,
            'columns' => 3,
        ), $atts);
        
        $products = SDP_Products::get_instance()->get_all_products('active');
        
        if (empty($products)) {
            return '<p>現在販売中の商品はありません。</p>';
        }
        
        ob_start();
        ?>
        <div class="sdp-products-grid" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <?php foreach ($products as $product): ?>
                <div class="sdp-product-card">
                    <?php if (!empty($product->image_url)): ?>
                        <div class="sdp-product-image">
                            <img src="<?php echo esc_url($product->image_url); ?>" alt="<?php echo esc_attr($product->name); ?>" />
                        </div>
                    <?php endif; ?>
                    <div class="sdp-product-content">
                        <h3 class="sdp-product-title"><?php echo esc_html($product->name); ?></h3>
                        <div class="sdp-product-description">
                            <?php echo wp_kses_post($product->description); ?>
                        </div>
                        <div class="sdp-product-price">
                            ¥<?php echo (floor($product->price) == $product->price) ? number_format((int)$product->price) : number_format($product->price, 2); ?>
                        </div>
                        <button class="sdp-buy-button" data-product-id="<?php echo esc_attr($product->id); ?>">
                            購入する
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 単一商品ショートコード
     * 使用方法: [sdp_product id="1"]
     */
    public function render_product_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts);
        
        $product_id = intval($atts['id']);
        
        if (!$product_id) {
            return '<p>商品IDを指定してください。</p>';
        }
        
        $product = SDP_Products::get_instance()->get_product($product_id);
        
        if (!$product || $product->status !== 'active') {
            return '<p>商品が見つかりません。</p>';
        }
        
        ob_start();
        ?>
        <div class="sdp-single-product">
            <h2 class="sdp-product-title"><?php echo esc_html($product->name); ?></h2>
            <?php if (!empty($product->image_url)): ?>
                <div class="sdp-product-image">
                    <img src="<?php echo esc_url($product->image_url); ?>" alt="<?php echo esc_attr($product->name); ?>" />
                </div>
            <?php endif; ?>
            <div class="sdp-product-description">
                <?php echo wp_kses_post($product->description); ?>
            </div>
            <div class="sdp-product-price">
                ¥<?php echo (floor($product->price) == $product->price) ? number_format((int)$product->price) : number_format($product->price, 2); ?>
            </div>
            <button class="sdp-buy-button" data-product-id="<?php echo esc_attr($product->id); ?>">
                購入する
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * マイ注文一覧ショートコード
     * 使用方法: [sdp_my_orders]
     */
    public function render_my_orders_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>注文履歴を表示するにはログインしてください。</p>';
        }
        
        $current_user = wp_get_current_user();
        $orders = SDP_Download::get_instance()->get_orders_by_email($current_user->user_email);
        
        if (empty($orders)) {
            return '<p>注文履歴がありません。</p>';
        }
        
        ob_start();
        ?>
        <div class="sdp-my-orders">
            <h2>注文履歴</h2>
            <table class="sdp-orders-table">
                <thead>
                    <tr>
                        <th>注文日</th>
                        <th>商品名</th>
                        <th>金額</th>
                        <th>ステータス</th>
                        <th>ダウンロード</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo esc_html(date('Y年m月d日', strtotime($order->created_at))); ?></td>
                            <td><?php echo esc_html($order->product_name); ?></td>
                            <td>¥<?php echo number_format($order->amount); ?></td>
                            <td>
                                <?php 
                                $status_labels = array(
                                    'pending' => '保留中',
                                    'completed' => '完了',
                                    'failed' => '失敗',
                                );
                                echo esc_html($status_labels[$order->status] ?? $order->status);
                                ?>
                            </td>
                            <td>
                                <?php if ($order->status === 'completed'): ?>
                                    <?php if ($order->download_count < $order->download_limit): ?>
                                        <a href="<?php echo esc_url(add_query_arg('sdp_download', $order->download_token, home_url('/'))); ?>" class="sdp-download-link">
                                            ダウンロード (<?php echo esc_html($order->download_count); ?>/<?php echo esc_html($order->download_limit); ?>)
                                        </a>
                                    <?php else: ?>
                                        <span class="sdp-download-expired">制限に達しました</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
