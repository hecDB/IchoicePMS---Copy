<?php
// update_po_section.php - API for updating specific sections of purchase orders
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log all errors to help debugging
ini_set('log_errors', 1);
ini_set('error_log', '../logs/api_errors.log');

// Debug: Log incoming request
error_log('update_po_section.php called with: ' . json_encode($_POST));

try {
    require_once __DIR__ . '/../config/db_connect.php';
} catch (Exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Check if PDO connection exists
    if (!isset($pdo)) {
        throw new Exception("Database connection not available");
    }
    
    $po_id = intval($_POST['po_id'] ?? 0);
    $update_type = $_POST['update_type'] ?? '';
    
    error_log("Processing update: po_id=$po_id, type=$update_type");
    
    if (!$po_id) {
        throw new Exception("PO ID ไม่ถูกต้อง");
    }
    
    if (!$update_type) {
        throw new Exception("ประเภทการอัปเดตไม่ถูกต้อง");
    }
    
    $pdo->beginTransaction();
    
    switch ($update_type) {
        case 'user':
            $ordered_by = intval($_POST['ordered_by'] ?? 0);
            if (!$ordered_by) {
                throw new Exception("ผู้สั่งซื้อไม่ถูกต้อง");
            }
            
            $stmt = $pdo->prepare("UPDATE purchase_orders SET ordered_by = ? WHERE po_id = ?");
            $stmt->execute([$ordered_by, $po_id]);
            break;
            
        case 'supplier':
            $supplier_id = intval($_POST['supplier_id'] ?? 0);
            if (!$supplier_id) {
                throw new Exception("ผู้จำหน่ายไม่ถูกต้อง");
            }
            
            $stmt = $pdo->prepare("UPDATE purchase_orders SET supplier_id = ? WHERE po_id = ?");
            $stmt->execute([$supplier_id, $po_id]);
            break;
            
        case 'item':
            $item_id = intval($_POST['item_id'] ?? 0);
            $product_id = intval($_POST['product_id'] ?? 0);
            $product_name = trim($_POST['product_name'] ?? '');
            $qty = intval($_POST['qty'] ?? 1);
            $price_per_unit = floatval($_POST['price_per_unit'] ?? 0);
            $currency_id = intval($_POST['currency_id'] ?? 1);
            $price_original = floatval($_POST['price_original'] ?? $price_per_unit);
            $exchange_rate = floatval($_POST['exchange_rate'] ?? 1.0);
            
            if (!$item_id || !$product_name || $qty < 1) {
                throw new Exception("ข้อมูลรายการสินค้าไม่ถูกต้อง");
            }
            
            $price_base = $price_original * $exchange_rate;
            $total = $qty * $price_base;
            
            // Update item with product_id if available
            if ($product_id > 0) {
                $stmt = $pdo->prepare("UPDATE purchase_order_items SET product_id = ?, qty = ?, price_per_unit = ?, total = ?, currency_id = ?, price_original = ?, price_base = ? WHERE item_id = ? AND po_id = ?");
                $stmt->execute([$product_id, $qty, $price_base, $total, $currency_id, $price_original, $price_base, $item_id, $po_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE purchase_order_items SET qty = ?, price_per_unit = ?, total = ?, currency_id = ?, price_original = ?, price_base = ? WHERE item_id = ? AND po_id = ?");
                $stmt->execute([$qty, $price_base, $total, $currency_id, $price_original, $price_base, $item_id, $po_id]);
            }
            
            // Update product name if it's different (assuming there's a product table)
            // For now, we'll skip this as it requires more complex logic
            
            // Recalculate total amount for PO
            $stmt = $pdo->prepare("SELECT SUM(total) FROM purchase_order_items WHERE po_id = ?");
            $stmt->execute([$po_id]);
            $new_total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE po_id = ?");
            $stmt->execute([$new_total, $po_id]);
            break;
            
        case 'add_item':
            $product_id = intval($_POST['product_id'] ?? 0);
            $product_name = trim($_POST['product_name'] ?? '');
            $qty = intval($_POST['qty'] ?? 1);
            $price_per_unit = floatval($_POST['price_per_unit'] ?? 0);
            $unit = trim($_POST['unit'] ?? 'ชิ้น');
            $currency_id = intval($_POST['currency_id'] ?? 1);
            $price_original = floatval($_POST['price_original'] ?? $price_per_unit);
            $exchange_rate = floatval($_POST['exchange_rate'] ?? 1.0);
            
            if (!$product_name || $qty < 1) {
                throw new Exception("ข้อมูลสินค้าใหม่ไม่ถูกต้อง");
            }
            
            $price_base = $price_original * $exchange_rate;
            $total = $qty * $price_base;
            
            // Insert new item with product_id if available, otherwise use 0 for manual entries
            $stmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, price_per_unit, total, currency_id, price_original, price_base) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$po_id, $product_id, $qty, $price_original, $total, $currency_id, $price_original, $price_base]);
            
            $new_item_id = $pdo->lastInsertId();
            
            // Recalculate total amount for PO
            $stmt = $pdo->prepare("SELECT SUM(total) FROM purchase_order_items WHERE po_id = ?");
            $stmt->execute([$po_id]);
            $new_total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE po_id = ?");
            $stmt->execute([$new_total, $po_id]);
            
            // Get additional product info if product_id exists
            $sku = '-';
            $image = '';
            if ($product_id > 0) {
                $stmt = $pdo->prepare("SELECT sku, image FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $product_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $sku = $product_data['sku'] ?? '-';
                $image = $product_data['image'] ?? '';
            }
            
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'item_id' => $new_item_id,
                'sku' => $sku,
                'image' => $image,
                'unit' => $unit
            ]);
            exit;
            break;
            
        case 'delete_item':
            $item_id = intval($_POST['item_id'] ?? 0);
            
            if (!$item_id) {
                throw new Exception("รหัสรายการสินค้าไม่ถูกต้อง");
            }
            
            $stmt = $pdo->prepare("DELETE FROM purchase_order_items WHERE item_id = ? AND po_id = ?");
            $stmt->execute([$item_id, $po_id]);
            
            // Recalculate total amount for PO
            $stmt = $pdo->prepare("SELECT SUM(total) FROM purchase_order_items WHERE po_id = ?");
            $stmt->execute([$po_id]);
            $new_total = $stmt->fetchColumn() ?: 0;
            
            $stmt = $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE po_id = ?");
            $stmt->execute([$new_total, $po_id]);
            break;
            
        default:
            throw new Exception("ประเภทการอัปเดตไม่รองรับ");
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>