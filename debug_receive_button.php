<?php
/**
 * Interactive Debug Helper for Receive Button Issues
 * ไฟล์ช่วยในการแก้ไขปัญหาปุ่ม "รับสินค้า"
 */

session_start();
require 'config/db_connect.php';

$test_results = [];
$po_id = isset($_GET['po_id']) ? (int)$_GET['po_id'] : 1;

// Test 1: Database connection
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM purchase_orders");
    $result = $stmt->fetch();
    $test_results['db_connection'] = [
        'status' => 'pass',
        'message' => 'Database connected. Total POs: ' . $result['count']
    ];
} catch (Exception $e) {
    $test_results['db_connection'] = [
        'status' => 'fail',
        'message' => $e->getMessage()
    ];
}

// Test 2: Check if PO exists
try {
    $stmt = $pdo->prepare("SELECT po_id, po_number FROM purchase_orders WHERE po_id = ? LIMIT 1");
    $stmt->execute([$po_id]);
    $po = $stmt->fetch();
    
    if ($po) {
        $test_results['po_exists'] = [
            'status' => 'pass',
            'message' => 'PO found: ' . $po['po_number'] . ' (ID: ' . $po['po_id'] . ')'
        ];
    } else {
        $test_results['po_exists'] = [
            'status' => 'fail',
            'message' => 'No PO found with ID: ' . $po_id
        ];
    }
} catch (Exception $e) {
    $test_results['po_exists'] = [
        'status' => 'fail',
        'message' => $e->getMessage()
    ];
}

// Test 3: Check PO items
try {
    $sql = "
        SELECT 
            COUNT(*) as item_count,
            SUM(poi.qty) as total_qty
        FROM purchase_order_items poi
        WHERE poi.po_id = ? AND poi.temp_product_id IS NULL
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$po_id]);
    $result = $stmt->fetch();
    
    if ($result['item_count'] > 0) {
        $test_results['po_items'] = [
            'status' => 'pass',
            'message' => 'Items found: ' . $result['item_count'] . ', Total qty: ' . $result['total_qty']
        ];
    } else {
        $test_results['po_items'] = [
            'status' => 'info',
            'message' => 'No items in this PO'
        ];
    }
} catch (Exception $e) {
    $test_results['po_items'] = [
        'status' => 'fail',
        'message' => $e->getMessage()
    ];
}

// Test 4: API response format check
try {
    $sql = "
        SELECT 
            poi.item_id,
            p.name as product_name,
            poi.qty as order_qty,
            poi.price_per_unit as unit_price,
            COALESCE(SUM(ri.receive_qty), 0) as received_qty,
            (poi.qty - COALESCE(SUM(ri.receive_qty), 0)) as remaining_qty
        FROM purchase_order_items poi
        LEFT JOIN products p ON poi.product_id = p.product_id
        LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
        WHERE poi.po_id = ? AND poi.temp_product_id IS NULL
        GROUP BY poi.item_id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$po_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($item) {
        // Check data types
        $order_qty_type = gettype($item['order_qty']);
        $unit_price_type = gettype($item['unit_price']);
        
        $test_results['api_format'] = [
            'status' => 'info',
            'message' => 'Sample item: order_qty type=' . $order_qty_type . ', unit_price type=' . $unit_price_type,
            'sample' => $item
        ];
    }
} catch (Exception $e) {
    $test_results['api_format'] = [
        'status' => 'fail',
        'message' => $e->getMessage()
    ];
}

// Test 5: Check required columns
try {
    $sql = "SHOW COLUMNS FROM purchase_order_items";
    $stmt = $pdo->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_columns = ['item_id', 'po_id', 'product_id', 'qty', 'is_cancelled', 'cancelled_by'];
    
    $missing = array_diff($required_columns, $columns);
    if (empty($missing)) {
        $test_results['columns'] = [
            'status' => 'pass',
            'message' => 'All required columns exist'
        ];
    } else {
        $test_results['columns'] = [
            'status' => 'warn',
            'message' => 'Missing columns: ' . implode(', ', $missing)
        ];
    }
} catch (Exception $e) {
    $test_results['columns'] = [
        'status' => 'fail',
        'message' => $e->getMessage()
    ];
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Debug Helper - Receive Button</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Prompt', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .debug-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .debug-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .debug-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            margin: 0;
        }
        .debug-body {
            padding: 1.5rem;
        }
        .test-item {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border-left: 4px solid #ccc;
        }
        .test-pass { 
            background: #ecfdf5; 
            border-left-color: #10b981;
            color: #065f46;
        }
        .test-fail { 
            background: #fef2f2; 
            border-left-color: #ef4444;
            color: #7f1d1d;
        }
        .test-warn { 
            background: #fffbeb; 
            border-left-color: #f59e0b;
            color: #78350f;
        }
        .test-info { 
            background: #eff6ff; 
            border-left-color: #3b82f6;
            color: #0c3558;
        }
        .test-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .sample-json {
            background: #1f2937;
            color: #10b981;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            overflow-x: auto;
            margin-top: 0.5rem;
        }
        .btn-test {
            margin-top: 1rem;
        }
        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        .status-pass { background: #10b981; color: white; }
        .status-fail { background: #ef4444; color: white; }
        .status-warn { background: #f59e0b; color: white; }
        .status-info { background: #3b82f6; color: white; }
    </style>
</head>
<body>

<div class="debug-container">
    <!-- Header -->
    <div class="debug-card">
        <div class="debug-header">
            <h2 style="margin: 0;">🔧 Debug Helper - Receive Button</h2>
            <small>ตรวจสอบปัญหาและแก้ไขปุ่ม "รับสินค้า"</small>
        </div>
        <div class="debug-body">
            <p><strong>Testing PO ID:</strong> <?php echo $po_id; ?></p>
            <form method="GET" class="d-flex gap-2">
                <input type="number" name="po_id" class="form-control" value="<?php echo $po_id; ?>" placeholder="Enter PO ID" style="max-width: 150px;">
                <button type="submit" class="btn btn-primary">Test</button>
            </form>
        </div>
    </div>

    <!-- Test Results -->
    <?php foreach ($test_results as $test_name => $result): ?>
    <div class="debug-card">
        <div class="debug-header">
            <h5 style="margin: 0;">
                Test: <?php echo ucwords(str_replace('_', ' ', $test_name)); ?>
                <span class="status-badge status-<?php echo $result['status']; ?>">
                    <?php echo strtoupper($result['status']); ?>
                </span>
            </h5>
        </div>
        <div class="debug-body">
            <div class="test-item test-<?php echo $result['status']; ?>">
                <div class="test-label">
                    <?php echo match($result['status']) {
                        'pass' => '✓',
                        'fail' => '✗',
                        'warn' => '⚠',
                        'info' => 'ℹ'
                    }; ?>
                    Status
                </div>
                <div><?php echo $result['message']; ?></div>
                
                <?php if (isset($result['sample'])): ?>
                <div class="sample-json">
                    <strong>Sample Data:</strong><br>
                    <?php echo htmlspecialchars(json_encode($result['sample'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Manual API Test -->
    <div class="debug-card">
        <div class="debug-header">
            <h5 style="margin: 0;">Manual API Test</h5>
        </div>
        <div class="debug-body">
            <p>Test the API endpoint directly:</p>
            <a href="api/get_po_items.php?po_id=<?php echo $po_id; ?>" class="btn btn-outline-primary" target="_blank">
                Test API Endpoint
            </a>
            <p class="small text-muted mt-2">This will open the API in a new tab. You should see JSON data.</p>
        </div>
    </div>

    <!-- Recommended Actions -->
    <div class="debug-card">
        <div class="debug-header">
            <h5 style="margin: 0;">Recommended Actions</h5>
        </div>
        <div class="debug-body">
            <div class="list-group">
                <a href="test_receive_button.php" class="list-group-item list-group-item-action">
                    🧪 Run Test Receive Button Page
                </a>
                <a href="setup_cancel_columns.php" class="list-group-item list-group-item-action">
                    ⚙️ Setup Database Columns
                </a>
                <a href="receive/receive_po_items.php" class="list-group-item list-group-item-action">
                    📦 Go to Receive Items Page
                </a>
                <a href="javascript:location.reload()" class="list-group-item list-group-item-action">
                    🔄 Refresh This Page
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="alert alert-info">
        <strong>ℹ️ Tips:</strong>
        <ul class="mb-0 mt-2">
            <li>เปิด F12 (Browser Developer Tools) เพื่อดู Console</li>
            <li>ตรวจสอบ Network tab เพื่อดู API requests และ responses</li>
            <li>ค้นหา error messages (สีแดง) ใน Console</li>
            <li>ตรวจสอบไฟล์ error logs ของ Apache/PHP</li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
