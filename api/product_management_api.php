<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../config/db_connect.php';

$action = $_POST['action'] ?? '';

// Log for debugging
error_log('Product Management API called with action: ' . $action);
error_log('POST data: ' . json_encode($_POST));
error_log('FILES data: ' . json_encode(array_keys($_FILES)));

// Function to resize and compress image
function resizeAndCompressImage($file) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('เกิดข้อผิดพลาดในการอัปโหลดไฟล์: ' . $file['error']);
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('ประเภทไฟล์ไม่ถูกต้อง (JPG, PNG, GIF เท่านั้น)');
    }
    
    if ($file['size'] > 10 * 1024 * 1024) { // 10MB upload limit
        throw new Exception('ไฟล์ใหญ่เกินไป (ไม่เกิน 10MB)');
    }
    
    $upload_dir = '../images/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้');
        }
    }
    
    // Determine file extension
    $ext = '';
    switch ($file['type']) {
        case 'image/jpeg':
            $ext = 'jpg';
            break;
        case 'image/png':
            $ext = 'png';
            break;
        case 'image/gif':
            $ext = 'gif';
            break;
        default:
            throw new Exception('ประเภทไฟล์ไม่รองรับ');
    }
    
    // Generate filename with timestamp
    $timestamp = date('YmdHis');
    $filename = $timestamp . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    // Ensure unique filename
    $counter = 0;
    while (file_exists($filepath)) {
        $counter++;
        $filename = $timestamp . '_' . $counter . '.' . $ext;
        $filepath = $upload_dir . $filename;
    }
    
    // Check if GD is available for image processing
    if (extension_loaded('gd')) {
        try {
            // Load image
            $image = null;
            switch ($file['type']) {
                case 'image/jpeg':
                    $image = @imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'image/png':
                    $image = @imagecreatefrompng($file['tmp_name']);
                    break;
                case 'image/gif':
                    $image = @imagecreatefromgif($file['tmp_name']);
                    break;
            }
            
            if ($image) {
                $width = imagesx($image);
                $height = imagesy($image);
                
                // Resize if larger than 800x800
                $max_width = 800;
                $max_height = 800;
                $ratio = min($max_width / $width, $max_height / $height);
                
                if ($ratio < 1) {
                    $new_width = (int)($width * $ratio);
                    $new_height = (int)($height * $ratio);
                    
                    $resized = imagecreatetruecolor($new_width, $new_height);
                    
                    // Preserve transparency for PNG and GIF
                    if ($ext === 'png' || $ext === 'gif') {
                        imagealphablending($resized, false);
                        imagesavealpha($resized, true);
                        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                        imagefill($resized, 0, 0, $transparent);
                    }
                    
                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                    imagedestroy($image);
                    $image = $resized;
                }
                
                // Save compressed image
                $save_success = false;
                switch ($ext) {
                    case 'jpg':
                        $save_success = imagejpeg($image, $filepath, 85);
                        break;
                    case 'png':
                        $save_success = imagepng($image, $filepath, 8);
                        break;
                    case 'gif':
                        $save_success = imagegif($image, $filepath);
                        break;
                }
                
                imagedestroy($image);
                
                if (!$save_success) {
                    throw new Exception('ไม่สามารถบันทึกรูปภาพที่ถูกบีบอัดได้');
                }
            } else {
                // If can't load image, just save as is
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    throw new Exception('ไม่สามารถบันทึกไฟล์ได้');
                }
            }
        } catch (Exception $e) {
            // If image processing fails, try to save as is
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('ไม่สามารถบันทึกไฟล์ได้');
            }
        }
    } else {
        // GD not available, just save the file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('ไม่สามารถบันทึกไฟล์ได้');
        }
    }
    
    return 'images/' . $filename;
}

try {
    switch ($action) {
        case 'create':
            $name = $_POST['name'] ?? '';
            $sku = $_POST['sku'] ?? '';
            $barcode = $_POST['barcode'] ?? '';
            $unit = $_POST['unit'] ?? '';
            $product_category_id = $_POST['product_category_id'] ?? null;
            $remark_color = $_POST['remark_color'] ?? '';
            $remark_split = $_POST['remark_split'] ?? '';
            $location_id = $_POST['location_id'] ?? null;
            $is_active = $_POST['is_active'] ?? 1;
            $image = null;
            
            error_log("Create: name=$name, sku=$sku, barcode=$barcode, unit=$unit");
            
            if (!$name || !$sku || !$barcode || !$unit) {
                throw new Exception('กรุณากรอกข้อมูลที่จำเป็น: name=' . ($name ? 'ok' : 'no') . ', sku=' . ($sku ? 'ok' : 'no') . ', barcode=' . ($barcode ? 'ok' : 'no') . ', unit=' . ($unit ? 'ok' : 'no'));
            }
            
            // Handle image upload and compression
            if (isset($_FILES['image'])) {
                error_log("Image file detected, processing...");
                $image = resizeAndCompressImage($_FILES['image']);
                error_log("Image saved as: " . $image);
            } else {
                error_log("No image file in request");
            }
            
            // ตรวจสอบ SKU และ Barcode ซ้ำ
            $check_sql = "SELECT product_id FROM products WHERE sku = ? OR barcode = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$sku, $barcode]);
            if ($check_stmt->fetch()) {
                throw new Exception('SKU หรือ Barcode นี้มีอยู่แล้ว');
            }
            
            error_log("Inserting product...");
            $sql = "INSERT INTO products (name, sku, barcode, unit, product_category_id, remark_color, remark_split, image, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $sku, $barcode, $unit, $product_category_id ?: null, $remark_color, $remark_split, $image, $is_active]);
            
            $product_id = $pdo->lastInsertId();
            error_log("Product created with ID: " . $product_id);
            
            // บันทึกตำแหน่งที่จัดเก็บสินค้า
            $row_code = $_POST['row_code'] ?? '';
            $bin = $_POST['bin'] ?? '';
            $shelf = $_POST['shelf'] ?? '';
            
            if ($row_code || $bin || $shelf) {
                // ค้นหา location_id ที่ตรงกับ row_code, bin, shelf
                $find_loc_sql = "SELECT location_id FROM locations WHERE row_code = ? AND bin = ? AND shelf = ?";
                $find_loc_stmt = $pdo->prepare($find_loc_sql);
                $find_loc_stmt->execute([$row_code, $bin, $shelf]);
                $loc_result = $find_loc_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($loc_result) {
                    $location_id = $loc_result['location_id'];
                    // เพิ่มตำแหน่งใหม่
                    $loc_sql = "INSERT INTO product_location (product_id, location_id) VALUES (?, ?)";
                    $loc_stmt = $pdo->prepare($loc_sql);
                    $loc_stmt->execute([$product_id, $location_id]);
                    error_log("Location assigned: $location_id");
                }
            } elseif ($location_id) {
                // ลบตำแหน่งเก่า (ถ้ามี)
                $del_sql = "DELETE FROM product_location WHERE product_id = ?";
                $del_stmt = $pdo->prepare($del_sql);
                $del_stmt->execute([$product_id]);
                
                // เพิ่มตำแหน่งใหม่
                $loc_sql = "INSERT INTO product_location (product_id, location_id) VALUES (?, ?)";
                $loc_stmt = $pdo->prepare($loc_sql);
                $loc_stmt->execute([$product_id, $location_id]);
                error_log("Location assigned: $location_id");
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'เพิ่มสินค้าสำเร็จ',
                'product_id' => $product_id
            ]);
            break;
            
        case 'update':
            $product_id = $_POST['product_id'] ?? '';
            $name = $_POST['name'] ?? '';
            $sku = $_POST['sku'] ?? '';
            $barcode = $_POST['barcode'] ?? '';
            $unit = $_POST['unit'] ?? '';
            $product_category_id = $_POST['product_category_id'] ?? null;
            $remark_color = $_POST['remark_color'] ?? '';
            $remark_split = $_POST['remark_split'] ?? '';
            $location_id = $_POST['location_id'] ?? null;
            $is_active = $_POST['is_active'] ?? 1;
            
            if (!$name || !$sku || !$barcode || !$unit || !$product_id) {
                throw new Exception('กรุณากรอกข้อมูลที่จำเป็น');
            }
            
            // ตรวจสอบ SKU และ Barcode ซ้ำ (ยกเว้นตัวเอง)
            $check_sql = "SELECT product_id FROM products WHERE (sku = ? OR barcode = ?) AND product_id != ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$sku, $barcode, $product_id]);
            if ($check_stmt->fetch()) {
                throw new Exception('SKU หรือ Barcode นี้มีอยู่แล้ว');
            }
            
            // Get current product data
            $current_sql = "SELECT image FROM products WHERE product_id = ?";
            $current_stmt = $pdo->prepare($current_sql);
            $current_stmt->execute([$product_id]);
            $current_product = $current_stmt->fetch(PDO::FETCH_ASSOC);
            $image = $current_product['image'];
            
            // Handle new image upload and compression
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $image = resizeAndCompressImage($_FILES['image']);
            }
            
            $sql = "UPDATE products SET name = ?, sku = ?, barcode = ?, unit = ?, product_category_id = ?, 
                    remark_color = ?, remark_split = ?, image = ?, is_active = ? WHERE product_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $sku, $barcode, $unit, $product_category_id ?: null, $remark_color, $remark_split, $image, $is_active, $product_id]);
            
            // อัปเดตตำแหน่งที่จัดเก็บสินค้า
            $row_code = $_POST['row_code'] ?? '';
            $bin = $_POST['bin'] ?? '';
            $shelf = $_POST['shelf'] ?? '';
            
            if ($row_code || $bin || $shelf) {
                // ค้นหา location_id ที่ตรงกับ row_code, bin, shelf
                $find_loc_sql = "SELECT location_id FROM locations WHERE row_code = ? AND bin = ? AND shelf = ?";
                $find_loc_stmt = $pdo->prepare($find_loc_sql);
                $find_loc_stmt->execute([$row_code, $bin, $shelf]);
                $loc_result = $find_loc_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($loc_result) {
                    $location_id = $loc_result['location_id'];
                    // ลบตำแหน่งเก่า
                    $del_sql = "DELETE FROM product_location WHERE product_id = ?";
                    $del_stmt = $pdo->prepare($del_sql);
                    $del_stmt->execute([$product_id]);
                    
                    // เพิ่มตำแหน่งใหม่
                    $loc_sql = "INSERT INTO product_location (product_id, location_id) VALUES (?, ?)";
                    $loc_stmt = $pdo->prepare($loc_sql);
                    $loc_stmt->execute([$product_id, $location_id]);
                    error_log("Location updated: $location_id");
                } else {
                    // ไม่พบ location ที่ตรงกัน ลบตำแหน่งเก่า
                    $del_sql = "DELETE FROM product_location WHERE product_id = ?";
                    $del_stmt = $pdo->prepare($del_sql);
                    $del_stmt->execute([$product_id]);
                }
            } elseif ($location_id) {
                // อัปเดตจาก location_id dropdown
                // ลบตำแหน่งเก่า
                $del_sql = "DELETE FROM product_location WHERE product_id = ?";
                $del_stmt = $pdo->prepare($del_sql);
                $del_stmt->execute([$product_id]);
                
                // เพิ่มตำแหน่งใหม่
                $loc_sql = "INSERT INTO product_location (product_id, location_id) VALUES (?, ?)";
                $loc_stmt = $pdo->prepare($loc_sql);
                $loc_stmt->execute([$product_id, $location_id]);
                error_log("Location updated: $location_id");
            } else {
                // ลบตำแหน่งเก่า ถ้าไม่เลือก
                $del_sql = "DELETE FROM product_location WHERE product_id = ?";
                $del_stmt = $pdo->prepare($del_sql);
                $del_stmt->execute([$product_id]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'แก้ไขสินค้าสำเร็จ'
            ]);
            break;
            
        case 'delete':
            $product_id = $_POST['product_id'] ?? '';
            
            if (!$product_id) {
                throw new Exception('ไม่พบ ID สินค้า');
            }
            
            $sql = "DELETE FROM products WHERE product_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$product_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'ลบสินค้าสำเร็จ'
            ]);
            break;
            
        case 'toggle_status':
            $product_id = $_POST['product_id'] ?? '';
            $is_active = $_POST['is_active'] ?? 1;
            
            if (!$product_id) {
                throw new Exception('ไม่พบ ID สินค้า');
            }
            
            $sql = "UPDATE products SET is_active = ? WHERE product_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$is_active, $product_id]);
            
            $status_text = $is_active == 1 ? 'ขายอยู่' : 'หยุดขาย';
            
            echo json_encode([
                'success' => true,
                'message' => "เปลี่ยนสถานะเป็น $status_text สำเร็จ"
            ]);
            break;
            
        default:
            throw new Exception('ไม่พบ Action');
    }
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Product Management API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
