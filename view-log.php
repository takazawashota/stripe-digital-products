<?php
/**
 * デバッグログビューワー
 * ブラウザで直接アクセス: http://localhost/wp-content/plugins/stripe-digital-products/view-log.php
 */

$log_file = __DIR__ . '/debug.log';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SDP Debug Log</title>
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        .log-container {
            background: #252526;
            border: 1px solid #3c3c3c;
            border-radius: 5px;
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .log-line {
            margin: 5px 0;
            line-height: 1.6;
        }
        .timestamp {
            color: #569cd6;
        }
        .error {
            color: #f48771;
        }
        .success {
            color: #4ec9b0;
        }
        .info {
            color: #dcdcaa;
        }
        .clear-btn {
            background: #f48771;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .clear-btn:hover {
            background: #d16d5d;
        }
        .empty {
            color: #858585;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>Stripe Digital Products - Debug Log</h1>
    
    <?php if (isset($_GET['clear']) && $_GET['clear'] === '1'): ?>
        <?php
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            echo '<p style="color: #4ec9b0;">✓ ログをクリアしました</p>';
        }
        ?>
    <?php endif; ?>
    
    <form method="get">
        <button type="submit" name="clear" value="1" class="clear-btn">ログをクリア</button>
    </form>
    
    <div class="log-container">
        <?php
        if (file_exists($log_file)) {
            $content = file_get_contents($log_file);
            if (empty(trim($content))) {
                echo '<div class="empty">ログが記録されていません。テスト購入を実行してください。</div>';
            } else {
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    
                    $class = 'log-line';
                    if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false) {
                        $class .= ' error';
                    } elseif (stripos($line, 'success') !== false) {
                        $class .= ' success';
                    } elseif (preg_match('/^\[.*?\]/', $line)) {
                        $class .= ' info';
                    }
                    
                    // タイムスタンプを強調
                    $line = preg_replace('/^\[(.*?)\]/', '<span class="timestamp">[$1]</span>', htmlspecialchars($line));
                    
                    echo '<div class="' . $class . '">' . $line . '</div>';
                }
            }
        } else {
            echo '<div class="empty">ログファイルが存在しません。初回のテスト購入を実行してください。</div>';
        }
        ?>
    </div>
    
    <p style="margin-top: 20px; color: #858585;">
        ログファイル: <?php echo htmlspecialchars($log_file); ?><br>
        最終更新: <?php echo file_exists($log_file) ? date('Y-m-d H:i:s', filemtime($log_file)) : 'N/A'; ?>
    </p>
</body>
</html>
