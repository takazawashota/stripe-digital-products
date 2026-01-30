<?php
if (!defined('ABSPATH')) {
    exit;
}

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$product = null;

if ($product_id) {
    $product = SDP_Products::get_instance()->get_product($product_id);
}

// 保存処理
if (isset($_POST['sdp_save_product'])) {
    check_admin_referer('sdp_product_nonce');
    
    // バリデーション
    $errors = array();
    
    $name = sanitize_text_field($_POST['name']);
    $price = floatval($_POST['price']);
    $file_path = sanitize_text_field($_POST['file_path']);
    
    if (empty($name)) {
        $errors[] = '商品名は必須です';
    }
    
    if ($price <= 0) {
        $errors[] = '価格は0より大きい値を入力してください';
    }
    
    if ($price < 50) {
        $errors[] = '価格は50円以上である必要があります（Stripeの最小金額制限）';
    }
    
    if (empty($file_path)) {
        $errors[] = 'ファイルをアップロードしてください';
    }
    
    if (empty($errors)) {
        $data = array(
            'id' => $product_id,
            'name' => $name,
            'description' => wp_kses_post($_POST['description']),
            'price' => $price,
            'file_path' => $file_path,
            'status' => sanitize_text_field($_POST['status']),
        );
        
        // デバッグ: 保存しようとしているデータをログに記録
        error_log('SDP Save Product: Attempting to save - Product ID: ' . $product_id . ', File Path: ' . $file_path);
        
        $saved_id = SDP_Products::get_instance()->save_product($data);
        
        if ($saved_id) {
            error_log('SDP Save Product: Successfully saved - ID: ' . $saved_id);
            
            // Stripe商品を作成
            $stripe_result = SDP_Products::get_instance()->create_stripe_product($saved_id);
            
            if ($stripe_result) {
                echo '<div class="notice notice-success"><p>商品を保存しました。</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>商品は保存されましたが、Stripe連携でエラーが発生しました。Stripe APIキーが正しく設定されているか確認してください。</p></div>';
            }
            
            if (!$product_id) {
                $product_id = $saved_id;
            }
            
            // 必ず最新データを再取得
            $product = SDP_Products::get_instance()->get_product($product_id ? $product_id : $saved_id);
            error_log('SDP Save Product: Reloaded product - File Path: ' . ($product->file_path ?? 'NULL'));
        } else {
            $errors[] = 'データベースへの保存に失敗しました';
        }
    }
    
    if (!empty($errors)) {
        echo '<div class="notice notice-error"><p>';
        echo implode('<br>', array_map('esc_html', $errors));
        echo '</p></div>';
    }
}
?>

<div class="wrap">
    <h1><?php echo $product_id ? '商品を編集' : '新規商品を追加'; ?></h1>
    
    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('sdp_product_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="name">商品名 <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" name="name" id="name" value="<?php echo esc_attr($product->name ?? ''); ?>" class="regular-text" required />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="description">説明</label>
                </th>
                <td>
                    <?php
                    wp_editor(
                        $product->description ?? '',
                        'description',
                        array(
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                        )
                    );
                    ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="price">価格 <span class="required">*</span></label>
                </th>
                <td>
                    <?php
                    $display_price = '';
                    if (isset($product->price)) {
                        // 小数点以下がゼロの場合は整数で表示
                        $display_price = (floor($product->price) == $product->price) ? (int)$product->price : $product->price;
                    }
                    ?>
                    <input type="number" name="price" id="price" value="<?php echo esc_attr($display_price); ?>" step="1" min="50" required />
                    <p class="description">
                        円単位で入力してください<br>
                        <strong style="color: #d63638;">※ Stripeの制限により、最小金額は50円です</strong>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="file">ファイル <span class="required">*</span></label>
                </th>
                <td>
                    <input type="hidden" name="file_path" id="file_path" value="<?php echo esc_attr($product->file_path ?? ''); ?>" />
                    <div class="file-upload-controls">
                        <input type="file" id="file_upload" />
                        <button type="button" class="button" id="upload_file_button">アップロード</button>
                        <?php if (!empty($product->file_path)): ?>
                            <button type="button" class="button button-secondary" id="delete_file_button" data-product-id="<?php echo esc_attr($product_id); ?>" style="color: #b32d2e;">ファイルを削除</button>
                        <?php endif; ?>
                    </div>
                    <p class="description" id="current_file">
                        <?php if (!empty($product->file_path)): ?>
                            現在のファイル: <strong><?php echo esc_html(basename($product->file_path)); ?></strong>
                        <?php else: ?>
                            ファイルがアップロードされていません
                        <?php endif; ?>
                    </p>
                    <p class="description">
                        <small>保存場所: <?php echo esc_html(wp_upload_dir()['basedir']); ?>/sdp-products/</small>
                    </p>
                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    <p class="description" style="color: #666; font-size: 11px;">
                        <small>デバッグ: DB値 = <?php echo esc_html($product->file_path ?? '(空)'); ?></small><br>
                        <small>デバッグ: 隠しフィールド = <span id="debug_file_path"><?php echo esc_html($product->file_path ?? '(空)'); ?></span></small>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="status">ステータス</label>
                </th>
                <td>
                    <select name="status" id="status">
                        <option value="active" <?php selected($product->status ?? 'active', 'active'); ?>>有効</option>
                        <option value="inactive" <?php selected($product->status ?? 'active', 'inactive'); ?>>無効</option>
                    </select>
                </td>
            </tr>
            
            <?php if ($product_id): ?>
            <tr>
                <th scope="row">
                    <label>Stripe連携状態</label>
                </th>
                <td>
                    <?php if (!empty($product->stripe_price_id)): ?>
                        <p><span style="color: green; font-weight: bold;">✓ Stripeに連携済み</span></p>
                        <p class="description">
                            Stripe Product ID: <code><?php echo esc_html($product->stripe_product_id); ?></code><br>
                            Stripe Price ID: <code><?php echo esc_html($product->stripe_price_id); ?></code>
                        </p>
                    <?php else: ?>
                        <p><span style="color: red; font-weight: bold;">✗ Stripeに未連携</span></p>
                        <p class="description" style="color: #d63638;">
                            この商品は購入できません。「更新」ボタンをクリックしてStripeに連携してください。<br>
                            ※Stripe APIキーが設定されていることを確認してください。
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <p class="submit">
            <input type="submit" name="sdp_save_product" class="button button-primary" value="<?php echo $product_id ? '更新' : '追加'; ?>" />
            <a href="<?php echo admin_url('admin.php?page=stripe-digital-products'); ?>" class="button">キャンセル</a>
        </p>
    </form>
</div>
