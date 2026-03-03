<?php
echo "<h2>ตรวจสอบ Error Logs</h2>";

// ดู error log file location
$errorLogPath = ini_get('error_log');
echo "<p><strong>Error log path:</strong> " . ($errorLogPath ?: 'php://stderr or default') . "</p>";

// ถ้ามี log file อ่านบรรทัดล่าสุด 100 บรรทัด
if ($errorLogPath && file_exists($errorLogPath)) {
    echo "<h3>Error Log (100 บรรทัดล่าสุด)</h3>";
    $lines = file($errorLogPath);
    $recentLines = array_slice($lines, -100);
    echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto; max-height: 600px;'>";
    echo htmlspecialchars(implode('', $recentLines));
    echo "</pre>";
} else {
    echo "<p>ไม่พบ error log file ใน path ที่กำหนด</p>";
    
    // ลองหาใน common paths
    $commonPaths = [
        'C:/xampp/apache/logs/error.log',
        'C:/xampp/php/logs/php_error_log',
        '/var/log/apache2/error.log',
        '/var/log/php_errors.log',
        __DIR__ . '/logs/error.log',
        __DIR__ . '/php_error.log'
    ];
    
    echo "<h3>ค้นหา log files ใน common paths:</h3><ul>";
    foreach ($commonPaths as $path) {
        if (file_exists($path)) {
            echo "<li>✅ พบ: $path</li>";
            echo "<p><strong>50 บรรทัดล่าสุด:</strong></p>";
            $lines = file($path);
            $recentLines = array_slice($lines, -50);
            echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto; max-height: 400px;'>";
            echo htmlspecialchars(implode('', $recentLines));
            echo "</pre>";
        } else {
            echo "<li>❌ ไม่พบ: $path</li>";
        }
    }
    echo "</ul>";
}

// แสดง Apache error log
echo "<h3>Apache Error Log</h3>";
$apacheLogPath = 'C:/xampp/apache/logs/error.log';
if (file_exists($apacheLogPath)) {
    $lines = file($apacheLogPath);
    $recentLines = array_slice($lines, -50);
    echo "<pre style='background: #fff3cd; padding: 10px; overflow: auto; max-height: 400px;'>";
    echo htmlspecialchars(implode('', $recentLines));
    echo "</pre>";
} else {
    echo "<p>ไม่พบ Apache error log ที่: $apacheLogPath</p>";
}

// แสดง PHP error log
echo "<h3>PHP Error Log</h3>";
$phpLogPath = 'C:/xampp/php/logs/php_error_log';
if (file_exists($phpLogPath)) {
    $lines = file($phpLogPath);
    $recentLines = array_slice($lines, -50);
    echo "<pre style='background: #d1ecf1; padding: 10px; overflow: auto; max-height: 400px;'>";
    echo htmlspecialchars(implode('', $recentLines));
    echo "</pre>";
} else {
    echo "<p>ไม่พบ PHP error log ที่: $phpLogPath</p>";
}
