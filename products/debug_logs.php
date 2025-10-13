<?php
// แสดง PHP error log

echo "<h1>PHP Error Log Viewer</h1>";

// ตรวจสอบ error log ของ PHP
$errorLogPath = ini_get('error_log');
echo "<h2>PHP Error Log Path: " . ($errorLogPath ?: 'Default system log') . "</h2>";

// ตรวจสอบ log files ที่เป็นไปได้
$possibleLogFiles = [
    'php_errors.log',
    'error.log',
    'error_log',
    $_SERVER['DOCUMENT_ROOT'] . '/error.log',
    $_SERVER['DOCUMENT_ROOT'] . '/php_errors.log',
    '/tmp/php_errors.log',
    'c:/xampp/apache/logs/error.log',
    'c:/wamp/logs/php_error.log'
];

echo "<h2>Checking for log files:</h2>";
foreach ($possibleLogFiles as $logFile) {
    if (file_exists($logFile) && is_readable($logFile)) {
        echo "<h3>Found: $logFile</h3>";
        echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto;'>";
        echo "<pre>" . htmlspecialchars(tail($logFile, 50)) . "</pre>";
        echo "</div>";
    } else {
        echo "<p>Not found or not readable: $logFile</p>";
    }
}

// ฟังก์ชันอ่านท้ายไฟล์
function tail($filename, $lines = 10) {
    if (!file_exists($filename)) {
        return "File not found";
    }
    
    $data = file($filename);
    return implode("", array_slice($data, -$lines));
}

// แสดงข้อมูล PHP configuration
echo "<h2>PHP Configuration</h2>";
echo "<table border='1'>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>log_errors</td><td>" . ini_get('log_errors') . "</td></tr>";
echo "<tr><td>error_reporting</td><td>" . error_reporting() . "</td></tr>";
echo "<tr><td>display_errors</td><td>" . ini_get('display_errors') . "</td></tr>";
echo "<tr><td>error_log</td><td>" . ini_get('error_log') . "</td></tr>";
echo "</table>";

// ทดสอบการเขียน log
echo "<h2>Testing Error Logging</h2>";
$testMessage = "Test log message at " . date('Y-m-d H:i:s');
if (error_log($testMessage)) {
    echo "<p style='color: green'>✅ Error logging is working</p>";
    echo "<p>Test message: $testMessage</p>";
} else {
    echo "<p style='color: red'>❌ Error logging failed</p>";
}

// แสดง recent logs ถ้าเป็น AJAX request
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $logs = [];
    
    foreach ($possibleLogFiles as $logFile) {
        if (file_exists($logFile) && is_readable($logFile)) {
            $logs[$logFile] = tail($logFile, 20);
        }
    }
    
    echo json_encode(['logs' => $logs, 'timestamp' => time()]);
    exit;
}
?>

<script>
// Auto-refresh logs every 5 seconds
setInterval(function() {
    fetch('?ajax=1')
        .then(response => response.json())
        .then(data => {
            console.log('Updated logs:', data);
        });
}, 5000);
</script>

<p><a href="../products/import_product.php">กลับไปหน้าเพิ่มสินค้า</a></p>