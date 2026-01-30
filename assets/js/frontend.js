/* フロントエンド JavaScript */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // 購入ボタンのクリックイベント
        $('.sdp-buy-button').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var productId = $button.data('product-id');

            console.log('Purchase button clicked - Product ID:', productId);

            if (!productId) {
                alert('エラー: 商品IDが取得できませんでした');
                return;
            }

            // ボタンを無効化
            $button.prop('disabled', true).text('処理中...');

            // ローディング表示
            showLoading();

            console.log('Creating checkout session for product:', productId);

            // Checkout セッションを作成
            $.ajax({
                url: sdp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sdp_create_checkout_session',
                    nonce: sdp_ajax.nonce,
                    product_id: productId
                },
                success: function (response) {
                    console.log('Checkout response:', response);
                    hideLoading();

                    if (response.success) {
                        console.log('Redirecting to Stripe Checkout:', response.data.url);
                        // Stripe Checkoutページにリダイレクト
                        window.location.href = response.data.url;
                    } else {
                        var errorMsg = response.data || 'セッションの作成に失敗しました';
                        alert('エラーが発生しました: ' + errorMsg);
                        console.error('Checkout error:', errorMsg);
                        $button.prop('disabled', false).text('購入する');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX error:', xhr.responseText);
                    hideLoading();
                    alert('通信エラーが発生しました: ' + error);
                    $button.prop('disabled', false).text('購入する');
                }
            });
        });

        // 決済完了後の処理
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('session_id')) {
            // 決済完了メッセージを表示
            var message = $('<div class="sdp-success-message">')
                .css({
                    'background': '#46b450',
                    'color': '#fff',
                    'padding': '15px',
                    'border-radius': '4px',
                    'margin': '20px 0',
                    'text-align': 'center'
                })
                .text('決済が完了しました。ダウンロードリンクをメールでお送りしました。');

            $('.sdp-products-grid, .sdp-single-product').before(message);
        }
    });

    function showLoading() {
        var $loading = $('<div class="sdp-loading">')
            .append('<div class="sdp-loading-spinner"></div>');
        $('body').append($loading);
    }

    function hideLoading() {
        $('.sdp-loading').remove();
    }

})(jQuery);
