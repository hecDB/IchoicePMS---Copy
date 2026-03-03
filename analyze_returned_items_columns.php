<?php
require 'config/db_connect.php';

echo "═════════════════════════════════════════════════════════════\n";
echo "📊 RETURNED_ITEMS TABLE - COLUMN USAGE ANALYSIS\n";
echo "═════════════════════════════════════════════════════════════\n\n";

// Get all columns
$stmt = $pdo->prepare("
    SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'returned_items'
    ORDER BY ORDINAL_POSITION
");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📝 TOTAL COLUMNS: " . count($columns) . "\n\n";

// Get sample data usage
$stmt2 = $pdo->prepare("SELECT COUNT(*) as total FROM returned_items");
$stmt2->execute();
$totalRows = $stmt2->fetch(PDO::FETCH_ASSOC)['total'];

echo "📈 TOTAL RECORDS: $totalRows\n\n";

// Analyze each column for usage
echo "┌─ COLUMN DETAILS:\n";
echo "│\n";

$groups = [
    'PRIMARY/FOREIGN KEYS' => ['return_id', 'po_id', 'so_id', 'item_id', 'product_id', 'temp_product_id', 'location_id'],
    'PRODUCT INFORMATION' => ['product_name', 'sku', 'barcode', 'original_qty', 'return_qty'],
    'RETURN METADATA' => ['return_code', 'issue_tag', 'reason_id', 'reason_name', 'is_returnable', 'return_status', 'return_from_sales'],
    'RETURN DETAILS' => ['image_path', 'notes', 'expiry_date', 'condition_detail'],
    'APPROVAL & AUDIT' => ['approved_by', 'approved_at', 'created_by', 'created_at', 'updated_at'],
    'INSPECTION/DAMAGE COLUMNS' => ['status', 'new_sku', 'new_product_id', 'cost_price', 'sale_price', 'restock_qty', 'defect_notes', 'inspected_by', 'inspected_at', 'restocked_by', 'restocked_at']
];

foreach ($groups as $group => $colNames) {
    echo "│\n";
    echo "├─ $group\n";
    
    foreach ($colNames as $colName) {
        $col = array_filter($columns, fn($c) => $c['COLUMN_NAME'] === $colName);
        if (empty($col)) continue;
        
        $col = array_values($col)[0];
        
        // Check how many records have non-NULL values
        $checkStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN " . $colName . " IS NOT NULL THEN 1 ELSE 0 END) as used,
                SUM(CASE WHEN " . $colName . " IS NULL THEN 1 ELSE 0 END) as unused
            FROM returned_items
        ");
        $checkStmt->execute();
        $usage = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        $usagePercent = $totalRows > 0 ? round(($usage['used'] / $totalRows) * 100, 1) : 0;
        $statusIcon = $usagePercent >= 70 ? '✓' : ($usagePercent > 0 ? '◐' : '○');
        
        echo "│  $statusIcon ";
        echo str_pad($colName, 22);
        echo $col['COLUMN_TYPE'];
        echo " | Nullable: " . ($col['IS_NULLABLE'] === 'YES' ? 'Yes' : 'No');
        echo " | Usage: $usagePercent%";
        echo " (" . $usage['used'] . "/" . $totalRows . ")";
        echo "\n";
    }
}

echo "│\n";
echo "└─\n\n";

// Detail breakdown
echo "═════════════════════════════════════════════════════════════\n";
echo "📋 DETAILED COLUMN DESCRIPTIONS\n";
echo "═════════════════════════════════════════════════════════════\n\n";

$descriptions = [
    'return_id' => 'Primary key - Unique return record ID',
    'return_code' => 'Return reference code (e.g., RET-20260303-1151)',
    'po_id' => 'Purchase Order ID linked to this return',
    'po_number' => 'Purchase Order number (e.g., PO-2026-001)',
    'receive_id' => 'Receive ID from GRN (Goods Receipt Note)',
    'so_id' => 'Sales Order ID (if return from sales)',
    'issue_tag' => 'Issue category tag',
    'item_id' => 'Item ID from PO/SO',
    'product_id' => 'Product ID being returned',
    'temp_product_id' => 'Temp product ID for new/pending products',
    'product_name' => 'Name of product being returned',
    'sku' => 'Product SKU code',
    'barcode' => 'Product barcode',
    'original_qty' => 'Original quantity from PO/SO',
    'return_qty' => 'Quantity being returned',
    'reason_id' => 'Return reason ID (8=ชำรุดบางส่วน)',
    'reason_name' => 'Human-readable reason name',
    'is_returnable' => 'Can this item be restocked? (1=Yes, 0=No)',
    'return_status' => 'Status: pending/completed',
    'return_from_sales' => 'Is return from customer? (1=Yes, 0=No from supplier)',
    'image_path' => 'Photo of returned item',
    'notes' => 'Return notes/comments',
    'expiry_date' => 'Product expiry date',
    'condition_detail' => 'Detailed condition description',
    'location_id' => 'Storage location ID',
    'approved_by' => 'User ID who approved',
    'approved_at' => 'Approval timestamp',
    'created_by' => 'User ID who created record',
    'created_at' => 'Creation timestamp',
    'updated_at' => 'Last update timestamp',
    'status' => '[NEW] Inspection status: pending/completed',
    'new_sku' => '[NEW] Generated defect SKU (e.g., ตำหนิ-SKU123)',
    'new_product_id' => '[NEW] Product ID for defect product',
    'cost_price' => '[NEW] Verified cost price',
    'sale_price' => '[NEW] Verified sale price',
    'restock_qty' => '[NEW] Quantity to restock',
    'defect_notes' => '[NEW] Inspection notes & defect details',
    'inspected_by' => '[NEW] User ID who inspected',
    'inspected_at' => '[NEW] Inspection timestamp',
    'restocked_by' => '[NEW] User ID who restocked',
    'restocked_at' => '[NEW] Restock timestamp'
];

foreach ($descriptions as $col => $desc) {
    echo "💾 $col\n";
    echo "   $desc\n\n";
}

echo "\n═════════════════════════════════════════════════════════════\n";
echo "📌 USAGE LEGEND\n";
echo "═════════════════════════════════════════════════════════════\n";
echo "✓  = Actively used (≥70% records have data)\n";
echo "◐  = Partially used (>0% but <70%)\n";
echo "○  = Not used (0% records have data)\n";

// Show actual sample data
echo "\n═════════════════════════════════════════════════════════════\n";
echo "📑 SAMPLE RECORDS\n";
echo "═════════════════════════════════════════════════════════════\n\n";

$sampleStmt = $pdo->prepare("
    SELECT 
        return_id, return_code, product_name, reason_name, return_qty,
        return_status, status, new_sku, restock_qty, is_returnable,
        created_at, inspected_at
    FROM returned_items
    LIMIT 3
");
$sampleStmt->execute();
$samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($samples as $i => $row) {
    $i++;
    echo "Record #$i:\n";
    foreach ($row as $key => $val) {
        $display = $val ?? '[NULL]';
        echo "  $key: $display\n";
    }
    echo "\n";
}

?>
