/* 管理画面 JavaScript */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // 商品削除の確認
        $('.delete-product').on('click', function (e) {
            if (!confirm('本当に削除しますか?')) {
                e.preventDefault();
            }
        });

        // WordPress メディアアップローダー（画像用）
        var imageUploader;
        $('#upload_image_button').on('click', function (e) {
            e.preventDefault();

            // メディアアップローダーが既に存在する場合は開く
            if (imageUploader) {
                imageUploader.open();
                return;
            }

            // メディアアップローダーを作成
            imageUploader = wp.media({
                title: '商品画像を選択',
                button: {
                    text: '画像を使用'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // 画像が選択されたとき
            imageUploader.on('select', function () {
                var attachment = imageUploader.state().get('selection').first().toJSON();
                console.log('Image selected:', attachment);

                // 画像URLをフィールドに設定
                $('#image_url').val(attachment.url);

                // プレビューを更新
                $('#image_preview').html(
                    '<img src="' + attachment.url + '" style="max-width: 300px; height: auto; border: 1px solid #ddd; padding: 5px;" />'
                );

                // 削除ボタンを表示（まだない場合）
                if ($('#remove_image_button').length === 0) {
                    $('#upload_image_button').after('<button type="button" class="button" id="remove_image_button">画像を削除</button>');
                }
            });

            imageUploader.open();
        });

        // 画像削除ボタン
        $(document).on('click', '#remove_image_button', function (e) {
            e.preventDefault();

            if (confirm('画像を削除しますか？')) {
                $('#image_url').val('');
                $('#image_preview').html('');
                $(this).remove();
            }
        });

        // 管理者用ファイルプレビュー/ダウンロード
        $(document).on('click', '#admin_preview_file, .admin-preview-file-btn', function (e) {
            e.preventDefault();

            var filePath = $(this).data('file-path');

            if (!filePath) {
                alert('ファイルパスが取得できませんでした');
                return;
            }

            var button = $(this);
            var originalText = button.html();
            button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear;"></span> 処理中...');

            $.ajax({
                url: sdp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'sdp_admin_preview_file',
                    nonce: sdp_admin.nonce,
                    file_path: filePath
                },
                success: function (response) {
                    if (response.success) {
                        // 新しいタブでプレビュー表示
                        window.open(response.data.preview_url, '_blank');
                    } else {
                        alert('エラー: ' + (response.data || '不明なエラー'));
                    }
                },
                error: function (xhr, status, error) {
                    alert('通信エラー: ' + error);
                },
                complete: function () {
                    button.prop('disabled', false).html(originalText);
                }
            });
        });
    });

})(jQuery);
