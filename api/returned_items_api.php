<?php
/**
 * API: สินค้าตีกลับ
 * ดำเนินการ: สร้าง, ดูรายการ, ค้นหา, อนุมัติ, ปฏิเสธ
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require '../config/db_connect.php';

ensureDamagedReturnQueue($pdo);
ensureIssueItemsExpiryColumn($pdo);
ensureReturnedItemsReceiveIdColumn($pdo);

function ensureDamagedReturnQueue(PDO $pdo): void
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS damaged_return_inspections (
            inspection_id INT AUTO_INCREMENT PRIMARY KEY,
            return_id INT NOT NULL,
            return_code VARCHAR(50) NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) NULL,
            barcode VARCHAR(100) NULL,
            expiry_date DATE NULL,
            po_id INT NULL,
            po_number VARCHAR(50) NULL,
            return_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
            reason_id INT NOT NULL DEFAULT 0,
            reason_name VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            new_sku VARCHAR(100) NULL,
            new_product_id INT NULL,
            cost_price DECIMAL(12,2) NULL,
            sale_price DECIMAL(12,2) NULL,
            restock_qty DECIMAL(12,2) NULL,
            defect_notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            inspected_by INT NULL,
            inspected_at DATETIME NULL,
            restocked_by INT NULL,
            restocked_at DATETIME NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $columnsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'damaged_return_inspections'");
        $columnsStmt->execute();
        $existingColumns = array_flip(array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));

        $requiredColumns = [
            'reason_id' => "ALTER TABLE damaged_return_inspections ADD COLUMN reason_id INT NOT NULL DEFAULT 0 AFTER return_qty",
            'reason_name' => "ALTER TABLE damaged_return_inspections ADD COLUMN reason_name VARCHAR(255) NOT NULL AFTER reason_id",
            'status' => "ALTER TABLE damaged_return_inspections ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER reason_name",
            'new_sku' => "ALTER TABLE damaged_return_inspections ADD COLUMN new_sku VARCHAR(100) NULL AFTER status",
            'new_product_id' => "ALTER TABLE damaged_return_inspections ADD COLUMN new_product_id INT NULL AFTER new_sku",
            'receive_id' => "ALTER TABLE damaged_return_inspections ADD COLUMN receive_id INT NULL AFTER barcode",
            'expiry_date' => "ALTER TABLE damaged_return_inspections ADD COLUMN expiry_date DATE NULL AFTER receive_id",
            'po_id' => "ALTER TABLE damaged_return_inspections ADD COLUMN po_id INT NULL AFTER expiry_date",
            'po_number' => "ALTER TABLE damaged_return_inspections ADD COLUMN po_number VARCHAR(50) NULL AFTER po_id",
            'cost_price' => "ALTER TABLE damaged_return_inspections ADD COLUMN cost_price DECIMAL(12,2) NULL AFTER new_product_id",
            'sale_price' => "ALTER TABLE damaged_return_inspections ADD COLUMN sale_price DECIMAL(12,2) NULL AFTER cost_price",
            'restock_qty' => "ALTER TABLE damaged_return_inspections ADD COLUMN restock_qty DECIMAL(12,2) NULL AFTER sale_price",
            'defect_notes' => "ALTER TABLE damaged_return_inspections ADD COLUMN defect_notes TEXT NULL AFTER restock_qty",
            'created_by' => "ALTER TABLE damaged_return_inspections ADD COLUMN created_by INT NULL AFTER defect_notes",
            'created_at' => "ALTER TABLE damaged_return_inspections ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER created_by",
            'inspected_by' => "ALTER TABLE damaged_return_inspections ADD COLUMN inspected_by INT NULL AFTER created_at",
            'inspected_at' => "ALTER TABLE damaged_return_inspections ADD COLUMN inspected_at DATETIME NULL AFTER inspected_by",
            'restocked_by' => "ALTER TABLE damaged_return_inspections ADD COLUMN restocked_by INT NULL AFTER inspected_at",
            'restocked_at' => "ALTER TABLE damaged_return_inspections ADD COLUMN restocked_at DATETIME NULL AFTER restocked_by",
            'updated_at' => "ALTER TABLE damaged_return_inspections ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER restocked_at"
        ];

        foreach ($requiredColumns as $column => $ddl) {
            if (!isset($existingColumns[$column])) {
                $pdo->exec($ddl);
            }
        }

        $indexCheck = $pdo->query("SHOW INDEX FROM damaged_return_inspections WHERE Key_name = 'uniq_return_id'");
        if ($indexCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE damaged_return_inspections ADD UNIQUE KEY uniq_return_id (return_id)");
        }

        $fkCheck = $pdo->prepare("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'damaged_return_inspections'");
        $fkCheck->execute();
        if ($fkCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE damaged_return_inspections ADD CONSTRAINT fk_damaged_return_returned_items FOREIGN KEY (return_id) REFERENCES returned_items(return_id) ON DELETE CASCADE");
        }
    } catch (Exception $e) {
        error_log('Failed to initialize damaged_return_inspections table: ' . $e->getMessage());
    }
}

function ensureIssueItemsExpiryColumn(PDO $pdo): void
{
    try {
        // Check if expiry_date column exists in issue_items
        $columnsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'issue_items' AND COLUMN_NAME = 'expiry_date'");
        $columnsStmt->execute();
        
        if ($columnsStmt->rowCount() === 0) {
            // Add expiry_date column if it doesn't exist
            $pdo->exec("ALTER TABLE issue_items ADD COLUMN expiry_date DATE NULL AFTER issue_qty");
        }
    } catch (Exception $e) {
        error_log('Failed to ensure issue_items expiry_date column: ' . $e->getMessage());
    }
}

function ensureReturnedItemsReceiveIdColumn(PDO $pdo): void
{
    try {
        // Check if receive_id column exists in returned_items
        $columnsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'returned_items' AND COLUMN_NAME = 'receive_id'");
        $columnsStmt->execute();
        
        if ($columnsStmt->rowCount() === 0) {
            // Add receive_id column if it doesn't exist
            $pdo->exec("ALTER TABLE returned_items ADD COLUMN receive_id INT NULL COMMENT 'receive_id from issue_items for batch/lot tracking' AFTER po_number");
            // Add index for receive_id
            $pdo->exec("ALTER TABLE returned_items ADD KEY idx_receive_id (receive_id)");
        }
    } catch (Exception $e) {
        error_log('Failed to ensure returned_items receive_id column: ' . $e->getMessage());
    }
}

function findProductBySku(PDO $pdo, string $sku): ?array
{
    if ($sku === '') {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM products WHERE sku = :sku LIMIT 1");
    $stmt->execute([':sku' => $sku]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    return $product ?: null;
}

function createDefectProduct(PDO $pdo, array $sourceProduct, string $newSku, int $userId): int
{
    $baseName = $sourceProduct['name'] ?? 'สินค้าใหม่';
    $nameSuffix = ' (สินค้ามีตำหนิ)';
    $finalName = mb_strpos($baseName, 'ตำหนิ') === false ? $baseName . $nameSuffix : $baseName;

    $stmt = $pdo->prepare("INSERT INTO products (
        name, sku, barcode, unit, image, remark_color, remark_split, is_active, created_by, created_at, product_category_id, category_name
    ) VALUES (
        :name, :sku, :barcode, :unit, :image, :remark_color, :remark_split, 1, :created_by, NOW(), :product_category_id, :category_name
    )");

    $stmt->execute([
        ':name' => $finalName,
        ':sku' => $newSku,
        ':barcode' => $sourceProduct['barcode'] ?? null,
        ':unit' => $sourceProduct['unit'] ?? null,
        ':image' => $sourceProduct['image'] ?? null,
        ':remark_color' => $sourceProduct['remark_color'] ?? '',
        ':remark_split' => isset($sourceProduct['remark_split']) ? (int)$sourceProduct['remark_split'] : 0,
        ':created_by' => $userId,
        ':product_category_id' => $sourceProduct['product_category_id'] ?? null,
        ':category_name' => $sourceProduct['category_name'] ?? null
    ]);

    return (int)$pdo->lastInsertId();
}

function buildDefectSku(string $originalSku): string
{
    $trimmed = trim($originalSku);
    if ($trimmed === '') {
        throw new InvalidArgumentException('Original SKU is required for damaged inspections');
    }
    $prefix = 'ตำหนิ-';
    return mb_strpos($trimmed, $prefix) === 0 ? $trimmed : $prefix . $trimmed;
}

function updatePOStatusInline(PDO $pdo, int $po_id): void
{
    // Check completion status considering received, cancelled, AND damaged items
    $status_sql = "
        SELECT 
            poi.item_id,
            poi.product_id,
            poi.qty as ordered_qty,
            COALESCE(received_summary.total_received, 0) as received_qty,
            COALESCE(poi.cancel_qty, 0) as cancel_qty,
            COALESCE(damaged_summary.total_damaged, 0) as damaged_qty,
            poi.is_cancelled,
            poi.is_partially_cancelled
        FROM purchase_order_items poi
        LEFT JOIN (
            SELECT item_id, SUM(receive_qty) as total_received 
            FROM receive_items 
            GROUP BY item_id
        ) received_summary ON poi.item_id = received_summary.item_id
        LEFT JOIN (
            SELECT product_id, SUM(return_qty) as total_damaged
            FROM returned_items
            WHERE po_id = ? AND is_returnable = 0
            GROUP BY product_id
        ) damaged_summary ON poi.product_id = damaged_summary.product_id
        WHERE poi.po_id = ?
    ";
    
    $status_stmt = $pdo->prepare($status_sql);
    $status_stmt->execute([$po_id, $po_id]);
    $items_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($items_data && count($items_data) > 0) {
        $total_items = 0;
        $fully_processed_items = 0;
        $any_partial_processing = false;
        
        foreach ($items_data as $item) {
            $total_items++;
            
            $ordered_qty = floatval($item['ordered_qty']);
            $received_qty = floatval($item['received_qty']);
            $cancel_qty = floatval($item['cancel_qty']);
            $damaged_qty = floatval($item['damaged_qty']);
            
            $total_processed = $received_qty + $cancel_qty + $damaged_qty;
            
            if ($total_processed >= $ordered_qty) {
                $fully_processed_items++;
            } else if ($received_qty > 0 || $cancel_qty > 0 || $damaged_qty > 0) {
                $any_partial_processing = true;
            }
        }
        
        $new_status = 'pending';
        
        if ($fully_processed_items >= $total_items) {
            $new_status = 'completed';
        } elseif ($any_partial_processing || $fully_processed_items > 0) {
            $new_status = 'partial';
        }
        
        error_log("PO Status Update: PO_ID=$po_id, Total Items=$total_items, Fully Processed=$fully_processed_items, New Status=$new_status");
        
        $update_sql = "UPDATE purchase_orders SET status = ? WHERE po_id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$new_status, $po_id]);
    }
}

function logProductActivity(PDO $pdo, int $productId, int $userId, float $quantity, string $reference, string $sku, ?string $notes = null): void
{
    try {
        static $productActivityColumns = null;

        if ($productActivityColumns === false) {
            return;
        }

        if ($productActivityColumns === null) {
            $columnsStmt = $pdo->query('SHOW COLUMNS FROM product_activity');
            if (!$columnsStmt) {
                $productActivityColumns = false;
                return;
            }
            $productActivityColumns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
            if (empty($productActivityColumns)) {
                $productActivityColumns = false;
                return;
            }
        }

        $columns = $productActivityColumns;
        $data = [];

        if (in_array('product_id', $columns, true)) {
            $data['product_id'] = $productId;
        }
        if (in_array('user_id', $columns, true)) {
            $data['user_id'] = $userId;
        }
        if (in_array('activity_type', $columns, true)) {
            $data['activity_type'] = 'Damaged-Stock-In';
        }
        if (in_array('quantity', $columns, true)) {
            $data['quantity'] = $quantity;
        }
        if (in_array('reference', $columns, true)) {
            $data['reference'] = $reference;
        }
        if (in_array('notes', $columns, true) && $notes !== null && $notes !== '') {
            $data['notes'] = $notes;
        }
        if (in_array('activity_date', $columns, true)) {
            $data['activity_date'] = date('Y-m-d H:i:s');
        }
        if (in_array('location_from', $columns, true)) {
            $data['location_from'] = 'Damaged Returns';
        }
        if (in_array('location_to', $columns, true)) {
            $data['location_to'] = 'Main Stock';
        }
        if (in_array('sku', $columns, true)) {
            $data['sku'] = $sku;
        }

        if (empty($data)) {
            return;
        }

        $fields = array_keys($data);
        $placeholders = array_map(fn($field) => ':' . $field, $fields);
        $sql = 'INSERT INTO product_activity (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        foreach ($data as $field => $value) {
            $stmt->bindValue(':' . $field, $value);
        }
        $stmt->execute();
    } catch (Exception $e) {
        error_log('Failed to log product activity: ' . $e->getMessage());
    }
}

function ensureDamagedReturnPo(PDO $pdo, array $inspection, int $userId, ?float $costPrice, ?float $salePrice, float $restockQty): array
{
    $poId = isset($inspection['po_id']) ? (int)$inspection['po_id'] : 0;
    $poNumber = $inspection['po_number'] ?? null;

    if ($poId > 0) {
        if (!$poNumber) {
            try {
                $poLookup = $pdo->prepare('SELECT po_number FROM purchase_orders WHERE po_id = :po_id LIMIT 1');
                $poLookup->execute([':po_id' => $poId]);
                $foundNumber = $poLookup->fetchColumn();
                if ($foundNumber) {
                    $poNumber = $foundNumber;
                }
            } catch (Exception $e) {
                error_log('Failed to resolve PO number for damaged inspection: ' . $e->getMessage());
            }
        }
        return ['po_id' => $poId, 'po_number' => $poNumber];
    }

    try {
        $poNumber = 'PO-DMG-' . date('YmdHis');
        $unitBase = $costPrice ?? $salePrice ?? 0.0;
        $restock = max(0.0, $restockQty);
        $totalAmount = round($unitBase * $restock, 2);
        $remark = 'สร้างจากสินค้าชำรุดบางส่วน ' . ($inspection['return_code'] ?? '');

        $createPo = $pdo->prepare("INSERT INTO purchase_orders (
                po_number, supplier_id, order_date, total_amount,
                ordered_by, status, remark, created_at,
                currency_id, exchange_rate, total_amount_original, total_amount_base
            ) VALUES (
                :po_number, NULL, NOW(), :total_amount,
                :ordered_by, 'completed', :remark, NOW(),
                1, 1, :total_amount, :total_amount
            )");

        $createPo->execute([
            ':po_number' => $poNumber,
            ':total_amount' => $totalAmount,
            ':ordered_by' => $userId,
            ':remark' => $remark
        ]);

        $newPoId = (int)$pdo->lastInsertId();
        if ($newPoId <= 0) {
            return ['po_id' => 0, 'po_number' => null];
        }

        return ['po_id' => $newPoId, 'po_number' => $poNumber];
    } catch (Exception $e) {
        error_log('Failed to create PO for damaged inspection: ' . $e->getMessage());
        return ['po_id' => 0, 'po_number' => null];
    }
}

function ensureDamagedPurchaseOrderItem(PDO $pdo, int $poId, int $productId, float $restockQty, ?float $costPrice, ?float $salePrice): ?int
{
    if ($poId <= 0 || $productId <= 0 || $restockQty <= 0) {
        return null;
    }

    try {
        $lookup = $pdo->prepare('SELECT item_id FROM purchase_order_items WHERE po_id = :po_id AND product_id = :product_id ORDER BY item_id DESC LIMIT 1');
        $lookup->execute([
            ':po_id' => $poId,
            ':product_id' => $productId
        ]);
        $existingItemId = $lookup->fetchColumn();
        if ($existingItemId) {
            return (int)$existingItemId;
        }

        $pricePerUnit = $costPrice ?? $salePrice ?? 0.0;
        $saleValue = $salePrice ?? $pricePerUnit;
        $quantity = round($restockQty, 2);
        $totalAmount = round($pricePerUnit * $quantity, 2);

        $insert = $pdo->prepare('INSERT INTO purchase_order_items (
                po_id, product_id, qty, price_per_unit, sale_price, total, created_at
            ) VALUES (
                :po_id, :product_id, :qty, :price_per_unit, :sale_price, :total, NOW()
            )');

        $insert->execute([
            ':po_id' => $poId,
            ':product_id' => $productId,
            ':qty' => $quantity,
            ':price_per_unit' => $pricePerUnit,
            ':sale_price' => $saleValue,
            ':total' => $totalAmount
        ]);

        $itemId = (int)$pdo->lastInsertId();
        return $itemId > 0 ? $itemId : null;
    } catch (Exception $e) {
        error_log('Failed to ensure purchase order item for damaged inspection: ' . $e->getMessage());
        return null;
    }
}

function insertDamagedReceiveMovement(PDO $pdo, int $itemId, int $poId, float $restockQty, int $userId, string $newSku, ?string $notes, string $returnCode, ?string $expiryDate): void
{
    if ($itemId <= 0 || $poId <= 0 || $restockQty <= 0) {
        return;
    }

    $restock = round($restockQty, 2);
    $notesLine = trim((string)$notes);
    $remarkParts = [
        'รับสินค้าคืน (สินค้าชำรุดบางส่วน)',
        'SKU ใหม่: ' . $newSku
    ];

    if ($returnCode !== '') {
        $remarkParts[] = 'อ้างอิงคืนสินค้า: ' . $returnCode;
    }

    if ($notesLine !== '') {
        $remarkParts[] = $notesLine;
    }

    $remarkParts[] = 'บันทึกโดยระบบตรวจสอบสินค้าชำรุด';
    $remark = implode(' | ', array_filter($remarkParts, static fn($part) => $part !== ''));

    try {
        $insert = $pdo->prepare('INSERT INTO receive_items (
                item_id, po_id, receive_qty, expiry_date,
                remark_color, remark_split, remark, created_by, created_at
            ) VALUES (
                :item_id, :po_id, :receive_qty, :expiry_date,
                NULL, NULL, :remark, :created_by, NOW()
            )');

        $insert->execute([
            ':item_id' => $itemId,
            ':po_id' => $poId,
            ':receive_qty' => $restock,
            ':expiry_date' => $expiryDate ?: null,
            ':remark' => $remark,
            ':created_by' => $userId
        ]);
    } catch (Exception $e) {
        error_log('Failed to insert receive movement for damaged inspection: ' . $e->getMessage());
    }
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// อ่าน action จาก GET, POST form-data, หรือ JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// ถ้าไม่พบ action ให้ลอง parse JSON body
if (!$action && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
}

// ========== GET RETURN REASONS ==========
if ($action === 'get_reasons') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM return_reasons 
            WHERE is_active = 1 
            ORDER BY category ASC, reason_name ASC
        ");
        $stmt->execute();
        $reasons = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $reasons]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== SEARCH PO BY PO_NUMBER OR TRACKING NUMBER ==========
if ($action === 'search_po') {
    try {
        $keyword = $_GET['keyword'] ?? '';
        
        $stmt = $pdo->prepare("
            SELECT 
                po.po_id,
                po.po_number,
                po.created_at,
                s.supplier_name,
                po.remark,
                COUNT(poi.item_id) as total_items
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
            LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
            WHERE po.po_number LIKE :keyword 
                OR po.remark LIKE :keyword
            GROUP BY po.po_id
            ORDER BY po.created_at DESC
            LIMIT 20
        ");
        
        $stmt->execute([':keyword' => "%{$keyword}%"]);
        $pos = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $pos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== SEARCH BY ISSUE TAG (เลขแท็ค) ==========
if ($action === 'search_by_issue_tag') {
    try {
        $keyword = $_GET['keyword'] ?? '';
        
        if (strlen($keyword) < 1) {
            echo json_encode(['status' => 'success', 'data' => []]);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                so.sale_order_id as so_id,
                so.issue_tag,
                so.created_at,
                u.name as customer_name,
                COALESCE(so.total_items, 0) as total_items
            FROM sales_orders so
            LEFT JOIN users u ON so.issued_by = u.user_id
            WHERE so.issue_tag LIKE :keyword 
            ORDER BY so.created_at DESC
            LIMIT 20
        ");
        
        $stmt->execute([':keyword' => "%{$keyword}%"]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $orders]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== GET PO ITEMS ==========
if ($action === 'get_po_items') {
    try {
        $po_id = $_GET['po_id'] ?? null;
        
        if (!$po_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'PO ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                poi.item_id,
                poi.po_id,
                poi.product_id,
                p.sku,
                p.barcode,
                p.name as product_name,
                p.image,
                poi.price_per_unit,
                ri.expiry_date,
                COALESCE(SUM(ret.return_qty), 0) as returned_qty,
                ri.receive_qty - COALESCE(SUM(ret.return_qty), 0) as available_qty,
                ri.receive_qty,
                l.location_id,
                l.row_code,
                l.bin,
                l.shelf
            FROM purchase_order_items poi
            LEFT JOIN products p ON poi.product_id = p.product_id
            LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
            LEFT JOIN product_location l ON p.product_id = l.product_id
            LEFT JOIN returned_items ret ON poi.item_id = ret.item_id AND ret.return_status != 'rejected'
            WHERE poi.po_id = :po_id
            GROUP BY poi.item_id
            ORDER BY poi.item_id ASC
        ");
        
        $stmt->execute([':po_id' => $po_id]);
        $items = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== GET SALES ORDER ITEMS ==========
if ($action === 'get_sales_order_items') {
    try {
        $so_id = $_GET['so_id'] ?? null;
        
        if (!$so_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Sales Order ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                ii.issue_id AS si_id,
                ii.sale_order_id AS so_id,
                ii.product_id,
                p.sku,
                p.barcode,
                p.name AS product_name,
                p.image,
                ii.issue_qty,
                COALESCE(ret.total_returned, 0) AS returned_qty,
                CASE WHEN COALESCE(ret.total_returned, 0) > 0 THEN 0 ELSE ii.issue_qty END AS available_qty,
                CASE WHEN COALESCE(ret.total_returned, 0) > 0 THEN 1 ELSE 0 END AS already_returned
            FROM issue_items ii
            LEFT JOIN products p ON ii.product_id = p.product_id
            LEFT JOIN (
                SELECT 
                    item_id,
                    SUM(return_qty) AS total_returned
                FROM returned_items
                WHERE return_from_sales = 1 AND return_status IN ('pending', 'approved', 'completed')
                GROUP BY item_id
            ) ret ON ret.item_id = ii.issue_id
            WHERE ii.sale_order_id = :so_id
            ORDER BY ii.issue_id ASC
        ");
        
        $stmt->execute([':so_id' => $so_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== CREATE RETURN ==========
if ($action === 'create_return') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $so_id = $data['so_id'] ?? null;
        $po_id = $data['po_id'] ?? null;
        $item_id = $data['item_id'] ?? null;
        $product_id = $data['product_id'] ?? null;
        $return_qty = $data['return_qty'] ?? 0;
        $reason_id = $data['reason_id'] ?? null;
        $notes = $data['notes'] ?? '';
        
        if (!$item_id || !$product_id || !$return_qty || !$reason_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit;
        }
        
        // Get product details
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :product_id");
        $stmt->execute([':product_id' => $product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        // Determine if return is from sales or purchase
        $return_from_sales = !empty($so_id) ? 1 : 0;
        $po_number = null;
        $original_qty = 0;
        $issue_tag = null;
        $cost_price = null;
        $sale_price = null;
        $receive_id = null;
        
        if ($return_from_sales) {
            // Get sales order item details
            $stmt = $pdo->prepare("
                SELECT ii.*, so.issue_tag 
                FROM issue_items ii
                JOIN sales_orders so ON ii.sale_order_id = so.sale_order_id
                WHERE ii.issue_id = :item_id AND ii.sale_order_id = :so_id
            ");
            $stmt->execute([':item_id' => $item_id, ':so_id' => $so_id]);
            $order_item = $stmt->fetch();
            
            if (!$order_item) {
                throw new Exception('Sales order item not found');
            }

            $duplicateCheck = $pdo->prepare("SELECT COUNT(*) FROM returned_items WHERE return_from_sales = 1 AND item_id = :item_id AND return_status IN ('pending', 'approved', 'completed')");
            $duplicateCheck->execute([':item_id' => $item_id]);
            if ((int)$duplicateCheck->fetchColumn() > 0) {
                throw new Exception('รายการนี้มีการตีกลับแล้ว ไม่สามารถตีกลับซ้ำ');
            }
            
            $original_qty = $order_item['issue_qty'];
            $issue_tag = $order_item['issue_tag'];
            $cost_price = isset($order_item['cost_price']) ? (float)$order_item['cost_price'] : null;
            $sale_price = isset($order_item['sale_price']) ? (float)$order_item['sale_price'] : null;
            $receive_id = isset($order_item['receive_id']) ? (int)$order_item['receive_id'] : null;
            
            // ค้นหา po_id ที่มี product นี้ (สำหรับบันทึกวิบาก)
            if (!$po_id) {
                $stmt = $pdo->prepare("
                    SELECT MAX(po.po_id) as po_id, po.po_number
                    FROM purchase_orders po
                    JOIN purchase_order_items poi ON po.po_id = poi.po_id
                    WHERE poi.product_id = :product_id
                    ORDER BY po.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([':product_id' => $product_id]);
                $po_result = $stmt->fetch();
                $po_id = $po_result['po_id'] ?? null;
                $po_number = $po_result['po_number'] ?? null;
            }
        } else {
            // Get receive item details
            $stmt = $pdo->prepare("
                SELECT ri.*, poi.price_per_unit, poi.sale_price, po.po_number
                FROM receive_items ri
                JOIN purchase_order_items poi ON ri.item_id = poi.item_id
                JOIN purchase_orders po ON poi.po_id = po.po_id
                WHERE ri.item_id = :item_id AND ri.po_id = :po_id
            ");
            $stmt->execute([':item_id' => $item_id, ':po_id' => $po_id]);
            $order_item = $stmt->fetch();

            // Fallback: allow damaged flow before any receive record exists
            if (!$order_item) {
                $fallbackStmt = $pdo->prepare("
                    SELECT poi.*, po.po_number
                    FROM purchase_order_items poi
                    JOIN purchase_orders po ON poi.po_id = po.po_id
                    WHERE poi.item_id = :item_id AND poi.po_id = :po_id
                    LIMIT 1
                ");
                $fallbackStmt->execute([':item_id' => $item_id, ':po_id' => $po_id]);
                $fallbackItem = $fallbackStmt->fetch(PDO::FETCH_ASSOC);

                if ($fallbackItem) {
                    $order_item = $fallbackItem;
                    $order_item['expiry_date'] = null;
                    $order_item['receive_id'] = null;
                }
            }

            if (!$order_item) {
                throw new Exception('Purchase order item not found');
            }

            $original_qty = isset($order_item['receive_qty']) ? $order_item['receive_qty'] : ($order_item['qty'] ?? 0);
            $po_number = $order_item['po_number'] ?? null;
            $cost_price = isset($order_item['price_per_unit']) ? (float)$order_item['price_per_unit'] : null;
            $sale_price = isset($order_item['sale_price']) ? (float)$order_item['sale_price'] : null;
            $receive_id = isset($order_item['receive_id']) ? (int)$order_item['receive_id'] : null;
        }
        
        // Get reason details
        $stmt = $pdo->prepare("SELECT * FROM return_reasons WHERE reason_id = :reason_id");
        $stmt->execute([':reason_id' => $reason_id]);
        $reason = $stmt->fetch();
        
        if (!$reason) {
            throw new Exception('Return reason not found');
        }
        
        // Generate return code - ใช้ timestamp + random เพื่อหลีกเลี่ยงซ้ำ
        $timestamp = microtime(true) * 10000; // Convert to unique number
        $random = mt_rand(100, 999);
        $return_code = 'RET-' . date('Ymd') . '-' . str_pad(intval($timestamp) % 9999, 4, '0', STR_PAD_LEFT);
        
        // Insert return record
        $stmt = $pdo->prepare("
            INSERT INTO returned_items (
                return_code, po_id, po_number, receive_id, so_id, issue_tag, item_id, product_id, 
                product_name, sku, barcode, original_qty, return_qty, reason_id, reason_name,
                is_returnable, return_status, return_from_sales, image_path, notes, expiry_date,
                location_id, created_by
            ) VALUES (
                :return_code, :po_id, :po_number, :receive_id, :so_id, :issue_tag, :item_id, :product_id,
                :product_name, :sku, :barcode, :original_qty, :return_qty, :reason_id, :reason_name,
                :is_returnable, 'pending', :return_from_sales, :image_path, :notes, :expiry_date,
                :location_id, :created_by
            )
        ");
        
        $stmt->execute([
            ':return_code' => $return_code,
            ':po_id' => $po_id,
            ':po_number' => $po_number,
            ':receive_id' => $receive_id,
            ':so_id' => $so_id,
            ':issue_tag' => $issue_tag,
            ':item_id' => $item_id,
            ':product_id' => $product_id,
            ':product_name' => $product['name'],
            ':sku' => $product['sku'],
            ':barcode' => $product['barcode'] ?? null,
            ':original_qty' => $original_qty,
            ':return_qty' => $return_qty,
            ':reason_id' => $reason_id,
            ':reason_name' => $reason['reason_name'],
            ':is_returnable' => $reason['is_returnable'],
            ':return_from_sales' => $return_from_sales,
            ':image_path' => $product['image'] ?? null,
            ':notes' => $notes,
            ':expiry_date' => $order_item['expiry_date'] ?? null,
            ':location_id' => null,
            ':created_by' => $user_id
        ]);
        
        $return_id = $pdo->lastInsertId();
        $reasonNameForQueue = trim((string)($reason['reason_name'] ?? ''));
        if ($reasonNameForQueue === 'สินค้าชำรุดบางส่วน') {
            try {
                $queueStmt = $pdo->prepare("INSERT INTO damaged_return_inspections (
                        return_id, return_code, product_id, product_name, sku, barcode, receive_id, expiry_date, po_id, po_number, return_qty,
                        reason_id, reason_name, status, new_sku, defect_notes, created_by, cost_price, sale_price
                    ) VALUES (
                        :return_id, :return_code, :product_id, :product_name, :sku, :barcode, :receive_id, :expiry_date, :po_id, :po_number, :return_qty,
                        :reason_id, :reason_name, 'pending', NULL, :defect_notes, :created_by, :cost_price, :sale_price
                    )");
                $queueStmt->execute([
                    ':return_id' => $return_id,
                    ':return_code' => $return_code,
                    ':product_id' => $product_id,
                    ':product_name' => $product['name'],
                    ':sku' => $product['sku'],
                    ':barcode' => $product['barcode'] ?? null,
                    ':receive_id' => $receive_id,
                    ':expiry_date' => $order_item['expiry_date'] ?? null,
                    ':po_id' => $po_id,
                    ':po_number' => $po_number,
                    ':return_qty' => $return_qty,
                    ':reason_id' => $reason_id,
                    ':reason_name' => $reasonNameForQueue,
                    ':defect_notes' => $notes,
                    ':created_by' => $user_id,
                    ':cost_price' => $cost_price,
                    ':sale_price' => $sale_price
                ]);
            } catch (PDOException $inspectionException) {
                if ($inspectionException->getCode() !== '23000') {
                    throw $inspectionException;
                }
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Return created successfully',
            'return_id' => $return_id,
            'return_code' => $return_code
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== GET RETURNS LIST ==========
if ($action === 'get_returns') {
    try {
        $status = $_GET['status'] ?? 'all';
        $is_returnable = $_GET['is_returnable'] ?? 'all';
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        $sql = "
            SELECT 
                ret.*,
                u.name as created_by_name,
                u2.name as approved_by_name
            FROM returned_items ret
            LEFT JOIN users u ON ret.created_by = u.user_id
            LEFT JOIN users u2 ON ret.approved_by = u2.user_id
            WHERE 1=1
        ";
        
        if ($status !== 'all') {
            $sql .= " AND ret.return_status = '" . $pdo->quote($status) . "'";
        }
        
        if ($is_returnable !== 'all') {
            $sql .= " AND ret.is_returnable = " . (int)$is_returnable;
        }
        
        $sql .= " ORDER BY ret.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $pdo->query($sql);
        $returns = $stmt->fetchAll();
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM returned_items WHERE 1=1";
        if ($status !== 'all') {
            $count_sql .= " AND return_status = '" . $pdo->quote($status) . "'";
        }
        if ($is_returnable !== 'all') {
            $count_sql .= " AND is_returnable = " . (int)$is_returnable;
        }
        
        $count_stmt = $pdo->query($count_sql);
        $total = $count_stmt->fetch()['total'];
        
        echo json_encode([
            'status' => 'success',
            'data' => $returns,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== DAMAGED RETURN - LIST QUEUE ==========
if ($action === 'list_damaged_inspections') {
    try {
        $status = $_GET['status'] ?? 'pending';
        $conditions = [];
        $params = [];

        if ($status !== 'all') {
            $conditions[] = 'di.status = :status';
            $params[':status'] = $status;
        }

        $sql = "
            SELECT 
                di.*,
                ri.return_qty AS original_return_qty,
                ri.return_status,
                u.name AS created_by_name,
                u2.name AS inspected_by_name,
                u3.name AS restocked_by_name
            FROM damaged_return_inspections di
            LEFT JOIN returned_items ri ON di.return_id = ri.return_id
            LEFT JOIN users u ON di.created_by = u.user_id
            LEFT JOIN users u2 ON di.inspected_by = u2.user_id
            LEFT JOIN users u3 ON di.restocked_by = u3.user_id
        ";

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY di.created_at DESC, di.inspection_id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $inspections]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== DAMAGED RETURN - DETAIL ==========
if ($action === 'get_damaged_inspection') {
    try {
        $inspection_id = $_GET['inspection_id'] ?? null;
        if (!$inspection_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Inspection ID is required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT 
                di.*,
                ri.return_qty AS original_return_qty,
                ri.return_status,
                ri.notes AS return_notes,
                ri.expiry_date AS return_expiry_date,
                u.name AS created_by_name,
                u2.name AS inspected_by_name,
                u3.name AS restocked_by_name
            FROM damaged_return_inspections di
            JOIN returned_items ri ON di.return_id = ri.return_id
            LEFT JOIN users u ON di.created_by = u.user_id
            LEFT JOIN users u2 ON di.inspected_by = u2.user_id
            LEFT JOIN users u3 ON di.restocked_by = u3.user_id
            WHERE di.inspection_id = :inspection_id");
        $stmt->execute([':inspection_id' => $inspection_id]);
        $inspection = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inspection) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Inspection not found']);
            exit;
        }

        $productStmt = $pdo->prepare('SELECT * FROM products WHERE product_id = :product_id LIMIT 1');
        $productStmt->execute([':product_id' => $inspection['product_id']]);
        $inspection['source_product'] = $productStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        echo json_encode(['status' => 'success', 'data' => $inspection]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== DAMAGED RETURN - PROCESS ==========
if ($action === 'process_damaged_inspection') {
    try {
        $payload = json_decode(file_get_contents('php://input'), true);
        $inspection_id = $payload['inspection_id'] ?? null;
        $disposition = $payload['disposition'] ?? null;
        $restockQty = isset($payload['restock_qty']) ? (float)$payload['restock_qty'] : 0.0;
        $inspectionNotes = trim((string)($payload['inspection_notes'] ?? ''));
        $costPriceInput = $payload['cost_price'] ?? null;
        $salePriceInput = $payload['sale_price'] ?? null;

        if (!$inspection_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Inspection ID is required']);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT 
                di.*,
                ri.return_id,
                ri.return_qty AS original_return_qty,
                ri.return_status,
                ri.notes AS return_notes,
                ri.expiry_date AS return_expiry_date,
                p.name AS source_product_name,
                p.sku AS source_product_sku,
                p.barcode AS source_barcode,
                p.unit AS source_unit,
                p.image AS source_image,
                p.remark_color AS source_remark_color,
                p.remark_split AS source_remark_split,
                p.product_category_id AS source_product_category_id,
                p.category_name AS source_category_name
            FROM damaged_return_inspections di
            JOIN returned_items ri ON di.return_id = ri.return_id
            JOIN products p ON di.product_id = p.product_id
            WHERE di.inspection_id = :inspection_id
            FOR UPDATE");
        $stmt->execute([':inspection_id' => $inspection_id]);
        $inspection = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inspection) {
            throw new Exception('Inspection not found');
        }

        if ($inspection['status'] !== 'pending') {
            throw new Exception('Inspection already processed');
        }

        $newSku = buildDefectSku((string)($inspection['sku'] ?? ''));

        $costPrice = null;
        if (array_key_exists('cost_price', $payload)) {
            if ($costPriceInput !== null && $costPriceInput !== '') {
                $costPrice = round((float)$costPriceInput, 2);
            }
        } elseif ($inspection['cost_price'] !== null) {
            $costPrice = round((float)$inspection['cost_price'], 2);
        }

        $salePrice = null;
        if (array_key_exists('sale_price', $payload)) {
            if ($salePriceInput !== null && $salePriceInput !== '') {
                $salePrice = round((float)$salePriceInput, 2);
            }
        } elseif ($inspection['sale_price'] !== null) {
            $salePrice = round((float)$inspection['sale_price'], 2);
        }

        $availableQty = isset($inspection['return_qty']) ? (float)$inspection['return_qty'] : 0.0;
        if ($availableQty <= 0) {
            throw new Exception('Invalid return quantity for inspection');
        }

        if ($restockQty <= 0) {
            $restockQty = $availableQty;
        }

        if ($restockQty <= 0 || $restockQty > $availableQty) {
            throw new Exception('จำนวนที่จะนำกลับเข้าสต๊อกต้องมากกว่า 0 และไม่เกินจำนวนที่ตีกลับ');
        }

        $restockQty = round($restockQty, 2);

        $existingProduct = findProductBySku($pdo, $newSku);
        if ($existingProduct && (int)$existingProduct['product_id'] === (int)$inspection['product_id']) {
            $newProductId = (int)$existingProduct['product_id'];
        } elseif ($existingProduct) {
            $newProductId = (int)$existingProduct['product_id'];
            if ((int)$existingProduct['is_active'] !== 1) {
                $pdo->prepare('UPDATE products SET is_active = 1 WHERE product_id = :product_id')
                    ->execute([':product_id' => $newProductId]);
            }
        } else {
            $sourceProduct = [
                'name' => $inspection['source_product_name'],
                'barcode' => $inspection['source_barcode'],
                'unit' => $inspection['source_unit'],
                'image' => $inspection['source_image'],
                'remark_color' => $inspection['source_remark_color'],
                'remark_split' => $inspection['source_remark_split'],
                'product_category_id' => $inspection['source_product_category_id'],
                'category_name' => $inspection['source_category_name']
            ];
            $newProductId = createDefectProduct($pdo, $sourceProduct, $newSku, (int)$user_id);
        }

        // Normalize disposition label and downstream behavior flag
        $isSellable = ($disposition !== 'discard');
        $dispositionLabel = $isSellable ? '[ขายได้]' : '[ทิ้ง/ใช้ไม่ได้]';

        // Strip any existing disposition prefixes and reapply the latest selection
        $existingDefectNotes = trim((string)($inspection['defect_notes'] ?? ''));
        $existingDefectNotes = preg_replace('/^\[(ขายได้|ทิ้ง\/ใช้ไม่ได้)\]\s*/u', '', $existingDefectNotes);

        $notesSegments = [];
        if ($existingDefectNotes !== '') {
            $notesSegments[] = "$dispositionLabel " . $existingDefectNotes;
        }

        $newNoteLine = $inspectionNotes !== '' ? $inspectionNotes : '';
        if ($newNoteLine !== '') {
            $notesSegments[] = "$dispositionLabel " . $newNoteLine;
        } elseif (empty($notesSegments)) {
            // Keep at least the disposition label to reflect the latest choice
            $notesSegments[] = $dispositionLabel;
        }

        $combinedNotes = implode("\n", $notesSegments);

        $expiryDate = $inspection['expiry_date'] ?? $inspection['return_expiry_date'] ?? null;
        if (($inspection['expiry_date'] ?? null) === null && $expiryDate !== null) {
            $setExpiry = $pdo->prepare('UPDATE damaged_return_inspections SET expiry_date = :expiry_date WHERE inspection_id = :inspection_id');
            $setExpiry->execute([
                ':expiry_date' => $expiryDate,
                ':inspection_id' => $inspection_id
            ]);
            $inspection['expiry_date'] = $expiryDate;
        }

        $poIdForMovement = 0;
        $poNumberForMovement = $inspection['po_number'] ?? null;
        $receiveItemId = null;

        // Only create PO/items/movements when the item is sellable
        if ($isSellable) {
            $poContext = ensureDamagedReturnPo($pdo, $inspection, (int)$user_id, $costPrice, $salePrice, $restockQty);
            $poIdForMovement = (int)($poContext['po_id'] ?? 0);
            $poNumberForMovement = $poContext['po_number'] ?? ($inspection['po_number'] ?? null);

            if ($poIdForMovement > 0) {
                $receiveItemId = ensureDamagedPurchaseOrderItem(
                    $pdo,
                    $poIdForMovement,
                    $newProductId,
                    $restockQty,
                    $costPrice,
                    $salePrice
                );
            }
        }

        $updateInspection = $pdo->prepare("UPDATE damaged_return_inspections SET
                status = 'completed',
                new_sku = :new_sku,
                new_product_id = :new_product_id,
                cost_price = :cost_price,
                sale_price = :sale_price,
                restock_qty = :restock_qty,
                defect_notes = :defect_notes,
                po_id = :po_id,
                po_number = :po_number,
                inspected_by = :inspected_by,
                inspected_at = NOW(),
                restocked_by = :restocked_by,
                restocked_at = NOW(),
                updated_at = NOW()
            WHERE inspection_id = :inspection_id");

        $updateInspection->execute([
            ':new_sku' => $newSku,
            ':new_product_id' => $newProductId,
            ':cost_price' => $costPrice,
            ':sale_price' => $salePrice,
            ':restock_qty' => $restockQty,
            ':defect_notes' => $combinedNotes !== '' ? $combinedNotes : null,
            ':po_id' => $poIdForMovement > 0 ? $poIdForMovement : null,
            ':po_number' => $poNumberForMovement,
            ':inspected_by' => $user_id,
            ':restocked_by' => $user_id,
            ':inspection_id' => $inspection_id
        ]);

        if ($isSellable && $poIdForMovement > 0 && ((int)($inspection['po_id'] ?? 0) !== $poIdForMovement || ($inspection['po_number'] ?? null) !== $poNumberForMovement)) {
            $updateReturnPo = $pdo->prepare('UPDATE returned_items SET po_id = :po_id, po_number = :po_number WHERE return_id = :return_id');
            $updateReturnPo->execute([
                ':po_id' => $poIdForMovement,
                ':po_number' => $poNumberForMovement,
                ':return_id' => $inspection['return_id']
            ]);
        }

        $noteLine = "\n[INSPECTED] เปลี่ยน SKU เป็น {$newSku} ({$restockQty}) โดยผู้ใช้ {$user_id} เวลา " . date('Y-m-d H:i:s');
        $isReturnableValue = $isSellable ? 1 : 0;
        $updateReturn = $pdo->prepare("UPDATE returned_items SET 
                return_status = 'completed',
                is_returnable = :is_returnable,
                notes = CONCAT(COALESCE(notes, ''), :note_append),
                updated_at = NOW()
            WHERE return_id = :return_id");
        $updateReturn->execute([
            ':is_returnable' => $isReturnableValue,
            ':note_append' => $noteLine,
            ':return_id' => $inspection['return_id']
        ]);

        if ($isSellable && $receiveItemId !== null && $poIdForMovement > 0) {
            insertDamagedReceiveMovement(
                $pdo,
                $receiveItemId,
                $poIdForMovement,
                $restockQty,
                (int)$user_id,
                $newSku,
                $combinedNotes !== '' ? $combinedNotes : null,
                (string)($inspection['return_code'] ?? ''),
                $expiryDate
            );
        }

        if ($isSellable) {
            // Only log stock movement when item is sellable
            logProductActivity(
                $pdo,
                $newProductId,
                (int)$user_id,
                $restockQty,
                (string)($inspection['return_code'] ?? ''),
                $newSku,
                $combinedNotes !== '' ? $combinedNotes : null
            );
        }

        // Update PO status to check if all items have been processed
        // Get the original PO ID from the inspection record
        $originalPoId = $inspection['po_id'] ?? null;
        if ($originalPoId) {
            updatePOStatusInline($pdo, $originalPoId);
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกการตรวจสอบสินค้าเรียบร้อย',
            'new_product_id' => $newProductId
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== APPROVE RETURN ==========
if ($action === 'approve_return') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $return_id = $data['return_id'] ?? null;
        
        if (!$return_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Return ID is required']);
            exit;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get return item details
        $stmt = $pdo->prepare("SELECT * FROM returned_items WHERE return_id = :return_id");
        $stmt->execute([':return_id' => $return_id]);
        $return_item = $stmt->fetch();
        
        if (!$return_item) {
            throw new Exception('Return item not found');
        }
        
        $reasonName = trim((string)($return_item['reason_name'] ?? ''));
        $isDamagedPartial = ($reasonName === 'สินค้าชำรุดบางส่วน');
        
        // Update return status
        $stmt = $pdo->prepare("
            UPDATE returned_items 
            SET return_status = 'approved', 
                approved_by = :approved_by,
                approved_at = NOW()
            WHERE return_id = :return_id
        ");
        
        $stmt->execute([
            ':return_id' => $return_id,
            ':approved_by' => $user_id
        ]);
        
        if ($isDamagedPartial) {
            $poIdForQueue = $return_item['po_id'] ?? null;
            $poNumberForQueue = $return_item['po_number'] ?? null;
            $costPriceForQueue = null;
            $salePriceForQueue = null;

            if ((int)($return_item['return_from_sales'] ?? 0) === 1) {
                $issueDataStmt = $pdo->prepare("SELECT sale_price, cost_price FROM issue_items WHERE issue_id = :issue_id LIMIT 1");
                $issueDataStmt->execute([':issue_id' => $return_item['item_id']]);
                $issueData = $issueDataStmt->fetch(PDO::FETCH_ASSOC);
                if ($issueData) {
                    $salePriceForQueue = isset($issueData['sale_price']) ? (float)$issueData['sale_price'] : null;
                    $costPriceForQueue = isset($issueData['cost_price']) ? (float)$issueData['cost_price'] : null;
                }
            } else {
                $poItemStmt = $pdo->prepare("SELECT price_per_unit, sale_price FROM purchase_order_items WHERE item_id = :item_id LIMIT 1");
                $poItemStmt->execute([':item_id' => $return_item['item_id']]);
                $poItemData = $poItemStmt->fetch(PDO::FETCH_ASSOC);
                if ($poItemData) {
                    $costPriceForQueue = isset($poItemData['price_per_unit']) ? (float)$poItemData['price_per_unit'] : null;
                    $salePriceForQueue = isset($poItemData['sale_price']) ? (float)$poItemData['sale_price'] : null;
                }
            }

            $inspectionCheck = $pdo->prepare("SELECT inspection_id FROM damaged_return_inspections WHERE return_id = :return_id LIMIT 1");
            $inspectionCheck->execute([':return_id' => $return_id]);
            if (!$inspectionCheck->fetch()) {
                $queueStmt = $pdo->prepare("INSERT INTO damaged_return_inspections (
                        return_id, return_code, product_id, product_name, sku, barcode, expiry_date, po_id, po_number, return_qty,
                        reason_id, reason_name, status, new_sku, defect_notes, created_by, cost_price, sale_price
                    ) VALUES (
                        :return_id, :return_code, :product_id, :product_name, :sku, :barcode, :expiry_date, :po_id, :po_number, :return_qty,
                        :reason_id, :reason_name, 'pending', NULL, :defect_notes, :created_by, :cost_price, :sale_price
                    )");
                $queueStmt->execute([
                    ':return_id' => $return_item['return_id'],
                    ':return_code' => $return_item['return_code'],
                    ':product_id' => $return_item['product_id'],
                    ':product_name' => $return_item['product_name'],
                    ':sku' => $return_item['sku'],
                    ':barcode' => $return_item['barcode'] ?? null,
                    ':expiry_date' => $return_item['expiry_date'] ?? null,
                    ':po_id' => $poIdForQueue,
                    ':po_number' => $poNumberForQueue,
                    ':return_qty' => $return_item['return_qty'],
                    ':reason_id' => $return_item['reason_id'] ?? 0,
                    ':reason_name' => $reasonName,
                    ':defect_notes' => $return_item['notes'] ?? null,
                    ':created_by' => $return_item['created_by'] ?? null,
                    ':cost_price' => $costPriceForQueue,
                    ':sale_price' => $salePriceForQueue
                ]);
            }
        }
        
        // ถ้าเป็นสินค้าที่สามารถคืนสต็อกได้ ให้เพิ่มลงใน receive_items
        if ($return_item['is_returnable'] == 1 && !$isDamagedPartial) {
            // แน่ใจว่า return_qty เป็นค่าบวก
            $return_qty = abs((float)$return_item['return_qty']);
            
            // หาข้อมูล PO จากสินค้าที่ตีกลับ
            $po_id = $return_item['po_id'];
            error_log("DEBUG: approve_return - return_id={$return_id}, po_id={$po_id}, product_id={$return_item['product_id']}, return_qty={$return_qty}");
            
            // ถ้าไม่มี po_id ให้ค้นหาจากสินค้า (สร้าง PO สำหรับการคืน หรือใช้ PO สุดท้าย)
            if (!$po_id) {
                // ค้นหา PO ล่าสุดที่มีสินค้านี้
                $stmt = $pdo->prepare("
                    SELECT MAX(po.po_id) as po_id 
                    FROM purchase_orders po
                    JOIN purchase_order_items poi ON po.po_id = poi.po_id
                    WHERE poi.product_id = :product_id
                    ORDER BY po.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([':product_id' => $return_item['product_id']]);
                $po_result = $stmt->fetch();
                $po_id = $po_result['po_id'] ?? null;
                error_log("DEBUG: Found po_id from search: {$po_id}");
            }
            
            // เพิ่มจำนวนลงใน receive_items
            if ($po_id) {
                // ค้นหา receive item สำหรับ product นี้จาก PO นี้
                // receive_items มี item_id (FK to purchase_order_items) ไม่ใช่ product_id
                $stmt = $pdo->prepare("
                    SELECT ri.* FROM receive_items ri
                    JOIN purchase_order_items poi ON ri.item_id = poi.item_id
                    WHERE ri.po_id = :po_id AND poi.product_id = :product_id
                    LIMIT 1
                ");
                $stmt->execute([
                    ':po_id' => $po_id,
                    ':product_id' => $return_item['product_id']
                ]);
                $receive_item = $stmt->fetch();
                error_log("DEBUG: receive_item found: " . ($receive_item ? 'YES' : 'NO'));
                
                if ($receive_item) {
                    // Always record a new receive movement entry for returns
                    $base_remark = $receive_item['remark'] ?? '';
                    $clean_base = str_replace('ตีกลับ', '', $base_remark);
                    $clean_base = trim($clean_base);
                    $remark_lines = [];
                    if (!empty($clean_base)) {
                        $remark_lines[] = $clean_base;
                    }
                    $remark_lines[] = "รับคืนจากสินค้าตีกลับ: {$return_item['return_code']}";
                    $final_remark = implode("\n", $remark_lines);

                    $stmt = $pdo->prepare("
                        INSERT INTO receive_items (
                            po_id, item_id, receive_qty, expiry_date, remark, created_by, created_at
                        ) VALUES (
                            :po_id, :item_id, :receive_qty, :expiry_date, :remark, :created_by, NOW()
                        )
                    ");
                    $stmt->execute([
                        ':po_id' => $po_id,
                        ':item_id' => $receive_item['item_id'],
                        ':receive_qty' => $return_qty,
                        ':expiry_date' => $receive_item['expiry_date'] ?? null,
                        ':remark' => $final_remark,
                        ':created_by' => $user_id
                    ]);
                    error_log("DEBUG: INSERT receive_items (existing) - new ID: " . $pdo->lastInsertId());
                } else {
                    // ค้นหา item_id สำหรับ product นี้จาก PO นี้
                    $stmt = $pdo->prepare("
                        SELECT item_id FROM purchase_order_items 
                        WHERE po_id = :po_id AND product_id = :product_id
                        LIMIT 1
                    ");
                    $stmt->execute([
                        ':po_id' => $po_id,
                        ':product_id' => $return_item['product_id']
                    ]);
                    $poi_result = $stmt->fetch();
                    error_log("DEBUG: poi_result found: " . ($poi_result ? 'YES (item_id=' . $poi_result['item_id'] . ')' : 'NO'));
                    
                    if ($poi_result) {
                        // Create new receive_items record
                        $stmt = $pdo->prepare("
                            INSERT INTO receive_items (
                                po_id, item_id, receive_qty, expiry_date, remark, created_by, created_at
                            ) VALUES (
                                :po_id, :item_id, :receive_qty, :expiry_date, :remark, :created_by, NOW()
                            )
                        ");
                        $stmt->execute([
                            ':po_id' => $po_id,
                            ':item_id' => $poi_result['item_id'],
                            ':receive_qty' => $return_qty,
                            ':expiry_date' => $return_item['expiry_date'] ?? null,
                            ':remark' => "รับคืนจากสินค้าตีกลับ: {$return_item['return_code']}",
                            ':created_by' => $user_id
                        ]);
                        error_log("DEBUG: INSERT receive_items (new) - new ID: " . $pdo->lastInsertId());
                    } else {
                        error_log("ERROR: ไม่พบ item_id สำหรับ product_id={$return_item['product_id']} ใน po_id={$po_id}");
                    }
                }
            } else {
                error_log("ERROR: ไม่พบ po_id สำหรับ return_id={$return_id}");
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => $isDamagedPartial ? 'Damaged return queued for inspection' : 'Return approved successfully'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== REJECT RETURN ==========
if ($action === 'reject_return') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $return_id = $data['return_id'] ?? null;
        $reason = $data['reason'] ?? '';
        
        if (!$return_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Return ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE returned_items 
            SET return_status = 'rejected',
                notes = CONCAT(COALESCE(notes, ''), '\n[REJECTED] ', :reason, ' - by ', :approved_by, ' at ', NOW()),
                approved_by = :approved_by,
                approved_at = NOW()
            WHERE return_id = :return_id
        ");
        
        $stmt->execute([
            ':return_id' => $return_id,
            ':reason' => $reason,
            ':approved_by' => $user_id
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Return rejected successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== GET RETURN DETAILS ==========
if ($action === 'get_return') {
    try {
        $return_id = $_GET['return_id'] ?? null;
        
        if (!$return_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Return ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                ret.*,
                u.name as created_by_name,
                u2.name as approved_by_name
            FROM returned_items ret
            LEFT JOIN users u ON ret.created_by = u.user_id
            LEFT JOIN users u2 ON ret.approved_by = u2.user_id
            WHERE ret.return_id = :return_id
        ");
        
        $stmt->execute([':return_id' => $return_id]);
        $return = $stmt->fetch();
        
        if (!$return) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Return not found']);
            exit;
        }
        
        echo json_encode(['status' => 'success', 'data' => $return]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
