<?php
session_start();
require_once '../config/db_connect.php';
include '../templates/sidebar.php';
// if($_SESSION['user_role']!=='admin'){ http_response_code(403); exit; }

// เพิ่มสไตล์สำหรับปุ่ม debug
echo '<style>
.debug-button {
    position: fixed;
    top: 10px;
    right: 10px;
    background: #ff4444;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
    z-index: 9999;
}
.debug-button:hover {
    background: #cc0000;
}
</style>';

echo '<button class="debug-button" onclick="window.open(\'debug_logs.php\', \'_blank\')">🔍 Debug Logs</button>';

// ตรวจสอบ session และ user_id
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // ตั้งค่าเริ่มต้นสำหรับทดสอบ
    error_log("Warning: No user_id in session, using default value 1");
}
$user_id = $_SESSION['user_id'];
$message = "";
$uploadDir = __DIR__ . '/../images/'; // แก้ไข path ให้ถูกต้อง
$imgWebPath = 'images/';

// ตรวจสอบและสร้างโฟลเดอร์รูปภาพ
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        $message = "ไม่สามารถสร้างโฟลเดอร์สำหรับรูปภาพได้";
    }
}

// เปิด error reporting เฉพาะในโหมด development
ini_set('display_errors', 1); 
error_reporting(E_ALL);

// Log การโหลดหน้า
error_log("=== IMPORT PRODUCT PAGE LOADED ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("User ID from session: " . ($user_id ?? 'NULL'));
error_log("POST data count: " . count($_POST));

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
try {
    $pdo->query("SELECT 1");
    error_log("Database connection OK");
} catch (Exception $e) {
    $message = "ปัญหาการเชื่อมต่อฐานข้อมูล: " . $e->getMessage();
    error_log("Database connection FAILED: " . $e->getMessage());
}

// ตรวจสอบข้อมูลที่ส่งมาจากฟอร์ม
if(isset($_POST['submit'])) {
    error_log("=== FORM SUBMISSION DEBUG ===");
    error_log("POST submit detected: " . var_export($_POST['submit'], true));
    error_log("POST items data: " . print_r($_POST['items'] ?? 'NO ITEMS', true));
    error_log("FILES data: " . print_r($_FILES, true));
    error_log("User ID: " . $user_id);
    
    if(empty($_POST['items'])) {
        $message = "ไม่พบข้อมูลรายการสินค้า กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ";
        error_log("ERROR: No items found in POST data");
    }
}

if(isset($_POST['submit']) && !empty($_POST['items'])) {
    error_log("=== STARTING PRODUCT IMPORT PROCESS ===");
    error_log("User ID: " . $user_id);
    error_log("Total items to process: " . count($_POST['items']));
    error_log("Upload directory: " . $uploadDir);
    error_log("Upload directory exists: " . (is_dir($uploadDir) ? 'YES' : 'NO'));
    
    $pdo->beginTransaction();
    error_log("Database transaction started");
    
    try {
        // สร้างเลข PO
        $date = date('Ymd');
        $last_po = $pdo->query("SELECT po_number FROM purchase_orders WHERE po_number LIKE 'PO{$date}%' ORDER BY po_number DESC LIMIT 1")->fetchColumn();
        $num = $last_po ? intval(substr($last_po, -3)) + 1 : 1;
        $po_number = 'PO'.$date.str_pad($num,3,'0',STR_PAD_LEFT);
        error_log("Generated PO Number: " . $po_number);

        // คำนวณยอดรวม
        $total_amount = 0;
        foreach($_POST['items'] as $item){
            $item_total = floatval($item['qty']) * floatval($item['price']);
            $total_amount += $item_total;
            error_log("Item total calculation: " . $item['qty'] . " x " . $item['price'] . " = " . $item_total);
        }
        error_log("Total PO Amount: " . $total_amount);

        // insert PO
        $stmt = $pdo->prepare("INSERT INTO purchase_orders 
            (po_number, supplier_id, order_date, total_amount, ordered_by, status, remark)
            VALUES (?, ?, NOW(), ?, ?, ?, ?)");
        $result = $stmt->execute([$po_number, 1, $total_amount, $user_id, 'pending', 'imported from form']);
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("PO insert failed: " . print_r($errorInfo, true));
            throw new Exception("ไม่สามารถสร้าง Purchase Order ได้: " . $errorInfo[2]);
        }
        $po_id = $pdo->lastInsertId();
        error_log("PO Insert Result: SUCCESS");
        error_log("PO ID: " . $po_id);
        error_log("Total Amount: " . $total_amount);

        // insert items
        foreach($_POST['items'] as $idx=>$item){
            error_log("=== PROCESSING ITEM $idx ===");
            error_log("Item data: " . print_r($item, true));
            
            $sku = $item['sku'] ?? ''; 
            $barcode = $item['barcode'] ?? ''; 
            $name = $item['name'] ?? '';
            $unit = $item['unit'] ?? 'ชิ้น'; 
            $row_code = $item['row_code'] ?? ''; 
            $bin = $item['bin'] ?? ''; 
            $shelf = $item['shelf'] ?? '';
            $qty = floatval($item['qty'] ?? 0); 
            $price = floatval($item['price'] ?? 0);
            $currency = $item['currency'] ?? 'THB'; 
            $sale_price = floatval($item['sale_price'] ?? 0);
            
            error_log("Parsed values: name=$name, qty=$qty, price=$price, location=$row_code-$bin-$shelf");
            
            // ตรวจสอบข้อมูลที่จำเป็น
            if(empty($name)) {
                throw new Exception("ชื่อสินค้าในรายการที่ " . ($idx + 1) . " ไม่ถูกต้อง");
            }
            if($qty <= 0) {
                throw new Exception("จำนวนในรายการที่ " . ($idx + 1) . " ต้องมากกว่า 0");
            }
            if($price < 0) {
                throw new Exception("ราคาในรายการที่ " . ($idx + 1) . " ไม่ถูกต้อง");
            }

            // upload image - แก้ไขการจัดการไฟล์
            $imageFile = '';
            if(!empty($_FILES['items']['name'][$idx]['image'])) {
                $tmp_name = $_FILES['items']['tmp_name'][$idx]['image'];
                $original_name = $_FILES['items']['name'][$idx]['image'];
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($original_name));
                
                // ตรวจสอบว่าโฟลเดอร์มีอยู่หรือไม่
                if(!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                if(is_uploaded_file($tmp_name) && move_uploaded_file($tmp_name, $uploadDir . $filename)){
                    $imageFile = $filename;
                }
            }

            // ตรวจสอบ/เพิ่มสินค้า
            $product_id = null;
            if(!empty($sku) || !empty($barcode)) {
                $stmt = $pdo->prepare("SELECT product_id FROM products WHERE sku=? OR barcode=?");
                $stmt->execute([$sku, $barcode]);
                $product_id = $stmt->fetchColumn();
                error_log("Existing product check: " . ($product_id ? "Found ID $product_id" : "Not found"));
            }
            
            if(!$product_id){
                $image_path = !empty($imageFile) ? $imageFile : '';
                error_log("Creating new product: name=$name, sku=$sku, barcode=$barcode, unit=$unit, image=$image_path");
                $stmt = $pdo->prepare("INSERT INTO products (name, sku, barcode, unit, image, created_by, created_at) VALUES (?,?,?,?,?,?,NOW())");
                $result = $stmt->execute([$name, $sku, $barcode, $unit, $image_path, $user_id]);
                if (!$result) {
                    $errorInfo = $stmt->errorInfo();
                    error_log("Product insert failed: " . print_r($errorInfo, true));
                    throw new Exception("ไม่สามารถบันทึกข้อมูลสินค้าได้: " . $errorInfo[2]);
                }
                $product_id = $pdo->lastInsertId();
                error_log("Product Insert Result: SUCCESS");
                error_log("Created Product ID: " . $product_id);
            } else {
                error_log("Using existing Product ID: " . $product_id);
            }

            // ตรวจสอบ/เพิ่ม location
            if(empty($row_code) || empty($bin) || empty($shelf)) {
                throw new Exception("ข้อมูลที่เก็บสินค้าไม่ครบถ้วนในรายการที่ " . ($idx + 1));
            }
            
            $stmt = $pdo->prepare("SELECT location_id FROM locations WHERE row_code=? AND bin=? AND shelf=?");
            $stmt->execute([$row_code, $bin, $shelf]);
            $loc = $stmt->fetch();
            $location_id = $loc ? $loc['location_id'] : null;
            
            if(!$location_id){
                $desc = "$row_code-$bin-$shelf";
                $stmt = $pdo->prepare("INSERT INTO locations (row_code, bin, shelf, description) VALUES (?,?,?,?)");
                $stmt->execute([$row_code, $bin, $shelf, $desc]);
                $location_id = $pdo->lastInsertId();
            }

            // product_location
            $stmt = $pdo->prepare("SELECT 1 FROM product_location WHERE product_id=? AND location_id=?");
            $stmt->execute([$product_id,$location_id]);
            if(!$stmt->fetch()){
                $stmt = $pdo->prepare("INSERT INTO product_location (product_id, location_id) VALUES (?,?)");
                $stmt->execute([$product_id,$location_id]);
            }

            // แปลงราคาเป็น THB ก่อนบันทึก
            $price_thb = $price;
            $sale_price_thb = $sale_price;
            
            if($currency !== 'THB') {
                // ดึงอัตราแลกเปลี่ยน
                $rate_stmt = $pdo->prepare("SELECT exchange_rate_to_thb FROM currencies WHERE currency_code = ? AND is_active = 1");
                $rate_stmt->execute([$currency]);
                $rate = $rate_stmt->fetchColumn();
                
                if($rate) {
                    $price_thb = $price * $rate;
                    $sale_price_thb = $sale_price * $rate;
                }
            }

            // insert PO item
            $stmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, price_per_unit, total, currency, original_price) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$po_id,$product_id,$qty,$price_thb,$qty*$price_thb,$currency,$price]);
            $item_id = $pdo->lastInsertId();

            // insert receive
            $stmt = $pdo->prepare("INSERT INTO receive_items (created_at, po_id, item_id, receive_qty, created_by, remark) VALUES (NOW(),?,?,?,?,?)");
            $stmt->execute([$po_id,$item_id,$qty,$user_id,'imported']);
        }

        $pdo->commit();
        error_log("=== TRANSACTION COMMITTED SUCCESSFULLY ===");
        error_log("Final PO Number: " . $po_number);
        error_log("Total items saved: " . count($_POST['items']));
        error_log("Success message will be displayed to user");
        
        // Set success flag for JavaScript
        $success = true;
        $message = "✅ บันทึกข้อมูลสำเร็จ!<br>📋 Purchase Order: <b>$po_number</b><br>📦 จำนวนรายการ: <b>" . count($_POST['items']) . "</b> รายการ";
        
    } catch(Exception $e){
        $pdo->rollBack();
        error_log("=== TRANSACTION FAILED ===");
        error_log("Error: " . $e->getMessage());
        error_log("File: " . $e->getFile());
        error_log("Line: " . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Set error flag for JavaScript
        $success = false;
        $message = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
    }
} else if(isset($_POST['submit'])) {
    $success = false;
    $message = "ไม่พบข้อมูลรายการสินค้า กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ";
    error_log("Form submitted but no items found");
}

// Debug: Log final status
error_log("=== FINAL PAGE STATUS ===");
error_log("Message: " . ($message ? "'" . $message . "'" : "EMPTY"));
error_log("Success flag: " . (isset($success) ? ($success ? 'TRUE' : 'FALSE') : 'NOT SET'));
?>

<!-- PHP Debug Info (will show in HTML source) -->
<!-- 
MESSAGE DEBUG:
- Message content: <?= $message ? htmlspecialchars($message) : 'EMPTY' ?>
- Success flag: <?= isset($success) ? ($success ? 'TRUE' : 'FALSE') : 'NOT SET' ?>
- POST submit: <?= isset($_POST['submit']) ? 'YES' : 'NO' ?>
- POST items count: <?= isset($_POST['items']) ? count($_POST['items']) : '0' ?>
-->

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>IchoicePMS</title>
  <link rel="icon" href="images/favicon.png" type="image/png">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="../assets/base.css">
<link rel="stylesheet" href="../assets/sidebar.css">
<link rel="stylesheet" href="../assets/components.css">

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>

      <style>
      :root {

        --primary-color: #4f46e5;
          --primary-hover: #4338ca;
          --success-color: #10b981;
          --danger-color: #ef4444;
          --warning-color: #f59e0b;
          --background-color: #f8fafc;
          --card-background: #ffffff;
          --border-color: #e2e8f0;
          --text-primary: #1e293b;
          --text-secondary: #64748b;
          --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
          --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -2px rgb(0 0 0 / 0.05);
          --radius-md: 0.5rem;
          --radius-lg: 0.75rem;
      }

      .mainwrap {
          background: var(--background-color);
          min-height: 100vh;
          padding: 1rem;
      }

      .topbar {
          background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
          color: white;
          padding: 1.25rem 2rem;
          border-radius: var(--radius-lg);
          font-size: 1.25rem;
          font-weight: 600;
          text-align: center;
          margin-bottom: 2rem;
          box-shadow: var(--shadow-lg);
      }

      .table-card {
          background: var(--card-background);
          border-radius: var(--radius-lg);
          box-shadow: var(--shadow-lg);
          padding: 0;
          max-width: 95vw;
          margin: auto;
          overflow: hidden;
      }

      .table-card h2 {
          color: white;
          background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
          padding: 1.5rem 2rem;
          margin: 0;
          font-size: 1.5rem;
          font-weight: 600;
          display: flex;
          align-items: center;
          gap: 0.75rem;
      }

      .table-card h2::before {
          content: '📦';
          font-size: 1.25rem;
      }

      .table-content {
          padding: 2rem;
      }

      .table-responsive {
          overflow-x: auto;
          border-radius: var(--radius-md);
          border: 1px solid var(--border-color);
          margin-bottom: 2rem;
      }

      .table-product {
          width: 100%;
          border-collapse: collapse;
          font-size: 0.9rem;
          background: white;
      }

      .table-product thead th {
          background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
          color: var(--text-primary);
          text-align: center;
          padding: 0.75rem 0.5rem;
          font-weight: 600;
          border-bottom: 2px solid var(--border-color);
          position: sticky;
          top: 0;
          z-index: 10;
          white-space: nowrap;
          font-size: 0.85rem;
      }

      .table-product tbody td {
          padding: 1rem 0.75rem;
          border-bottom: 1px solid var(--border-color);
          vertical-align: middle;
      }

      .table-product tbody tr {
          transition: all 0.2s ease;
      }

      .table-product tbody tr:hover {
          background: #f8fafc;
          transform: translateY(-1px);
          box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      }

      .form-control, .form-select {
          width: 100%;
          padding: 0.5rem;
          border: 1px solid var(--border-color);
          border-radius: var(--radius-md);
          font-size: 0.85rem;
          transition: all 0.2s ease;
          background: white;
          min-width: 100px;
      }

      .form-control:focus, .form-select:focus {
          outline: none;
          border-color: var(--primary-color);
          box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
          transform: translateY(-1px);
      }

      .file-input {
          font-size: 0.85rem;
          padding: 0.5rem !important;
      }

      .btn {
          display: inline-flex;
          align-items: center;
          gap: 0.5rem;
          padding: 0.75rem 1.25rem;
          border: none;
          border-radius: var(--radius-md);
          font-size: 0.9rem;
          font-weight: 500;
          cursor: pointer;
          transition: all 0.2s ease;
          text-decoration: none;
          white-space: nowrap;
      }

      .btn:hover {
          transform: translateY(-1px);
          box-shadow: var(--shadow-lg);
      }

      .btn-primary {
          background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
          color: white;
      }

      .btn-primary:hover {
          background: linear-gradient(135deg, var(--primary-hover) 0%, #3730a3 100%);
      }

      .btn-success {
          background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
          color: white;
      }

      .btn-success:hover {
          background: linear-gradient(135deg, #059669 0%, #047857 100%);
      }

      .btn-sm {
          padding: 0.5rem 0.75rem;
          font-size: 0.85rem;
      }

      .scan-btn {
          background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);
          color: white;
          padding: 0.5rem 0.75rem;
          font-size: 0.8rem;
      }

      .scan-btn:hover {
          background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
      }

      .remove-row-btn {
          background: white;
          border: 2px solid var(--border-color);
          border-radius: 50%;
          width: 40px;
          height: 40px;
          display: flex;
          justify-content: center;
          align-items: center;
          cursor: pointer;
          transition: all 0.2s ease;
      }

      .remove-row-btn:hover {
          border-color: var(--danger-color);
          background: var(--danger-color);
          color: white;
          transform: scale(1.1);
      }

      .remove-row-btn span {
          color: var(--danger-color);
          font-size: 1.25rem;
          transition: color 0.2s ease;
      }

      .remove-row-btn:hover span {
          color: white;
      }

      .action-buttons {
          display: flex;
          gap: 1rem;
          padding: 1.5rem 0 0 0;
          justify-content: center;
          flex-wrap: wrap;
      }

      .barcode-input-group {
          display: flex;
          gap: 0.5rem;
          align-items: stretch;
      }

      .barcode-input-group input {
          flex: 1;
          min-width: 120px;
      }

      /* Location Selects Styling */
      .location-group {
          display: flex;
          gap: 0.25rem;
          align-items: center;
          flex-wrap: nowrap;
      }

      .location-group select {
          min-width: 70px;
          font-size: 0.8rem;
          flex: 1;
      }

      /* Image Preview */
      .image-preview {
          width: 50px;
          height: 50px;
          border-radius: var(--radius-md);
          object-fit: cover;
          border: 2px solid var(--border-color);
          display: none;
      }

      /* Responsive Design */
      @media (max-width: 1400px) {
          .table-product th,
          .table-product td {
              padding: 0.5rem 0.25rem;
          }
          
          .form-control, .form-select {
              min-width: 90px;
              font-size: 0.8rem;
              padding: 0.4rem;
          }
          
          .location-group select {
              min-width: 60px;
              font-size: 0.75rem;
          }
      }

      @media (max-width: 768px) {
          .mainwrap {
              padding: 0.5rem;
          }
          
          .table-content {
              padding: 1rem;
          }
          
          .topbar {
              padding: 1rem;
              font-size: 1.1rem;
          }
          
          .table-product th,
          .table-product td {
              padding: 0.5rem 0.25rem;
              font-size: 0.8rem;
          }
          
          .form-control, .form-select {
              min-width: 80px;
              padding: 0.5rem;
              font-size: 0.8rem;
          }
          
          .btn {
              padding: 0.5rem 0.75rem;
              font-size: 0.8rem;
          }
          
          .action-buttons {
              flex-direction: column;
              align-items: center;
          }
          
          .barcode-input-group {
              flex-direction: column;
          }
          
          .location-group {
              flex-direction: column;
              gap: 0.25rem;
          }
          
          .location-group select {
              min-width: 70px;
          }
      }

      /* Loading Animation */
      .loading {
          display: inline-block;
          width: 20px;
          height: 20px;
          border: 3px solid #f3f3f3;
          border-top: 3px solid var(--primary-color);
          border-radius: 50%;
          animation: spin 1s linear infinite;
      }

      @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
      }

      /* Success Animation */
      @keyframes fadeInUp {
          from {
              opacity: 0;
              transform: translateY(30px);
          }
          to {
              opacity: 1;
              transform: translateY(0);
          }
      }

      .table-product tbody tr {
          animation: fadeInUp 0.3s ease-out;
      }

      /* Modern Scrollbar */
      .table-responsive::-webkit-scrollbar {
          height: 8px;
      }

      .table-responsive::-webkit-scrollbar-track {
          background: #f1f1f1;
          border-radius: 4px;
      }

      .table-responsive::-webkit-scrollbar-thumb {
          background: var(--primary-color);
          border-radius: 4px;
      }

      .table-responsive::-webkit-scrollbar-thumb:hover {
          background: var(--primary-hover);
      }
      
      /* ปรับขนาดตารางให้พอดีกับหน้าจอ */
      .table-product {
          table-layout: fixed;
          width: 100%;
          min-width: 1350px; /* เพิ่มขนาดเพื่อรองรับคอลัมน์เพิ่มเติม */
      }
      
      .table-product td,
      .table-product th {
          overflow: hidden;
          text-overflow: ellipsis;
          padding: 0.4rem 0.2rem;
      }
      
      /* ปรับขนาด input ให้พอดีกับ cell */
      .table-product input,
      .table-product select {
          width: 100%;
          min-height: 32px;
          box-sizing: border-box;
      }
      
      /* ปรับขนาดปุ่มสแกน */
      .scan-btn {
          padding: 0.3rem 0.5rem;
          font-size: 0.75rem;
          min-width: auto;
          white-space: nowrap;
      }
      
      /* ปรับขนาดปุ่มลบ */
      .remove-row-btn {
          width: 32px;
          height: 32px;
          padding: 0;
      }
      
      /* ปรับขนาด image preview */
      .image-preview {
          width: 40px;
          height: 40px;
      }
      
      /* ลดระยะห่างในช่อง location */
      .location-group span {
          font-size: 0.7rem;
          margin: 0 2px;
      }
      
      /* Price Input Group สำหรับราคาและการแปลงสกุลเงิน */
      .price-input-group {
          position: relative;
          display: flex;
          flex-direction: column;
          gap: 2px;
      }
      
      .converted-price,
      .converted-sale-price {
          font-size: 0.65rem !important;
          color: #64748b !important;
          margin: 0;
          line-height: 1;
          font-weight: 500;
          background: #f1f5f9;
          padding: 1px 4px;
          border-radius: 2px;
          text-align: center;
      }
      
      /* สีสำหรับสกุลเงินต่างๆ */
      .converted-price {
          color: #059669 !important;
          background: #dcfce7;
      }
      
      .converted-sale-price {
          color: #0ea5e9 !important;
          background: #e0f2fe;
      }
      
      /* Alert styles */
      .alert {
          padding: 12px 20px;
          margin-bottom: 1rem;
          border: 1px solid transparent;
          border-radius: 8px;
          position: relative;
          display: flex;
          align-items: center;
          justify-content: space-between;
      }
      
      .alert-info {
          color: #0c5460;
          background-color: #d1ecf1;
          border-color: #bee5eb;
      }
      
      .alert .btn-close {
          background: none;
          border: none;
          font-size: 1.2rem;
          cursor: pointer;
          opacity: 0.7;
          padding: 0;
          margin-left: 15px;
      }
      
      .alert .btn-close:hover {
          opacity: 1;
      }
      
      .alert .btn-close::before {
          content: '×';
      }
</style>
</head>
<body>

<div class="mainwrap">
<div class="topbar mb-3">เพิ่มสินค้าใหม่</div>

<!-- Debug Alert -->
<div class="alert alert-info alert-dismissible fade show" role="alert" style="margin: 1rem; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 1px solid #2196f3; border-radius: 8px;">
  <div class="d-flex align-items-center">
    <span style="font-size: 1.2rem; margin-right: 8px;">🔍</span>
    <div>
      <strong>โหมดการ Debug:</strong> ระบบกำลังตรวจสอบการทำงานของฟอร์ม
      <br><small class="text-muted">
        • กดปุ่ม <strong>"🔍 Debug Logs"</strong> (มุมขวาบน) เพื่อดู PHP error logs
        <br>• กดปุ่ม <strong>"📟 Console"</strong> (มุมซ้ายล่าง) เพื่อดู JavaScript logs
        <br>• ทดสอบเพิ่มสินค้าแล้วตรวจสอบ logs ทั้งสองช่องทางเพื่อดูปัญหา
      </small>
    </div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<!-- Test Notification Buttons -->
<div style="margin: 1rem; text-align: center;">
  <button type="button" onclick="testSuccessNotification()" style="
    background: #10b981; color: white; border: none; padding: 8px 15px; 
    border-radius: 5px; margin: 0 5px; cursor: pointer;">
    🎉 ทดสอบ Success
  </button>
  <button type="button" onclick="testErrorNotification()" style="
    background: #ef4444; color: white; border: none; padding: 8px 15px; 
    border-radius: 5px; margin: 0 5px; cursor: pointer;">
    ❌ ทดสอบ Error
  </button>
</div>

<div class="table-card">
  <h2>เพิ่มรายการสินค้าทีละรายการ</h2>
  
  <div class="table-content">
    <form method="post" enctype="multipart/form-data">
      
      <div class="table-responsive">
        <table class="table-product" id="items-table">
          <thead>
            <tr>
              <th style="width: 9%;">รหัสสินค้า<br><small style="font-size: 0.75rem; opacity: 0.7;">SKU</small></th>
              <th style="width: 13%;">บาร์โค้ด<br><small style="font-size: 0.75rem; opacity: 0.7;">Barcode</small></th>
              <th style="width: 18%;">ชื่อสินค้า<br><small style="font-size: 0.75rem; opacity: 0.7;">Product Name</small></th>
              <th style="width: 8%;">รูปภาพ<br><small style="font-size: 0.75rem; opacity: 0.7;">Image</small></th>
              <th style="width: 6%;">หน่วย<br><small style="font-size: 0.75rem; opacity: 0.7;">Unit</small></th>
              <th style="width: 16%;">ที่เก็บสินค้า<br><small style="font-size: 0.75rem; opacity: 0.7;">Row-Bin-Shelf</small></th>
              <th style="width: 6%;">จำนวน<br><small style="font-size: 0.75rem; opacity: 0.7;">Qty</small></th>
              <th style="width: 6%;">สกุลเงิน<br><small style="font-size: 0.75rem; opacity: 0.7;">Currency</small></th>
              <th style="width: 7%;">ราคาทุน<br><small style="font-size: 0.75rem; opacity: 0.7;">Cost</small></th>
              <th style="width: 7%;">ราคาขาย<br><small style="font-size: 0.75rem; opacity: 0.7;">Sale</small></th>
              <th style="width: 4%;">ลบ<br><small style="font-size: 0.75rem; opacity: 0.7;">Del</small></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <input class="form-control" type="text" name="items[0][sku]" placeholder="SKU001">
              </td>
              <td>
                <div class="barcode-input-group">
                  <input class="form-control" type="text" name="items[0][barcode]" placeholder="ป้อนหรือสแกน">
                  <button type="button" class="btn btn-sm scan-btn">
                    <span class="material-icons" style="font-size: 16px;">qr_code_scanner</span>
                    สแกน
                  </button>
                </div>
              </td>
              <td>
                <input class="form-control" type="text" name="items[0][name]" placeholder="ชื่อสินค้า" required>
              </td>
              <td>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: center;">
                  <input class="form-control file-input" type="file" name="items[0][image]" accept="image/*" capture="environment">
                  <img class="image-preview" id="preview-0" alt="ตัวอย่างรูป">
                </div>
              </td>
              <td>
                <input class="form-control" type="text" name="items[0][unit]" placeholder="ชิ้น, กิโลกรัม">
              </td>
              <td>
                <div class="location-group">
                  <select class="form-select" name="items[0][row_code]" required>
                    <option value="">แถว</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                    <option value="E">E</option>
                    <option value="F">F</option>
                    <option value="G">G</option>
                    <option value="H">H</option>
                    <option value="I">I</option>
                    <option value="J">J</option>
                    <option value="K">K</option>
                    <option value="L">L</option>
                    <option value="M">M</option>
                    <option value="N">N</option>
                    <option value="O">O</option>
                    <option value="P">P</option>
                    <option value="Q">Q</option>
                    <option value="R">R</option>
                    <option value="S">S</option>
                    <option value="T">T (ตู้)</option>
                    <option value="U">U</option>
                    <option value="V">V</option>
                    <option value="W">W</option>
                    <option value="X">X</option>
                    <option value="Sale(บน)">Sale(บน)</option>
                    <option value="Sale(ล่าง)">Sale(ล่าง)</option>
                  </select>
                  <span style="color: var(--text-secondary);">-</span>
                  <select class="form-select" name="items[0][bin]" required>
                    <option value="">ล็อค</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                    <option value="6">6</option>
                    <option value="7">7</option>
                    <option value="8">8</option>
                    <option value="9">9</option>
                    <option value="10">10</option>
                  </select>
                  <span style="color: var(--text-secondary);">-</span>
                  <select class="form-select" name="items[0][shelf]" required>
                    <option value="">ชั้น</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                    <option value="6">6</option>
                    <option value="7">7</option>
                    <option value="8">8</option>
                    <option value="9">9</option>
                    <option value="10">10</option>
                  </select>
                </div>
              </td>
              <td>
                <input class="form-control" type="number" min="1" name="items[0][qty]" placeholder="1" required>
              </td>
              <td>
                <select class="form-select" name="items[0][currency]" data-row="0" onchange="updateCurrencyDisplay(this)" required>
                  <option value="">เลือกสกุลเงิน</option>
                  <option value="THB" selected>THB (บาท)</option>
                  <option value="USD">USD (ดอลลาร์)</option>
                </select>
              </td>
              <td>
                <div class="price-input-group">
                  <input class="form-control price-input" type="number" min="0" step="0.01" name="items[0][price]" placeholder="0.00" data-row="0" oninput="calculatePriceConversion(this)" required>
                  <small class="converted-price text-muted" id="converted-price-0" style="font-size: 0.7rem; display: none;"></small>
                </div>
              </td>
              <td>
                <div class="price-input-group">
                  <input class="form-control price-input" type="number" min="0" step="0.01" name="items[0][sale_price]" placeholder="0.00" data-row="0" oninput="calculatePriceConversion(this)">
                  <small class="converted-sale-price text-muted" id="converted-sale-price-0" style="font-size: 0.7rem; display: none;"></small>
                </div>
              </td>
              <td>
                <button type="button" class="remove-row-btn" title="ลบรายการ">
                  <span class="material-icons">delete</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="action-buttons">
        <button type="button" class="add-row-btn btn btn-primary">
          <span class="material-icons">add_circle</span> 
          เพิ่มรายการสินค้า
        </button>
        <button type="submit" name="submit" class="save-btn btn btn-success">
          <span class="material-icons">save_alt</span> 
          บันทึกข้อมูลทั้งหมด
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal สแกนบาร์โค้ดแบบสวยงาม -->
<div class="modal fade" id="barcodeModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content" style="border-radius: var(--radius-lg); overflow: hidden; border: none;">
      <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); color: white; border: none;">
        <h5 class="modal-title" style="display: flex; align-items: center; gap: 0.75rem; font-weight: 600;">
          <span class="material-icons">qr_code_scanner</span>
          สแกนบาร์โค้ดสินค้า
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body p-0" style="position: relative; min-height: 400px;">
        <!-- แสดงสถานะการโหลด -->
        <div id="cameraStatus" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; z-index: 20; background: rgba(255,255,255,0.9); padding: 20px; border-radius: 10px; display: none;">
          <div class="loading" style="margin: 0 auto 10px;"></div>
          <p style="margin: 0; color: var(--text-primary);">กำลังเปิดกล้อง...</p>
        </div>
        
        <!-- พื้นที่แสดงกล้อง -->
        <div id="reader" style="width:100%; height:400px; background: #f8fafc;"></div>
        
        <!-- คำแนะนำ -->
        <div id="scanInstructions" style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 10px 20px; border-radius: 20px; font-size: 0.9rem;">
          <span class="material-icons" style="vertical-align: middle; margin-right: 8px; font-size: 18px;">info</span>
          วางบาร์โค้ดให้อยู่ในกรอบสี่เหลี่ยมเพื่อสแกน
        </div>
      </div>
      <div class="modal-footer" style="background: #f8fafc; border: none; justify-content: space-between;">
        <div>
          <button type="button" id="testCameraBtn" class="btn btn-info btn-sm me-2">
            <span class="material-icons">videocam</span>
            ทดสอบกล้อง
          </button>
          <button type="button" id="manualInputBtn" class="btn btn-success btn-sm me-2">
            <span class="material-icons">keyboard</span>
            พิมพ์เอง
          </button>
          <button type="button" id="helpBtn" class="btn btn-warning btn-sm" onclick="showCameraHelp()">
            <span class="material-icons">help</span>
            ช่วยเหลือ
          </button>
        </div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="material-icons">close</span>
          ยกเลิก
        </button>
      </div>
    </div>
  </div>
</div>


<?php if($message): ?>
<script>
console.log('=== MESSAGE NOTIFICATION ===');
console.log('Message exists: true');
console.log('Message content:', '<?= addslashes($message) ?>');
console.log('Success flag:', <?= isset($success) && $success ? 'true' : 'false' ?>);
console.log('SweetAlert2 available:', typeof Swal !== 'undefined');
console.log('About to show notification...');

// Check if SweetAlert2 is available
if (typeof Swal === 'undefined') {
    console.error('SweetAlert2 not loaded! Using fallback alert...');
    alert('<?= strip_tags($message) ?>');
} else {

<?php if(isset($success) && $success): ?>
// Success notification
Swal.fire({
    icon: 'success',
    title: '🎉 สำเร็จ!',
    html: '<?= addslashes($message) ?>',
    timer: 5000,
    timerProgressBar: true,
    showConfirmButton: true,
    confirmButtonText: '✨ เริ่มใหม่',
    confirmButtonColor: '#10b981',
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
        console.log('Success notification displayed');
    }
}).then((result) => {
    if (result.isConfirmed) {
        console.log('User clicked to restart');
        location.reload();
    }
});

// Auto reload after 6 seconds if user doesn't click
setTimeout(() => {
    console.log('Auto reloading page after success');
    location.reload();
}, 6000);

<?php else: ?>
// Error notification  
Swal.fire({
    icon: 'error',
    title: '❌ เกิดข้อผิดพลาด',
    html: '<?= addslashes($message) ?>',
    confirmButtonText: '🔍 ตรวจสอบ Debug',
    confirmButtonColor: '#ef4444',
    showCancelButton: true,
    cancelButtonText: '↻ ลองใหม่',
    cancelButtonColor: '#6b7280',
    allowOutsideClick: false,
    didOpen: () => {
        console.log('Error notification displayed');
    }
}).then((result) => {
    if (result.isConfirmed) {
        console.log('User wants to debug');
        // Show debug instructions
        Swal.fire({
            icon: 'info',
            title: '🔍 วิธีตรวจสอบปัญหา',
            html: `
                <div style="text-align: left;">
                    <p><strong>1. ตรวจสอบ PHP Logs:</strong></p>
                    <p>• กดปุ่ม <strong>"🔍 Debug Logs"</strong> มุมขวาบน</p>
                    <p><strong>2. ตรวจสอบ Console Logs:</strong></p>
                    <p>• กดปุ่ม <strong>"📟 Console"</strong> มุมซ้ายล่าง</p>
                    <p><strong>3. หรือกด F12 → Console Tab</strong></p>
                </div>
            `,
            confirmButtonText: 'เข้าใจแล้ว',
            confirmButtonColor: '#3b82f6'
        });
    } else {
        console.log('User wants to try again');
        location.reload();
    }
});
<?php endif; ?>

} // End SweetAlert2 check
</script>
<?php endif; ?>

<script>
// ข้อมูลอัตราแลกเปลี่ยนสกุลเงิน
let exchangeRates = {
  'THB': 1,
  'USD': 35.50 // ค่าเริ่มต้น - จะดึงจากฐานข้อมูล
};

// ฟังก์ชันดึงข้อมูลอัตราแลกเปลี่ยนจากฐานข้อมูล
async function loadExchangeRates() {
  try {
    const response = await fetch('../api/get_exchange_rates.php');
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        exchangeRates = data.rates;
        console.log('โหลดอัตราแลกเปลี่ยนสำเร็จ:', exchangeRates);
        
        // อัปเดตราคาที่มีอยู่แล้ว
        $('.price-input').each(function() {
          calculatePriceConversion(this);
        });
      }
    }
  } catch (error) {
    console.error('โหลดอัตราแลกเปลี่ยนไม่สำเร็จ:', error);
  }
}

// ฟังก์ชันคำนวณและแสดงราคาที่แปลงแล้ว
function calculatePriceConversion(priceInput) {
  const row = $(priceInput).data('row');
  const currency = $(`select[name="items[${row}][currency]"]`).val();
  const price = parseFloat($(priceInput).val()) || 0;
  
  if (!currency || price === 0) {
    $(`#converted-price-${row}, #converted-sale-price-${row}`).hide();
    return;
  }
  
  const isCostPrice = $(priceInput).attr('name').includes('[price]');
  const convertedElement = isCostPrice ? 
    $(`#converted-price-${row}`) : 
    $(`#converted-sale-price-${row}`);
  
  if (currency === 'THB') {
    convertedElement.hide();
  } else {
    const convertedPrice = (price * exchangeRates[currency]).toFixed(2);
    convertedElement.text(`≈ ${convertedPrice} บาท`).show();
  }
}

// ฟังก์ชันอัปเดตการแสดงเมื่อเปลี่ยนสกุลเงิน
function updateCurrencyDisplay(selectElement) {
  const row = $(selectElement).data('row');
  
  // อัปเดตราคาทั้งสอง
  $(`input[name="items[${row}][price]"], input[name="items[${row}][sale_price]"]`).each(function() {
    calculatePriceConversion(this);
  });
}

$(document).ready(function(){

let rowIdx = $('#items-table tbody tr').length;

// โหลดอัตราแลกเปลี่ยนเมื่อหน้าโหลดเสร็จ
loadExchangeRates();

// เพิ่มแถวใหม่
$('.add-row-btn').click(function(){
  let newRow = $('#items-table tbody tr:first').clone();
  
  // อัปเดต name attributes, data-row, และ ID สำหรับแถวใหม่
  newRow.find('input,select').each(function(){
    let name = $(this).attr('name');
    if(name){ 
      $(this).attr('name', name.replace(/\d+/, rowIdx)); 
      $(this).val(''); 
    }
    
    // อัปเดต data-row attribute
    if($(this).data('row') !== undefined) {
      $(this).attr('data-row', rowIdx);
    }
    
    // อัปเดต onchange และ oninput attributes
    if($(this).attr('onchange')) {
      $(this).attr('onchange', $(this).attr('onchange').replace(/\d+/, rowIdx));
    }
    if($(this).attr('oninput')) {
      $(this).attr('oninput', $(this).attr('oninput').replace(/\d+/, rowIdx));
    }
  });
  
  // อัปเดต ID สำหรับ image preview และ converted price elements
  newRow.find('.image-preview').attr('id', 'preview-' + rowIdx).hide();
  newRow.find('.converted-price').attr('id', 'converted-price-' + rowIdx).hide();
  newRow.find('.converted-sale-price').attr('id', 'converted-sale-price-' + rowIdx).hide();
  
  // ตั้งค่าเริ่มต้นสำหรับ currency (THB)
  newRow.find('select[name*="[currency]"]').val('THB');
  
  // เพิ่ม animation
  newRow.hide().appendTo('#items-table tbody').fadeIn(300);
  
  // Focus ที่ SKU input ของแถวใหม่
  setTimeout(() => {
    newRow.find('input[name*="[sku]"]').focus();
  }, 300);
  
  rowIdx++;
  
  // แสดงข้อความแจ้ง
  showToast('เพิ่มรายการสินค้าใหม่แล้ว', 'success');
});

// ลบแถว
$('#items-table').on('click','.remove-row-btn',function(){
  if($('#items-table tbody tr').length > 1){
    $(this).closest('tr').fadeOut(300, function(){ 
      $(this).remove(); 
      showToast('ลบรายการสินค้าแล้ว', 'info');
    });
  } else {
    showToast('ต้องมีรายการสินค้าอย่างน้อย 1 รายการ', 'warning');
  }
});

// Image Preview
$('#items-table').on('change', 'input[type="file"]', function(){
  let file = this.files[0];
  let previewImg = $(this).closest('td').find('.image-preview');
  
  if(file && file.type.startsWith('image/')){
    let reader = new FileReader();
    reader.onload = function(e){
      previewImg.attr('src', e.target.result).show();
    };
    reader.readAsDataURL(file);
  } else {
    previewImg.hide();
  }
});

// สแกนบาร์โค้ด - เวอร์ชันใหม่ที่ใช้งานได้จริง
let html5QrCode = null;
let currentInput = null;
let isScanning = false;

// ฟังก์ชันตรวจสอบการรองรับกล้อง
function checkCameraSupport() {
  const result = {
    supported: false,
    message: '',
    hasNavigator: !!navigator,
    hasMediaDevices: false,
    hasGetUserMedia: false,
    hasLegacyAPI: false
  };
  
  // ตรวจสอบ navigator
  if (!navigator) {
    result.message = 'Navigator ไม่พร้อมใช้งาน';
    return result;
  }
  
  // ตรวจสอบ mediaDevices
  result.hasMediaDevices = !!navigator.mediaDevices;
  
  if (!navigator.mediaDevices) {
    // สร้าง mediaDevices object ถ้าไม่มี
    navigator.mediaDevices = {};
    
    // ตรวจสอบ legacy API
    const legacyGetUserMedia = navigator.getUserMedia || 
                              navigator.webkitGetUserMedia || 
                              navigator.mozGetUserMedia ||
                              navigator.msGetUserMedia;
    
    result.hasLegacyAPI = !!legacyGetUserMedia;
    
    if (legacyGetUserMedia) {
      // สร้าง polyfill
      try {
        navigator.mediaDevices.getUserMedia = function(constraints) {
          return new Promise((resolve, reject) => {
            legacyGetUserMedia.call(navigator, constraints, resolve, reject);
          });
        };
        result.hasMediaDevices = true;
        result.hasGetUserMedia = true;
        result.supported = true;
        result.message = 'ใช้ Legacy API';
      } catch (e) {
        result.message = 'สร้าง polyfill ไม่สำเร็จ: ' + e.message;
        return result;
      }
    } else {
      // ลองสร้าง basic polyfill แม้ไม่มี legacy API
      try {
        navigator.mediaDevices.getUserMedia = function(constraints) {
          return Promise.reject(new Error('getUserMedia ไม่รองรับในเบราว์เซอร์นี้'));
        };
        result.message = 'เบราว์เซอร์ไม่รองรับการใช้กล้อง - ไม่มี API ใดๆ';
        return result;
      } catch (e) {
        result.message = 'เบราว์เซอร์เก่าเกินไป - ไม่รองรับการใช้กล้อง';
        return result;
      }
    }
  } else {
    // ตรวจสอบ getUserMedia
    result.hasGetUserMedia = !!navigator.mediaDevices.getUserMedia;
    
    if (!navigator.mediaDevices.getUserMedia) {
      result.message = 'เบราว์เซอร์ไม่รองรับ getUserMedia';
      return result;
    }
    
    result.supported = true;
    result.message = 'รองรับ Modern API';
  }
  
  return result;
}

// ฟังก์ชันตรวจสอบและขอ permission กล้อง
async function requestCameraPermission() {
  try {
    // ตรวจสอบการรองรับก่อน
    const support = checkCameraSupport();
    
    if (!support.supported) {
      throw new Error(support.message);
    }
    
    console.log('Camera support details:', support);
    
    // ตรวจสอบโครตคอล HTTPS (อนุญาต localhost และ local development)
    const isLocalhost = location.hostname === 'localhost' || 
                       location.hostname === '127.0.0.1' ||
                       location.hostname.includes('local') ||
                       location.hostname.endsWith('.local') ||
                       location.hostname.includes('192.168.') ||
                       location.hostname.includes('10.0.') ||
                       location.hostname === '';
                       
    if (location.protocol !== 'https:' && !isLocalhost) {
      throw new Error('กล้องต้องใช้งานผ่าน HTTPS เท่านั้น (ยกเว้น localhost)');
    }
    
    const stream = await navigator.mediaDevices.getUserMedia({ 
      video: { 
        facingMode: { ideal: "environment" },
        width: { ideal: 640 },
        height: { ideal: 480 }
      } 
    });
    
    // หยุด stream ทันที เพราะจะให้ Html5Qrcode จัดการเอง
    stream.getTracks().forEach(track => track.stop());
    return true;
  } catch (error) {
    console.error('Camera permission error:', error);
    throw error;
  }
}

// ฟังก์ชันเริ่มการสแกน
async function startScanner() {
  try {
    // ล้าง reader element
    $('#reader').empty();
    
    // หยุด scanner เก่าถ้ามี
    if (html5QrCode) {
      try {
        await html5QrCode.stop();
        html5QrCode.clear();
      } catch (e) {
        console.log('Error stopping previous scanner:', e);
      }
      html5QrCode = null;
    }
    
    // อัปเดตสถานะ
    $('#cameraStatus').show().find('p').text('กำลังเริ่มกล้อง...');
    
    // สร้าง scanner ใหม่
    html5QrCode = new Html5Qrcode("reader");
    
    // เริ่มสแกน
    await html5QrCode.start(
      { facingMode: "environment" },
      {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
      },
      // สำเร็จ
      decodedText => {
        currentInput.val(decodedText);
        $('#barcodeModal').modal('hide');
        showToast('สแกนบาร์โค้ดสำเร็จ: ' + decodedText, 'success');
      },
      // ผิดพลาดขณะสแกน (ไม่แสดง)
      errorMessage => {}
    );
    
    // สำเร็จ - ซ่อนสถานะและแสดงคำแนะนำ
    $('#cameraStatus').hide();
    $('#scanInstructions').show();
    
  } catch (error) {
    console.error('Scanner start error:', error);
    $('#cameraStatus').hide();
    throw error;
  }
}

$(document).on('click', '.scan-btn', function(){
  if(isScanning) return;
  
  currentInput = $(this).closest('.barcode-input-group').find('input');
  let scanButton = $(this);
  let originalContent = scanButton.html();
  
  // ปิดการใช้งานปุ่ม
  scanButton.html('<div class="loading"></div> เตรียมกล้อง...');
  scanButton.prop('disabled', true);
  isScanning = true;
  
  function resetButton() {
    scanButton.html(originalContent);
    scanButton.prop('disabled', false);
    isScanning = false;
  }
  
  // เปิด Modal
  $('#barcodeModal').modal('show');
  
  // เมื่อ Modal เปิดเสร็จ
  $('#barcodeModal').one('shown.bs.modal', async function(){
    try {
      // ขอ permission ก่อน
      $('#cameraStatus').show().find('p').text('กำลังขอสิทธิ์เข้าถึงกล้อง...');
      await requestCameraPermission();
      
      // เริ่ม scanner
      await startScanner();
      
      // รีเซ็ตปุ่ม
      resetButton();
      
    } catch (error) {
      $('#cameraStatus').hide();
      resetButton();
      
      let errorMsg = 'ไม่สามารถเปิดกล้องได้';
      let instruction = '';
      
      if (error.name === 'NotAllowedError') {
        errorMsg = 'กรุณาอนุญาตการใช้กล้อง';
        instruction = '1. คลิกไอคอนกล้องในแถบ URL\n2. เลือก "อนุญาต" หรือ "Allow"\n3. รีเฟรชหน้าเว็บแล้วลองใหม่';
      } else if (error.name === 'NotFoundError') {
        errorMsg = 'ไม่พบกล้องในอุปกรณ์';
        instruction = 'กรุณาตรวจสอบว่าอุปกรณ์มีกล้องและทำงานปกติ';
      } else if (error.name === 'NotReadableError') {
        errorMsg = 'กล้องถูกใช้งานโดยแอปอื่น';
        instruction = 'กรุณาปิดแอปพลิเคชันที่ใช้กล้องอื่นๆ แล้วลองใหม่';
      }
      
      Swal.fire({
        icon: 'error',
        title: 'เปิดกล้องไม่ได้',
        text: errorMsg,
        html: instruction ? `<div style="text-align: left; margin-top: 15px;"><strong>วิธีแก้ไข:</strong><br>${instruction.replace(/\n/g, '<br>')}</div>` : '',
        confirmButtonText: 'ตกลง',
        width: 400
      });
      
      $('#barcodeModal').modal('hide');
    }
  });
});

// ทดสอบกล้อง
$(document).on('click', '#testCameraBtn', async function(){
  let btn = $(this);
  let originalContent = btn.html();
  
  btn.html('<div class="loading"></div> ทดสอบ...').prop('disabled', true);
  
  try {
    // ตรวจสอบการรองรับก่อน
    const support = checkCameraSupport();
    
    console.log('=== Camera Support Check ===');
    console.log('Support Result:', support);
    console.log('Protocol:', location.protocol);
    console.log('Hostname:', location.hostname);
    console.log('User Agent:', navigator.userAgent);
    
    if (!support.supported) {
      throw new Error(support.message);
    }
    
    const stream = await navigator.mediaDevices.getUserMedia({ 
      video: { 
        facingMode: "environment",
        width: { ideal: 640 },
        height: { ideal: 480 }
      } 
    });
    
    console.log('Camera stream obtained successfully');
    
    // แสดงวิดีโอในช่วงสั้นๆ
    const video = document.createElement('video');
    video.srcObject = stream;
    video.play();
    
    setTimeout(() => {
      stream.getTracks().forEach(track => track.stop());
      showToast(`กล้องทำงานปกติ! (${support.message})`, 'success');
    }, 1000);
    
  } catch (err) {
    console.error('Camera test error:', err);
    
    const support = checkCameraSupport();
    let errorMsg = 'ทดสอบกล้องล้มเหลว: ';
    let suggestion = '';
    let debugInfo = `
      Debug Info:
      - รองรับ: ${support.supported ? 'ใช่' : 'ไม่'}
      - Navigator: ${support.hasNavigator ? 'ใช่' : 'ไม่'}
      - MediaDevices: ${support.hasMediaDevices ? 'ใช่' : 'ไม่'}
      - GetUserMedia: ${support.hasGetUserMedia ? 'ใช่' : 'ไม่'}
      - Legacy API: ${support.hasLegacyAPI ? 'ใช่' : 'ไม่'}
      - Protocol: ${location.protocol}
      - Error: ${err.message}
    `;
    
    if(err.message.includes('Navigator')) {
      errorMsg += 'เบราว์เซอร์ไม่รองรับ';
      suggestion = 'กรุณาใช้เบราว์เซอร์ Chrome, Firefox, หรือ Safari';
    } else if(err.message.includes('HTTPS')) {
      errorMsg += 'ต้องใช้ HTTPS';
      suggestion = `
        วิธีแก้ไข:<br>
        1. เปิดเว็บไซต์ผ่าน https:// แทน http://<br>
        2. หรือติดตั้ง SSL certificate<br>
        3. หรือใช้ localhost สำหรับการทดสอบ
      `;
    } else if(err.message.includes('getUserMedia') || err.message.includes('รองรับ')) {
      errorMsg += 'เบราว์เซอร์ไม่รองรับ API กล้อง';
      suggestion = `
        วิธีแก้ไข:<br>
        1. อัปเดตเบราว์เซอร์เป็นเวอร์ชันล่าสุด<br>
        2. ใช้ Chrome 53+, Firefox 36+, Safari 11+<br>
        3. เปิดใช้งาน JavaScript<br>
        4. ลองเบราว์เซอร์อื่น
      `;
    } else if(err.name === 'NotAllowedError') {
      errorMsg += 'กรุณาอนุญาตการใช้กล้อง';
      suggestion = 'คลิกไอคอนกล้องในแถบ URL และเลือก "อนุญาต"';
    } else if(err.name === 'NotFoundError') {
      errorMsg += 'ไม่พบกล้อง';
      suggestion = 'ตรวจสอบว่าอุปกรณ์มีกล้องและทำงานปกติ';
    } else if(err.name === 'NotReadableError') {
      errorMsg += 'กล้องถูกใช้งานโดยแอปอื่น';
      suggestion = 'ปิดแอปพลิเคชันที่ใช้กล้องอื่นๆ แล้วลองใหม่';
    } else {
      errorMsg += err.message;
      suggestion = 'ลองรีเฟรชหน้าเว็บและทำใหม่';
    }
    
    console.log(debugInfo);
    
    Swal.fire({
      icon: 'error',
      title: 'ทดสอบกล้องล้มเหลว',
      text: errorMsg,
      html: `
        <div style="text-align: left; margin-top: 10px;">
          <strong>สาเหตุ:</strong> ${errorMsg}<br>
          <strong>แนะนำ:</strong> ${suggestion}<br>
          <details style="margin-top: 10px;">
            <summary style="cursor: pointer; color: #666;">ข้อมูล Debug</summary>
            <pre style="font-size: 10px; background: #f5f5f5; padding: 10px; margin-top: 5px; text-align: left;">${debugInfo}</pre>
          </details>
        </div>
      `,
      confirmButtonText: 'ตกลง',
      width: 500
    });
    
  } finally {
    btn.html(originalContent).prop('disabled', false);
  }
});

// เมื่อเปิด modal
$('#barcodeModal').on('show.bs.modal', function(){
  $('#cameraStatus').show().find('p').text('เตรียมกล้อง...');
  $('#scanInstructions').hide();
  $('#reader').empty();
});

// ปิด modal
$('#barcodeModal').on('hidden.bs.modal', function(){
  // หยุด scanner
  if(html5QrCode) {
    html5QrCode.stop().then(() => {
      html5QrCode.clear();
      html5QrCode = null;
    }).catch(err => {
      console.log('Stop scanner error:', err);
      html5QrCode = null;
    });
  }
  
  // รีเซ็ต UI
  $('#cameraStatus').hide();
  $('#scanInstructions').show();
  $('#reader').empty();
  
  // รีเซ็ต state
  isScanning = false;
  $('.scan-btn').html('<span class="material-icons" style="font-size: 16px;">qr_code_scanner</span> สแกน');
  $('.scan-btn').prop('disabled', false);
});

// Auto-generate SKU based on product name
$('#items-table').on('input', 'input[name*="[name]"]', function(){
  let name = $(this).val();
  let skuInput = $(this).closest('tr').find('input[name*="[sku]"]');
  
  if(name && !skuInput.val()){
    // สร้าง SKU อัตโนมัติจากชื่อสินค้า
    let sku = name.replace(/[^a-zA-Z0-9ก-๙]/g, '').substring(0, 8).toUpperCase();
    if(sku) {
      sku += String(Date.now()).slice(-3); // เพิ่มเลข 3 หลักท้าย
      skuInput.val(sku);
    }
  }
});

// Form validation before submit
$('form').on('submit', function(e){
  console.log('=== FORM SUBMISSION STARTED ===');
  console.log('Form element:', this);
  console.log('Submit event:', e);
  console.log('Current time:', new Date().toISOString());
  
  let isValid = true;
  let emptyFields = [];
  let formData = new FormData(this);
  
  console.log('Form data created:', formData);
  console.log('Form method:', this.method);
  console.log('Form action:', this.action || 'same page');
  
  // Debug: แสดงข้อมูลฟอร์มทั้งหมด
  console.log('=== FORM DATA ENTRIES ===');
  for (let [key, value] of formData.entries()) {
    console.log(`${key}: ${value}`);
  }
  
  // Debug: ตรวจสอบว่าส่วน items มีข้อมูลหรือไม่
  console.log('=== ITEMS VALIDATION ===');
  let itemsFound = false;
  for (let [key, value] of formData.entries()) {
    if (key.startsWith('items[')) {
      itemsFound = true;
      break;
    }
  }
  console.log('Items found in form data:', itemsFound);
  
  // ตรวจสอบว่ามีรายการสินค้าหรือไม่
  let itemCount = $('#items-table tbody tr').length;
  console.log('Number of items in table:', itemCount);
  
  if(itemCount === 0) {
    e.preventDefault();
    Swal.fire({
      icon: 'error',
      title: 'ไม่พบรายการสินค้า',
      text: 'กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ',
      confirmButtonText: 'ตกลง'
    });
    return false;
  }
  
  // ตรวจสอบฟิลด์ที่จำเป็น
  $('#items-table tbody tr').each(function(index){
    let row = $(this);
    let name = row.find('input[name*="[name]"]').val();
    let qty = row.find('input[name*="[qty]"]').val();
    let price = row.find('input[name*="[price]"]').val();
    let currency = row.find('select[name*="[currency]"]').val();
    let rowCode = row.find('select[name*="[row_code]"]').val();
    let bin = row.find('select[name*="[bin]"]').val();
    let shelf = row.find('select[name*="[shelf]"]').val();
    
    console.log(`Item ${index}:`, {name, qty, price, currency, rowCode, bin, shelf});
    
    if(!name) emptyFields.push(`รายการที่ ${index + 1}: ชื่อสินค้า`);
    if(!qty || qty <= 0) emptyFields.push(`รายการที่ ${index + 1}: จำนวน`);
    if(!price && price !== '0') emptyFields.push(`รายการที่ ${index + 1}: ราคาทุน`);
    if(!currency) emptyFields.push(`รายการที่ ${index + 1}: สกุลเงิน`);
    if(!rowCode) emptyFields.push(`รายการที่ ${index + 1}: แถว`);
    if(!bin) emptyFields.push(`รายการที่ ${index + 1}: ล็อค`);
    if(!shelf) emptyFields.push(`รายการที่ ${index + 1}: ชั้น`);
  });
  
  if(emptyFields.length > 0){
    e.preventDefault();
    console.log('Validation errors:', emptyFields);
    Swal.fire({
      icon: 'warning',
      title: 'กรุณากรอกข้อมูลให้ครบถ้วน',
      html: emptyFields.join('<br>'),
      confirmButtonText: 'ตกลง',
      confirmButtonColor: '#f59e0b'
    });
    return false;
  }
  
  console.log('Form validation passed, submitting...');
  
  // แสดง loading
  $(this).find('button[type="submit"]').html('<div class="loading"></div> กำลังบันทึก...').prop('disabled', true);
});

// Toast notification function
function showToast(message, type = 'info') {
  const bgColor = {
    'success': '#10b981',
    'error': '#ef4444', 
    'warning': '#f59e0b',
    'info': '#3b82f6'
  };
  
  Swal.fire({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    icon: type,
    title: message,
    background: bgColor[type],
    color: 'white',
    timerProgressBar: true
  });
}

// Keyboard shortcuts
$(document).on('keydown', function(e){
  // Ctrl + Enter = Submit form
  if(e.ctrlKey && e.which === 13){
    $('form').submit();
  }
  
  // Ctrl + N = Add new row
  if(e.ctrlKey && e.which === 78){
    e.preventDefault();
    $('.add-row-btn').click();
  }
});

// เพิ่ม tooltip สำหรับ keyboard shortcuts
$('.add-row-btn').attr('title', 'เพิ่มรายการ (Ctrl+N)');
$('.save-btn').attr('title', 'บันทึกข้อมูล (Ctrl+Enter)');

// ตรวจสอบความพร้อมใช้งานกล้อง
const cameraSupport = checkCameraSupport();
console.log('Camera support check result:', cameraSupport);

if (cameraSupport.supported) {
  console.log('Camera support: OK -', cameraSupport.message);
} else {
  console.warn('Camera support: FAILED -', cameraSupport.message);
  $('.scan-btn').prop('disabled', true).attr('title', 'เบราว์เซอร์ไม่รองรับการใช้กล้อง: ' + cameraSupport.message);
  
  // แสดงข้อความเตือนในหน้าเว็บ
  setTimeout(() => {
    showToast('เบราว์เซอร์ไม่รองรับการใช้กล้อง: ' + cameraSupport.message, 'warning');
  }, 1000);
}

// ตรวจสอบ HTTPS
const isLocalhost = location.hostname === 'localhost' || 
                   location.hostname === '127.0.0.1' ||
                   location.hostname.includes('local') ||
                   location.hostname.endsWith('.local') ||
                   location.hostname.includes('192.168.') ||
                   location.hostname.includes('10.0.') ||
                   location.hostname === '';

if (location.protocol !== 'https:' && !isLocalhost) {
  console.warn('Camera requires HTTPS to work');
  setTimeout(() => {
    showToast('🔒 กล้องต้องใช้ HTTPS - กรุณาเปลี่ยนเป็น https://', 'error');
  }, 1000);
  // แสดงคำแนะนำเพิ่มเติม
  setTimeout(() => {
    showToast('💡 หรือใช้ปุ่ม "พิมพ์เอง" แทนการสแกน', 'info');
  }, 3000);
} else if (location.protocol === 'http:' && isLocalhost) {
  console.info('Running on localhost HTTP - camera should work');
  setTimeout(() => {
    showToast('✅ ทำงานบน localhost - กล้องพร้อมใช้งาน', 'success');
  }, 1000);
} else if (location.protocol === 'https:') {
  console.info('Running on HTTPS - camera fully supported');
  setTimeout(() => {
    showToast('🔒 HTTPS - กล้องพร้อมใช้งานเต็มรูปแบบ', 'success');
  }, 1000);
}

// ปุ่มพิมพ์เองสำหรับกรณีกล้องใช้ไม่ได้
$(document).on('click', '#manualInputBtn', function(){
  Swal.fire({
    title: 'ป้อนบาร์โค้ดด้วยตนเอง',
    input: 'text',
    inputPlaceholder: 'กรอกหมายเลขบาร์โค้ด...',
    showCancelButton: true,
    confirmButtonText: 'ตกลง',
    cancelButtonText: 'ยกเลิก',
    inputValidator: (value) => {
      if (!value) {
        return 'กรุณากรอกบาร์โค้ด';
      }
      if (value.length < 3) {
        return 'บาร์โค้ดต้องมีอย่างน้อย 3 ตัวอักษร';
      }
    }
  }).then((result) => {
    if (result.isConfirmed && result.value) {
      currentInput.val(result.value);
      $('#barcodeModal').modal('hide');
      showToast('ป้อนบาร์โค้ดสำเร็จ: ' + result.value, 'success');
    }
  });
});

});

// ฟังก์ชันช่วยเหลือ
function showCameraHelp() {
  Swal.fire({
    icon: 'info',
    title: 'วิธีใช้งานการสแกนบาร์โค้ด',
    html: `
      <div style="text-align: left;">
        <h6 style="color: #4f46e5; margin-top: 15px;">📱 การเตรียมอุปกรณ์:</h6>
        <ol style="margin: 10px 0;">
          <li>ตรวจสอบว่าอุปกรณ์มีกล้อง</li>
          <li>เชื่อมต่ออินเทอร์เน็ตที่เสถียร</li>
          <li>ใช้เบราว์เซอร์ Chrome, Firefox, หรือ Safari (เวอร์ชันล่าสุด)</li>
          <li>หากเป็นไปได้ควรใช้ HTTPS แทน HTTP</li>
        </ol>

        <h6 style="color: #10b981; margin-top: 15px;">🔍 ข้อมูลระบบปัจจุบัน:</h6>
        <div style="background: #f3f4f6; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 12px;">
          <div>เบราว์เซอร์: <span id="browserInfo">${navigator.userAgent.split(' ').pop()}</span></div>
          <div>โครตคอล: <span style="color: ${location.protocol === 'https:' ? 'green' : 'orange'}">${location.protocol}</span></div>
          <div>กล้อง API: <span style="color: ${navigator.mediaDevices ? 'green' : 'red'}">${navigator.mediaDevices ? 'รองรับ' : 'ไม่รองรับ'}</span></div>
        </div>

        <h6 style="color: #4f46e5; margin-top: 15px;">🔐 การอนุญาตการใช้กล้อง:</h6>
        <ol style="margin: 10px 0;">
          <li>เมื่อเห็นข้อความขออนุญาต คลิก <strong>"อนุญาต"</strong> หรือ <strong>"Allow"</strong></li>
          <li>หากปฏิเสธแล้ว คลิกไอคอนกล้องในแถบ URL</li>
          <li>เลือก "อนุญาต" แล้วรีเฟรชหน้าเว็บ</li>
        </ol>

        <h6 style="color: #4f46e5; margin-top: 15px;">📸 การสแกนบาร์โค้ด:</h6>
        <ol style="margin: 10px 0;">
          <li>วางบาร์โค้ดให้อยู่ในกรอบสี่เหลี่ยม</li>
          <li>รอให้ระบบสแกนอัตโนมัติ</li>
          <li>หากไม่สำเร็จ ลองขยับกล้องให้ใกล้หรือไกลขึ้น</li>
        </ol>

        <h6 style="color: #ef4444; margin-top: 15px;">⚠️ แก้ไขปัญหา:</h6>
        <ul style="margin: 10px 0;">
          <li><strong>เบราว์เซอร์ไม่รองรับ:</strong> ใช้ Chrome 53+, Firefox 36+, Safari 11+</li>
          <li><strong>HTTP แทน HTTPS:</strong> เปลี่ยนเป็น https:// หรือใช้ localhost</li>
          <li><strong>กล้องไม่เปิด:</strong> ตรวจสอบการอนุญาตและรีเฟรชหน้า</li>
          <li><strong>สแกนไม่ได้:</strong> ลองใช้ปุ่ม "ทดสอบกล้อง" ก่อน</li>
          <li><strong>หน้าจอดำ:</strong> ปิดแอปอื่นที่ใช้กล้องแล้วลองใหม่</li>
          <li><strong>ไม่สามารถสแกนได้:</strong> ใช้ปุ่ม "พิมพ์เอง" แทน</li>
        </ul>
        
        <h6 style="color: #10b981; margin-top: 15px;">💡 ทางเลือก:</h6>
        <ul style="margin: 10px 0;">
          <li><strong>ปุ่ม "พิมพ์เอง":</strong> กรอกบาร์โค้ดด้วยแป้นพิมพ์</li>
          <li><strong>ปุ่ม "ทดสอบกล้อง":</strong> ตรวจสอบการทำงานของกล้อง</li>
          <li><strong>เปิดใน localhost:</strong> ใช้ http://localhost แทน IP อื่น</li>
        </ul>
      </div>
    `,
    width: 600,
    confirmButtonText: 'เข้าใจแล้ว',
    confirmButtonColor: '#4f46e5'
  });
}

// เพิ่ม Console Monitor Panel
var consoleLogs = [];
var originalLog = console.log;
var originalError = console.error;
var originalWarn = console.warn;

console.log = function() {
    originalLog.apply(console, arguments);
    var message = Array.prototype.slice.call(arguments).join(' ');
    addToConsoleMonitor('LOG', message);
};

console.error = function() {
    originalError.apply(console, arguments);
    var message = Array.prototype.slice.call(arguments).join(' ');
    addToConsoleMonitor('ERROR', message);
};

console.warn = function() {
    originalWarn.apply(console, arguments);
    var message = Array.prototype.slice.call(arguments).join(' ');
    addToConsoleMonitor('WARN', message);
};

function addToConsoleMonitor(type, message) {
    var timestamp = new Date().toLocaleTimeString();
    consoleLogs.push({
        type: type,
        message: message,
        timestamp: timestamp
    });
    
    // เก็บแค่ 50 ข้อความล่าสุด
    if (consoleLogs.length > 50) {
        consoleLogs.shift();
    }
    
    updateConsoleMonitor();
}

function updateConsoleMonitor() {
    var panel = document.getElementById('console-monitor');
    if (!panel) return;
    
    var logContainer = panel.querySelector('.console-logs');
    logContainer.innerHTML = '';
    
    consoleLogs.slice(-20).forEach(function(log) {
        var logElement = document.createElement('div');
        logElement.className = 'console-log console-' + log.type.toLowerCase();
        logElement.innerHTML = '<span class="time">' + log.timestamp + '</span> ' +
                              '<span class="type">[' + log.type + ']</span> ' +
                              '<span class="message">' + log.message + '</span>';
        logContainer.appendChild(logElement);
    });
    
    // Auto scroll to bottom
    logContainer.scrollTop = logContainer.scrollHeight;
}

function toggleConsoleMonitor() {
    var panel = document.getElementById('console-monitor');
    if (panel.style.display === 'none' || !panel.style.display) {
        panel.style.display = 'block';
        updateConsoleMonitor();
    } else {
        panel.style.display = 'none';
    }
}

// สร้าง Console Monitor Panel
document.addEventListener('DOMContentLoaded', function() {
    var monitorHTML = `
        <div id="console-monitor" style="
            position: fixed;
            bottom: 10px;
            right: 10px;
            width: 400px;
            height: 300px;
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 8px;
            z-index: 10000;
            display: none;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        ">
            <div style="
                background: #333;
                color: white;
                padding: 8px 12px;
                border-radius: 8px 8px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            ">
                <span>🖥️ Console Monitor</span>
                <button onclick="toggleConsoleMonitor()" style="
                    background: none;
                    border: none;
                    color: white;
                    cursor: pointer;
                    padding: 0 5px;
                ">✕</button>
            </div>
            <div class="console-logs" style="
                height: 250px;
                overflow-y: auto;
                padding: 8px;
            "></div>
        </div>
        
        <button onclick="toggleConsoleMonitor()" style="
            position: fixed;
            bottom: 10px;
            left: 10px;
            background: #333;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            z-index: 9998;
        ">📟 Console</button>
    `;
    
    document.body.insertAdjacentHTML('beforeend', monitorHTML);
    
    // เพิ่ม CSS สำหรับ console logs
    var style = document.createElement('style');
    style.textContent = `
        .console-log {
            margin: 2px 0;
            padding: 3px 5px;
            border-radius: 3px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .console-log .time {
            color: #888;
            font-size: 10px;
        }
        .console-log .type {
            font-weight: bold;
            margin-right: 5px;
        }
        .console-log .message {
            color: #fff;
        }
        .console-log.console-error {
            background: #4a1010;
            color: #ff6b6b;
        }
        .console-log.console-error .type {
            color: #ff4757;
        }
        .console-log.console-warn {
            background: #4a3c10;
            color: #ffa502;
        }
        .console-log.console-warn .type {
            color: #ff7f00;
        }
        .console-log.console-log {
            background: #1a1a1a;
        }
        .console-log.console-log .type {
            color: #70a5fd;
        }
    `;
    document.head.appendChild(style);
});

// เพิ่ม log เริ่มต้น
console.log('=== IMPORT PRODUCT PAGE READY ===');
console.log('Page loaded at:', new Date().toISOString());
console.log('🔍 Console Monitor เริ่มทำงานแล้ว - กดปุ่ม "📟 Console" เพื่อดู logs');
console.log('Debug tools available:');
console.log('  • 🔍 Debug Logs button (top-right corner) - view PHP error logs');
console.log('  • 📟 Console button (bottom-left corner) - view JavaScript logs'); 
console.log('  • F12 → Console tab - browser developer console');

// Alert close functionality
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-close').forEach(function(button) {
        button.addEventListener('click', function() {
            const alert = this.closest('.alert');
            if (alert) {
                alert.style.display = 'none';
            }
        });
    });
});

// Test notification functions
function testSuccessNotification() {
    console.log('Testing success notification...');
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: '🎉 ทดสอบสำเร็จ!',
            html: '✅ ระบบแจ้งเตือนทำงานปกติ<br>📋 Purchase Order: <b>PO20251013001</b><br>📦 จำนวนรายการ: <b>3</b> รายการ',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true,
            confirmButtonText: '✨ เข้าใจแล้ว',
            confirmButtonColor: '#10b981'
        });
    } else {
        alert('ทดสอบสำเร็จ! SweetAlert2 ไม่พร้อมใช้งาน');
    }
}

function testErrorNotification() {
    console.log('Testing error notification...');
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: '❌ ทดสอบข้อผิดพลาด',
            html: '❌ นี่คือการทดสอบการแจ้งเตือนแบบ Error',
            confirmButtonText: '🔍 ตรวจสอบ Debug',
            confirmButtonColor: '#ef4444',
            showCancelButton: true,
            cancelButtonText: '↻ ลองใหม่',
            cancelButtonColor: '#6b7280'
        });
    } else {
        alert('ทดสอบ Error! SweetAlert2 ไม่พร้อมใช้งาน');
    }
}
</script>

</body>
</html>
