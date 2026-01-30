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
    $file_path = $product->file_path ?? ''; // 既存のファイルパスを保持
    $image_url = sanitize_text_field($_POST['image_url'] ?? '');
    
    // 新しいファイルがアップロードされた場合
    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = wp_upload_dir();
        $sdp_dir = $upload_dir['basedir'] . '/sdp-products';
        
        // ディレクトリが存在しない場合は作成
        if (!file_exists($sdp_dir)) {
            wp_mkdir_p($sdp_dir);
        }
        
        $file_name = sanitize_file_name($_FILES['file_upload']['name']);
        $timestamp = current_time('YmdHis');
        $file_name = $timestamp . '_' . $file_name;
        $file_path_full = $sdp_dir . '/' . $file_name;
        
        // ファイルを移動
        if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $file_path_full)) {
            // 古いファイルを削除（編集時）
            if ($product_id && !empty($product->file_path)) {
                $old_file = $upload_dir['basedir'] . '/' . $product->file_path;
                if (file_exists($old_file)) {
                    @unlink($old_file);
                }
            }
            $file_path = 'sdp-products/' . $file_name;
        } else {
            $errors[] = 'ファイルのアップロードに失敗しました';
        }
    }
    
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
            'image_url' => $image_url,
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
                    <label for="image">商品画像</label>
                </th>
                <td>
                    <input type="hidden" name="image_url" id="image_url" value="<?php echo esc_attr($product->image_url ?? ''); ?>" />
                    <div id="image_preview" style="margin-bottom: 10px;">
                        <?php if (!empty($product->image_url)): ?>
                            <img src="<?php echo esc_url($product->image_url); ?>" style="max-width: 300px; height: auto; border: 1px solid #ddd; padding: 5px;" />
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button" id="upload_image_button">画像を選択</button>
                    <?php if (!empty($product->image_url)): ?>
                        <button type="button" class="button" id="remove_image_button">画像を削除</button>
                    <?php endif; ?>
                    <p class="description">推奨サイズ: 800x800px</p>
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
                    <label for="file_upload">ファイル <span class="required">*</span></label>
                </th>
                <td>
                    <?php if (!empty($product->file_path)): 
                        $file_extension = strtolower(pathinfo($product->file_path, PATHINFO_EXTENSION));
                    ?>
                    <div id="file_preview" style="margin-bottom: 15px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px; max-width: 400px;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="flex-shrink: 0;">
                                <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                    <div style="width: 80px; height: 80px; background: #9e9e9e; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 20px;">IMG</div>
                                <?php elseif ($file_extension === 'pdf'): ?>
                                    <div style="width: 80px; height: 80px; background: #d32f2f; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px;">PDF</div>
                                <?php elseif (in_array($file_extension, ['zip', 'rar', '7z'])): ?>
                                    <div style="width: 80px; height: 80px; background: #7b1fa2; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 20px;">ZIP</div>
                                <?php else: ?>
                                    <div style="width: 80px; height: 80px; background: #455a64; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;"><?php echo strtoupper($file_extension); ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: bold; margin-bottom: 5px; word-break: break-all;"><?php echo esc_html(basename($product->file_path)); ?></div>
                                <div style="font-size: 12px; color: #666;"><?php echo strtoupper($file_extension); ?> ファイル</div>
                                <button type="button" class="button button-small" id="admin_preview_file" data-file-path="<?php echo esc_attr($product->file_path); ?>" style="margin-top: 8px; font-size: 12px;">
                                    商品プレビュー
                                </button>
                            </div>
                        </div>
                    </div>
                    <p class="description" style="margin-bottom: 10px;">
                        <strong style="color: #0a7d0a;">現在のファイル: <?php echo esc_html(basename($product->file_path)); ?></strong>
                    </p>
                    <?php endif; ?>
                    
                    <input type="file" name="file_upload" id="file_upload" />
                    <p class="description">
                        <?php if (!empty($product->file_path)): ?>
                            新しいファイルを選択すると、現在のファイルと置き換わります。
                        <?php else: ?>
                            販売するデジタルコンテンツファイルを選択してください。
                        <?php endif; ?>
                    </p>
                    <p class="description">
                        <small>保存場所: <?php echo esc_html(wp_upload_dir()['basedir']); ?>/sdp-products/</small>
                    </p>
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
