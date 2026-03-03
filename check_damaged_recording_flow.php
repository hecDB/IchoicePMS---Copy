<?php
require 'config/db_connect.php';

echo "═════════════════════════════════════════════════════════════\n";
echo "🔍 CHECKING DAMAGED ITEM RECORDING FLOW\n";
echo "═════════════════════════════════════════════════════════════\n\n";

// 1. Check returned_items structure
echo "【 1. Returned Items Table Structure 】\n";
echo "─────────────────────────────────────────────────────────────\n\n";

$stmt = $pdo->prepare("
    SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'returned_items'
    ORDER BY ORDINAL_POSITION
");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total columns: " . count($columns) . "\n\n";

$importantCols = [
    'return_id', 'return_code', 'po_id', 'item_id', 'product_id', 
    'product_name', 'sku', 'barcode', 'return_qty', 'cost_price', 'sale_price',
    'reason_id', 'reason_name', 'is_returnable', 'expiry_date', 'notes',
    'status', 'new_sku', 'new_product_id', 'restock_qty', 'defect_notes',
    'inspected_by', 'inspected_at', 'restocked_by', 'restocked_at',
    'created_by', 'created_at', 'updated_at', 'temp_product_id'
];

$colNames = array_column($columns, 'COLUMN_NAME');

foreach ($importantCols as $col) {
    if (in_array($col, $colNames)) {
        $colInfo = array_filter($columns, fn($c) => $c['COLUMN_NAME'] === $col);
        if ($colInfo) {
            $colInfo = array_values($colInfo)[0];
            echo "✓ $col\n";
            echo "  └─ Type: {$colInfo['COLUMN_TYPE']}, Nullable: {$colInfo['IS_NULLABLE']}\n";
        }
    } else {
        echo "✗ MISSING: $col\n";
    }
}

// 2. Check API endpoint
echo "\n\n【 2. API Create Return Endpoint 】\n";
echo "─────────────────────────────────────────────────────────────\n\n";

$apiFile = 'api/returned_items_api.php';
if (file_exists($apiFile)) {
    echo "✓ File exists: $apiFile\n\n";
    
    // Search for create_return action
    $content = file_get_contents($apiFile);
    if (strpos($content, "action === 'create_return'") !== false) {
        echo "✓ Found 'create_return' action in API\n";
        
        // Extract sample code
        $startIdx = strpos($content, "if (\$action === 'create_return')");
        if ($startIdx !== false) {
            $endIdx = strpos($content, "exit;", $startIdx);
            if ($endIdx !== false) {
                $code = substr($content, $startIdx, $endIdx - $startIdx + 5);
                
                // Check for SQL INSERT patterns
                if (strpos($code, 'INSERT INTO') !== false) {
                    echo "✓ Contains INSERT INTO statement\n";
                }
                
                if (preg_match('/return_id|return_code|po_id|item_id/', $code)) {
                    echo "✓ References basic return fields\n";
                }
                
                if (preg_match('/reason_id|reason_name/', $code)) {
                    echo "✓ References reason fields\n";
                }
            }
        }
    } else {
        echo "✗ 'create_return' action NOT found\n";
    }
} else {
    echo "✗ File not found: $apiFile\n";
}

// 3. Check actual damaged item in database
echo "\n\n【 3. Sample Damaged Item Record 】\n";
echo "─────────────────────────────────────────────────────────────\n\n";

$stmt2 = $pdo->prepare("
    SELECT * FROM returned_items 
    WHERE reason_id = 8
    LIMIT 1
");
$stmt2->execute();
$sample = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($sample) {
    echo "Sample record found:\n\n";
    
    echo "【Original Product Data】\n";
    echo "  return_id: {$sample['return_id']}\n";
    echo "  return_code: {$sample['return_code']}\n";
    echo "  po_id: {$sample['po_id']}\n";
    echo "  item_id: {$sample['item_id']}\n";
    echo "  product_id: {$sample['product_id']}\n";
    echo "  product_name: {$sample['product_name']}\n";
    echo "  sku: {$sample['sku']}\n";
    echo "  barcode: {$sample['barcode']}\n";
    echo "  return_qty: {$sample['return_qty']}\n";
    echo "  cost_price: {$sample['cost_price']}\n";
    echo "  sale_price: {$sample['sale_price']}\n";
    echo "  reason_id: {$sample['reason_id']}\n";
    echo "  reason_name: {$sample['reason_name']}\n";
    echo "  expiry_date: {$sample['expiry_date']}\n";
    echo "  notes: " . substr($sample['notes'] ?? '', 0, 50) . "...\n\n";
    
    echo "【Inspection Data】\n";
    echo "  status: {$sample['status']}\n";
    echo "  new_sku: {$sample['new_sku']}\n";
    echo "  new_product_id: {$sample['new_product_id']}\n";
    echo "  cost_price: {$sample['cost_price']}\n";
    echo "  sale_price: {$sample['sale_price']}\n";
    echo "  restock_qty: {$sample['restock_qty']}\n";
    echo "  defect_notes: " . substr($sample['defect_notes'] ?? '', 0, 50) . "...\n";
    echo "  inspected_by: {$sample['inspected_by']}\n";
    echo "  inspected_at: {$sample['inspected_at']}\n";
    echo "  restocked_by: {$sample['restocked_by']}\n";
    echo "  restocked_at: {$sample['restocked_at']}\n";
    echo "  temp_product_id: {$sample['temp_product_id']}\n\n";
    
    echo "【Audit】\n";
    echo "  created_by: {$sample['created_by']}\n";
    echo "  created_at: {$sample['created_at']}\n";
    echo "  updated_at: {$sample['updated_at']}\n";
} else {
    echo "No damaged items found in database\n";
}

// 4. Check for potential issues
echo "\n\n【 4. Potential Issues & Recommendations 】\n";
echo "─────────────────────────────────────────────────────────────\n\n";

echo "Issues to verify in API:\n\n";

echo "1️⃣  When creating damaged return record:\n";
echo "   ✓ Should insert into returned_items with reason_id = 8\n";
echo "   ✓ Should populate: po_id, item_id, return_qty, reason_id\n";
echo "   ✓ Should handle NULL product_id (for NEW products)\n";
echo "   ✓ Should populate: product_name, sku, barcode (from PO items)\n";
echo "   ✓ Should populate: cost_price, sale_price (from PO)\n";
echo "   ✓ Should set: expiry_date (user input)\n";
echo "   ✓ Should set: notes (user notes)\n";
echo "   ✓ Should populate: created_by, created_at\n\n";

echo "2️⃣  When submitting inspection (damaged item confirmation):\n";
echo "   ✓ Should UPDATE returned_items with:\n";
echo "   ✓   status = 'completed'\n";
echo "   ✓   new_sku (generated: ตำหนิ-originalsku)\n";
echo "   ✓   new_product_id (if created)\n";
echo "   ✓   defect_notes (with [ขายได้]/[ทิ้ง] prefix)\n";
echo "   ✓   restock_qty (user entered)\n";
echo "   ✓   inspected_by, inspected_at\n";
echo "   ✓   is_returnable (1=sellable, 0=discard)\n\n";

echo "3️⃣  For NEW PRODUCTS:\n";
echo "   ✓ product_id can be NULL initially\n";
echo "   ✓ Should store product_name, sku, barcode from temp data\n";
echo "   ✓ temp_product_id links to temp_products table\n";
echo "   ✓ new_product_id set when defect product created\n\n";

?>
