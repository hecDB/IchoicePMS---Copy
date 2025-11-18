<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db_connect.php';
require_once '../auth/auth_check.php';

// Check permission
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
if ($user_role !== 'admin' && $user_role !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ตรวจสอบข้อมูลพื้นฐาน
    if (empty($_POST['supplier_id']) || empty($_POST['order_date']) || empty($_POST['currency_id'])) {
        throw new Exception('กรุณากรอกข้อมูลเบื้องต้น');
    }

    // ตรวจสอบสินค้า
    if (empty($_POST['product_name']) || count($_POST['product_name']) === 0) {
        throw new Exception('กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ');
    }

    $supplier_id = (int)$_POST['supplier_id'];
    $order_date = $_POST['order_date'];
    $currency_id = (int)$_POST['currency_id'];
    $po_remark = $_POST['po_remark'] ?? '';
    $created_by = $_SESSION['user_id'];

    // ตรวจสอบซัพพลายเยอร์
    $stmt = $pdo->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$supplier_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('ซัพพลายเยอร์ไม่มีอยู่');
    }

    // ตรวจสอบสกุลเงิน
    $stmt = $pdo->prepare("SELECT currency_id FROM currencies WHERE currency_id = ? AND is_active = 1");
    $stmt->execute([$currency_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('สกุลเงินไม่มีอยู่');
    }

    // สร้างใบ PO - ใช้ status 'pending' และ remark 'ซื้อสินค้ามาใหม่'
    $po_status = 'pending';
    $final_remark = 'New Product Purchase';
    // ถ้ามีหมายเหตุเพิ่มเติมจากผู้ใช้ ให้เพิ่มเข้าไป
    if (!empty($po_remark)) {
        $final_remark .= ' (' . trim($po_remark) . ')';
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO purchase_orders 
        (supplier_id, order_date, status, ordered_by, remark, currency_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$supplier_id, $order_date, $po_status, $created_by, $final_remark, $currency_id]);

    $po_id = $pdo->lastInsertId();
    $po_number = 'PO-New-' . date('Y') . '-' . str_pad($po_id, 5, '0', STR_PAD_LEFT);
    
    // อัปเดต PO number
    $stmt = $pdo->prepare("UPDATE purchase_orders SET po_number = ? WHERE po_id = ?");
    $stmt->execute([$po_number, $po_id]);

    // บันทึกรายการสินค้า
    $product_names = $_POST['product_name'] ?? [];
    $categories = $_POST['category'] ?? [];
    $units = $_POST['unit'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    $product_images = $_FILES['product_image'] ?? [];

    $total_amount = 0;

    for ($i = 0; $i < count($product_names); $i++) {
        $product_name = trim($product_names[$i]);
        
        if (empty($product_name) || empty($quantities[$i]) || empty($unit_prices[$i])) {
            throw new Exception('กรุณากรอกข้อมูลสินค้าให้ครบถ้วน (ที่ ' . ($i + 1) . ')');
        }

        $category = trim($categories[$i] ?? '');
        $unit = trim($units[$i] ?? 'ชิ้น');
        $quantity = (float)$quantities[$i];
        $unit_price = (float)$unit_prices[$i];

        if ($quantity <= 0 || $unit_price < 0) {
            throw new Exception('จำนวนและราคาต้องมากกว่า 0 (ที่ ' . ($i + 1) . ')');
        }

        // จัดการการอัปโหลดรูปภาพ
        $product_image_data = null;
        if (isset($product_images['tmp_name'][$i]) && !empty($product_images['tmp_name'][$i])) {
            $file = $product_images['tmp_name'][$i];
            $error = $product_images['error'][$i];
            
            if ($error === UPLOAD_ERR_OK) {
                // ตรวจสอบไฟล์ประเภทรูปภาพ
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file);
                finfo_close($finfo);
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mime_type, $allowed_types)) {
                    throw new Exception('ประเภทรูปภาพไม่รองรับ (ที่ ' . ($i + 1) . ')');
                }
                
                // ตรวจสอบขนาดไฟล์ (max 10MB - จะถูกลดขนาดที่ client แล้ว)
                if (filesize($file) > 10 * 1024 * 1024) {
                    throw new Exception('ขนาดรูปภาพใหญ่เกินไป (ที่ ' . ($i + 1) . ')');
                }
                
                // อ่านรูปภาพและแปลงเป็น Base64
                $image_content = file_get_contents($file);
                $product_image_data = base64_encode($image_content);
            } elseif ($error !== UPLOAD_ERR_NO_FILE) {
                throw new Exception('เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ (ที่ ' . ($i + 1) . ')');
            }
        }

        $item_total = $quantity * $unit_price;
        $item_amount = $item_total;
        $total_amount += $item_amount;

        // สร้าง temp product
        $temp_status = 'pending_approval';
        $stmt = $pdo->prepare("
            INSERT INTO temp_products 
            (product_name, product_category, product_image, unit, status, po_id, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$product_name, $category, $product_image_data, $unit, $temp_status, $po_id, $created_by]);

        $temp_product_id = $pdo->lastInsertId();

        // บันทึก PO item
        $stmt = $pdo->prepare("
            INSERT INTO purchase_order_items 
            (po_id, product_id, temp_product_id, qty, price_per_unit, sale_price, total, currency_id) 
            VALUES (?, NULL, ?, ?, ?, 0, ?, ?)
        ");
        $stmt->execute([$po_id, $temp_product_id, $quantity, $unit_price, $item_amount, $currency_id]);
    }

    // อัปเดตยอดรวมใบ PO
    $stmt = $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE po_id = ?");
    $stmt->execute([$total_amount, $po_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'สร้างใบ PO สำเร็จ',
        'po_id' => $po_id,
        'po_number' => $po_number
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
