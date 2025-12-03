<?php
/**
 * Test script to diagnose "รับสินค้า" (Receive) button issues
 * This helps identify why the button click doesn't load PO items
 */

session_start();
require 'config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ทดสอบปุ่มรับสินค้า - Receive Button Test</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
        .test-card { background: white; border-radius: 12px; margin: 1rem 0; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .test-pass { background-color: #dcfce7; border-left: 4px solid #22c55e; }
        .test-fail { background-color: #fee2e2; border-left: 4px solid #ef4444; }
        .test-info { background-color: #e0f2fe; border-left: 4px solid #0ea5e9; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
        .console-output { 
            background: #1f2937; 
            color: #10b981; 
            padding: 1rem; 
            border-radius: 8px; 
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">🧪 ทดสอบปุ่ม "รับสินค้า" (Receive Button)</h1>

    <!-- Test 1: Check if jQuery is loaded -->
    <div class="test-card test-info">
        <h5>✓ Test 1: jQuery & Bootstrap</h5>
        <p>jQuery and Bootstrap are loaded in the console below</p>
    </div>

    <!-- Test 2: Check database connection -->
    <div class="test-card">
        <h5>Test 2: Database Connection</h5>
        <?php
        try {
            $sql = "SELECT po_id, po_number FROM purchase_orders LIMIT 1";
            $stmt = $pdo->query($sql);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($po) {
                echo '<div class="test-pass"><strong>✓ PASS</strong> Database connected</div>';
                echo '<p class="mt-2">Sample PO found: <strong>' . htmlspecialchars($po['po_number']) . '</strong> (ID: ' . $po['po_id'] . ')</p>';
            } else {
                echo '<div class="test-fail"><strong>✗ FAIL</strong> No PO found in database</div>';
            }
        } catch (Exception $e) {
            echo '<div class="test-fail"><strong>✗ FAIL</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
    </div>

    <!-- Test 3: Check API endpoint -->
    <div class="test-card">
        <h5>Test 3: API Endpoint (get_po_items.php)</h5>
        <div id="apiTestResult"></div>
    </div>

    <!-- Test 4: Mock PO card with receive button -->
    <div class="test-card">
        <h5>Test 4: Mock Receive Button (Click to test)</h5>
        <button class="btn btn-primary receive-po-btn" 
                data-po-id="1" 
                data-po-number="PO-2025-00001" 
                data-supplier="Test Supplier">
            <span class="material-icons" style="vertical-align: middle;">shopping_bag</span>
            รับสินค้า (Test Button)
        </button>
        <p class="mt-3 text-muted">Click the button above, then check the console (F12 → Console)</p>
    </div>

    <!-- Test 5: JavaScript console -->
    <div class="test-card test-info">
        <h5>📋 Browser Console Output (F12 → Console)</h5>
        <div class="console-output" id="consoleOutput">
            [Waiting for button click...]
        </div>
    </div>

    <!-- Test 6: Detailed diagnostics -->
    <div class="test-card">
        <h5>Test 5: Detailed Diagnostics</h5>
        <div id="diagnostics"></div>
    </div>

    <!-- Test 7: Quick navigation -->
    <div class="test-card">
        <h5>📍 Quick Navigation</h5>
        <ul>
            <li><a href="receive/receive_po_items.php" class="btn btn-outline-primary btn-sm">Go to Receive Items Page</a></li>
            <li><a href="setup_cancel_columns.php" class="btn btn-outline-success btn-sm">Setup Database Columns</a></li>
            <li><a href="test_receive_system.php" class="btn btn-outline-info btn-sm">Full System Test</a></li>
        </ul>
    </div>
</div>

<!-- Modal for testing -->
<div class="modal fade" id="poItemsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">PO: <span id="modalPoNumber">-</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Unit</th>
                            <th>Order Qty</th>
                            <th>Price</th>
                            <th>Received</th>
                            <th>Remaining</th>
                        </tr>
                    </thead>
                    <tbody id="poItemsTableBody">
                        <tr><td colspan="8" class="text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Capture console logs
const logs = [];
const originalLog = console.log;
const originalError = console.error;

console.log = function(...args) {
    logs.push('[LOG] ' + args.join(' '));
    originalLog.apply(console, args);
    updateConsoleOutput();
};

console.error = function(...args) {
    logs.push('[ERROR] ' + args.join(' '));
    originalError.apply(console, args);
    updateConsoleOutput();
};

function updateConsoleOutput() {
    $('#consoleOutput').text(logs.join('\n'));
    $('#consoleOutput').scrollTop($('#consoleOutput')[0].scrollHeight);
}

// Run diagnostics
$(document).ready(function() {
    console.log('=== Receive Button Test Started ===');
    console.log('jQuery version: ' + $.fn.jquery);
    console.log('jQuery ajax available: ' + (typeof $.ajax === 'function'));
    
    // Diagnostics
    let diag = '<ul>';
    diag += '<li>User logged in: ' + (<?php echo $user_id ? 'true' : 'false'; ?>) + '</li>';
    diag += '<li>jQuery loaded: <strong>✓</strong></li>';
    diag += '<li>Bootstrap loaded: <strong>✓</strong></li>';
    diag += '<li>document.ready: <strong>✓</strong></li>';
    diag += '<li>Event delegation (.receive-po-btn): ';
    
    // Check if button exists
    if ($('.receive-po-btn').length > 0) {
        diag += '<strong>✓</strong> (Found ' + $('.receive-po-btn').length + ' button(s))';
    } else {
        diag += '<strong>?</strong> (No buttons found - test buttons may load dynamically)';
    }
    diag += '</li></ul>';
    
    $('#diagnostics').html(diag);
    
    // Setup event listener
    console.log('Setting up event listener for .receive-po-btn');
    
    $(document).on('click', '.receive-po-btn', function(e) {
        e.preventDefault();
        console.log('=== Button Clicked ===');
        
        const poId = $(this).data('po-id');
        const poNumber = $(this).data('po-number');
        const supplier = $(this).data('supplier');
        const remark = $(this).data('remark') || '';
        
        console.log('Button data:', {
            poId: poId,
            poNumber: poNumber,
            supplier: supplier,
            remark: remark
        });
        
        // Show modal
        $('#modalPoNumber').text(poNumber);
        $('#poItemsModal').modal('show');
        console.log('Modal shown');
        
        // Test API call
        const apiUrl = 'api/get_po_items.php';
        console.log('Calling API: ' + apiUrl + '?po_id=' + poId);
        
        $.ajax({
            url: apiUrl,
            method: 'GET',
            data: { po_id: poId },
            dataType: 'json',
            success: function(response) {
                console.log('✓ API Success Response:', response);
                
                if (response && response.success && response.items) {
                    const itemCount = response.items.length;
                    console.log('✓ Items loaded: ' + itemCount);
                    
                    // Display items
                    let html = '';
                    response.items.forEach((item, idx) => {
                        html += '<tr><td>' + (idx+1) + '</td>';
                        html += '<td>' + item.product_name + '</td>';
                        html += '<td>' + item.sku + '</td>';
                        html += '<td>' + item.unit + '</td>';
                        html += '<td>' + item.order_qty + '</td>';
                        html += '<td>' + item.unit_price + '</td>';
                        html += '<td>' + item.received_qty + '</td>';
                        html += '<td>' + item.remaining_qty + '</td></tr>';
                    });
                    $('#poItemsTableBody').html(html);
                } else {
                    console.error('✗ Invalid response format:', response);
                    $('#poItemsTableBody').html('<tr><td colspan="8" class="text-danger">Invalid API response</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('✗ API Error:', {
                    status: status,
                    error: error,
                    statusCode: xhr.status,
                    responseText: xhr.responseText.substring(0, 200)
                });
                $('#poItemsTableBody').html('<tr><td colspan="8" class="text-danger">API Error: ' + error + '</td></tr>');
            }
        });
    });
    
    console.log('=== Setup Complete ===');
});
</script>

</body>
</html>
