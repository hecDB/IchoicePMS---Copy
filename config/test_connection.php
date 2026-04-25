<?php
/**
 * ทดสอบการเชื่อมต่อฐานข้อมูล
 * เปิดไฟล์นี้ในเบราว์เซอร์เพื่อตรวจสอบ connection
 */

header('Content-Type: text/html; charset=utf-8');

// โหลด env_loader
require_once __DIR__ . '/env_loader.php';

echo "<!DOCTYPE html>
<html lang='th'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Connection Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .icon { font-size: 2rem; }
        .section {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #667eea;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row:last-child { border-bottom: none; }
        .label { font-weight: 600; color: #64748b; }
        .value { color: #1e293b; font-family: monospace; }
        .success {
            background: #d1fae5;
            border-left-color: #10b981;
            color: #065f46;
        }
        .error {
            background: #fee2e2;
            border-left-color: #ef4444;
            color: #991b1b;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .badge-success {
            background: #10b981;
            color: white;
        }
        .badge-error {
            background: #ef4444;
            color: white;
        }
        .performance {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .metric {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .metric-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        .metric-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.5rem;
        }
        code {
            background: #1e293b;
            color: #10b981;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class='container'>";

// Start timing
$start_time = microtime(true);

echo "<h1><span class='icon'>🔌</span> Database Connection Test</h1>";

// Environment Info
echo "<div class='section'>
    <h2>📋 Environment Configuration</h2>
    <div class='info-row'>
        <span class='label'>Host:</span>
        <span class='value'>" . (getenv('DB_HOST') ?: '127.0.0.1') . "</span>
    </div>
    <div class='info-row'>
        <span class='label'>Port:</span>
        <span class='value'>" . (getenv('DB_PORT') ?: '3306') . "</span>
    </div>
    <div class='info-row'>
        <span class='label'>Database:</span>
        <span class='value'>" . (getenv('DB_NAME') ?: 'ichoice_') . "</span>
    </div>
    <div class='info-row'>
        <span class='label'>User:</span>
        <span class='value'>" . (getenv('DB_USER') ?: 'root') . "</span>
    </div>
    <div class='info-row'>
        <span class='label'>Environment:</span>
        <span class='value'>" . (getenv('APP_ENV') ?: 'development') . "</span>
    </div>
</div>";

// Test Connection
try {
    $pdo = require __DIR__ . '/db_connect.php';
    
    $connection_time = round((microtime(true) - $start_time) * 1000, 2);
    
    echo "<div class='section success'>
        <h2>✅ Connection Successful</h2>
        <p>เชื่อมต่อฐานข้อมูลสำเร็จ</p>
    </div>";
    
    // Test Query
    $query_start = microtime(true);
    $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as database");
    $result = $stmt->fetch();
    $query_time = round((microtime(true) - $query_start) * 1000, 2);
    
    echo "<div class='section'>
        <h2>🗄️ Database Information</h2>
        <div class='info-row'>
            <span class='label'>MySQL Version:</span>
            <span class='value'>" . $result['version'] . "</span>
        </div>
        <div class='info-row'>
            <span class='label'>Current Database:</span>
            <span class='value'>" . $result['database'] . "</span>
        </div>
    </div>";
    
    // Performance Metrics
    echo "<div class='section'>
        <h2>⚡ Performance Metrics</h2>
        <div class='performance'>
            <div class='metric'>
                <div class='metric-value'>{$connection_time}ms</div>
                <div class='metric-label'>Connection Time</div>
            </div>
            <div class='metric'>
                <div class='metric-value'>{$query_time}ms</div>
                <div class='metric-label'>Query Time</div>
            </div>
            <div class='metric'>
                <div class='metric-value'>" . round($connection_time + $query_time, 2) . "ms</div>
                <div class='metric-label'>Total Time</div>
            </div>
        </div>
    </div>";
    
    // Connection Attributes
    $persistent = $pdo->getAttribute(PDO::ATTR_PERSISTENT) ? 'Yes ✅' : 'No ❌';
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    echo "<div class='section'>
        <h2>🔧 PDO Configuration</h2>
        <div class='info-row'>
            <span class='label'>Driver:</span>
            <span class='value'>{$driver}</span>
        </div>
        <div class='info-row'>
            <span class='label'>Persistent Connection:</span>
            <span class='value'>{$persistent}</span>
        </div>
        <div class='info-row'>
            <span class='label'>Emulate Prepares:</span>
            <span class='value'>" . ($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) ? 'Yes' : 'No ✅') . "</span>
        </div>
        <div class='info-row'>
            <span class='label'>Error Mode:</span>
            <span class='value'>" . ($pdo->getAttribute(PDO::ATTR_ERRMODE) == PDO::ERRMODE_EXCEPTION ? 'Exception ✅' : 'Other') . "</span>
        </div>
    </div>";
    
    // Performance Assessment
    if ($connection_time < 10) {
        $perf_class = 'success';
        $perf_icon = '🚀';
        $perf_msg = 'Excellent! Connection time is very fast.';
    } elseif ($connection_time < 50) {
        $perf_class = 'section';
        $perf_icon = '✅';
        $perf_msg = 'Good connection time.';
    } else {
        $perf_class = 'error';
        $perf_icon = '⚠️';
        $perf_msg = 'Connection time is slow. Consider checking network or database server.';
    }
    
    echo "<div class='{$perf_class}'>
        <h2>{$perf_icon} Performance Assessment</h2>
        <p>{$perf_msg}</p>
    </div>";
    
    // Recommendations
    echo "<div class='section'>
        <h2>💡 Recommendations</h2>
        <ul style='padding-left: 1.5rem; line-height: 1.8;'>
            " . ($persistent == 'No ❌' ? "<li>❌ Persistent connection is disabled. Enable it for better performance.</li>" : "<li>✅ Persistent connection is enabled.</li>") . "
            <li>✅ Using real prepared statements (not emulated).</li>
            <li>✅ Exception error mode enabled.</li>
            <li>💡 Current total time: " . round($connection_time + $query_time, 2) . "ms</li>
        </ul>
    </div>";
    
} catch (PDOException $e) {
    $connection_time = round((microtime(true) - $start_time) * 1000, 2);
    
    echo "<div class='section error'>
        <h2>❌ Connection Failed</h2>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p style='margin-top: 1rem;'><strong>Time to fail:</strong> {$connection_time}ms</p>
    </div>";
    
    echo "<div class='section'>
        <h2>🔧 Troubleshooting</h2>
        <ul style='padding-left: 1.5rem; line-height: 1.8;'>
            <li>ตรวจสอบว่า MySQL/MariaDB ทำงานอยู่</li>
            <li>ตรวจสอบ port ที่ใช้: XAMPP (3306), MAMP (8889)</li>
            <li>ตรวจสอบ username และ password</li>
            <li>สร้างไฟล์ <code>.env</code> ใน folder config/ ถ้าต้องการเปลี่ยนค่า default</li>
        </ul>
    </div>";
}

$total_time = round((microtime(true) - $start_time) * 1000, 2);

echo "<div style='text-align: center; margin-top: 2rem; color: #64748b; font-size: 0.875rem;'>
    <p>Total Script Execution Time: {$total_time}ms</p>
    <p style='margin-top: 0.5rem;'>Generated: " . date('Y-m-d H:i:s') . "</p>
</div>";

echo "</div></body></html>";
?>
