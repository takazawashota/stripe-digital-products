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

        // フォーム送信前のバリデーション（商品フォームのみ）
        $('form[method="post"]').on('submit', function (e) {
            // 商品保存ボタンがある場合のみバリデーション
            if ($(this).find('input[name="sdp_save_product"]').length === 0) {
                return true; // 商品フォームでなければスキップ
            }

            var filePath = $('#file_path').val();

            // 新規追加または編集中でファイルパスが空の場合
            if (!filePath || filePath.trim() === '') {
                e.preventDefault();
                alert('ファイルをアップロードしてから保存してください。\n\nファイルを削除した場合は、新しいファイルをアップロードする必要があります。');
                return false;
            }
        });

        // ファイルアップロードプレビュー
        $('#file_upload').on('change', function () {
            var fileName = $(this).val().split('\\').pop();
            if (fileName) {
                $('#current_file').text('選択されたファイル: ' + fileName);
            }
        });

        // ファイルアップロード処理
        $('#upload_file_button').on('click', function (e) {
            e.preventDefault();

            // sdp_adminオブジェクトの存在確認
            if (typeof sdp_admin === 'undefined') {
                alert('エラー: 管理画面スクリプトが正しく読み込まれていません');
                console.error('sdp_admin is undefined');
                return;
            }

            var fileInput = $('#file_upload')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                alert('ファイルを選択してください');
                return;
            }

            var button = $(this);
            var originalText = button.text();
            button.text('アップロード中...').prop('disabled', true);

            var formData = new FormData();
            formData.append('action', 'sdp_upload_file');
            formData.append('nonce', sdp_admin.nonce);
            formData.append('file', fileInput.files[0]);

            console.log('Uploading file to:', sdp_admin.ajax_url);

            $.ajax({
                url: sdp_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    console.log('Upload response:', response);
                    if (response.success) {
                        $('#file_path').val(response.data.file_path);
                        var fileName = response.data.filename || response.data.file_path.split('/').pop();
                        $('#current_file').html('<strong style="color: green;">現在のファイル: ' + fileName + '</strong>');

                        // デバッグ表示も更新
                        if ($('#debug_file_path').length > 0) {
                            $('#debug_file_path').text(response.data.file_path);
                        }

                        // 削除ボタンを表示（まだない場合のみ）
                        if ($('#delete_file_button').length === 0) {
                            // URLからproduct_idを取得
                            var urlParams = new URLSearchParams(window.location.search);
                            var productId = urlParams.get('product_id') || 0;

                            $('#upload_file_button').after('<button type="button" class="button button-secondary" id="delete_file_button" data-product-id="' + productId + '" style="color: #b32d2e; margin-left: 5px;">ファイルを削除</button>');
                            attachDeleteHandler();
                        }

                        alert('アップロードが完了しました。\nファイル名: ' + fileName + '\n\n「追加」または「更新」ボタンをクリックして商品を保存してください。');

                        // 隠しフィールドの値を確認
                        console.log('File path set to:', $('#file_path').val());
                    } else {
                        var errorMsg = response.data || 'アップロードに失敗しました';
                        alert('エラー: ' + errorMsg);
                        console.error('Upload failed:', errorMsg);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    alert('通信エラー: ' + error + '\n詳細はコンソールを確認してください');
                },
                complete: function () {
                    button.text(originalText).prop('disabled', false);
                }
            });
        });

        // ファイル削除ハンドラーをアタッチ
        function attachDeleteHandler() {
            $('#delete_file_button').off('click').on('click', function (e) {
                e.preventDefault();

                console.log('Delete button clicked');

                if (!confirm('ファイルを削除しますか？\n\n注意: この操作は元に戻せません。')) {
                    return;
                }

                var filePath = $('#file_path').val();
                var productId = $(this).data('product-id') || 0;
                console.log('File path to delete:', filePath);
                console.log('Product ID:', productId);

                if (!filePath) {
                    alert('削除するファイルがありません');
                    return;
                }

                // sdp_adminオブジェクトの存在確認
                if (typeof sdp_admin === 'undefined') {
                    alert('エラー: 管理画面スクリプトが正しく読み込まれていません');
                    console.error('sdp_admin is undefined');
                    return;
                }

                var button = $(this);
                var originalText = button.text();
                button.text('削除中...').prop('disabled', true);

                console.log('Sending delete request to:', sdp_admin.ajax_url);

                $.ajax({
                    url: sdp_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sdp_delete_file',
                        nonce: sdp_admin.nonce,
                        file_path: filePath,
                        product_id: productId
                    },
                    success: function (response) {
                        console.log('Delete response:', response);
                        if (response.success) {
                            console.log('Delete successful, updating UI...');

                            // 隠しフィールドをクリア
                            $('#file_path').val('');
                            console.log('Hidden field cleared');

                            // 現在のファイル表示を更新
                            var currentFileElement = $('#current_file');
                            console.log('Current file element found:', currentFileElement.length);
                            currentFileElement.html('ファイルがアップロードされていません');
                            console.log('Current file display updated to:', currentFileElement.html());

                            // ファイル入力をリセット
                            $('#file_upload').val('');

                            // 削除ボタンを削除
                            var deleteButton = $('#delete_file_button');
                            console.log('Delete button found:', deleteButton.length);
                            deleteButton.remove();
                            console.log('Delete button removed');

                            alert('ファイルを削除しました。\n\nページがリロードされます。');

                            // ページをリロードして最新の状態を表示
                            location.reload();
                        } else {
                            var errorMsg = response.data || 'ファイルの削除に失敗しました';
                            alert('エラー: ' + errorMsg);
                            console.error('Delete failed:', errorMsg);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Delete AJAX Error:', xhr.responseText);
                        alert('通信エラー: ' + error + '\n詳細はコンソールを確認してください');
                    },
                    complete: function () {
                        if (button.length) {
                            button.text(originalText).prop('disabled', false);
                        }
                    }
                });
            });
        }

        // ページ読み込み時に削除ボタンがあればハンドラーをアタッチ
        if ($('#delete_file_button').length > 0) {
            attachDeleteHandler();
        }
    });

})(jQuery);
