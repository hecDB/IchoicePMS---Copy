<?php
session_start();
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$temp_product_id = isset($_POST['temp_product_id']) ? (int)$_POST['temp_product_id'] : 0;
$provisional_sku = isset($_POST['provisional_sku']) ? trim($_POST['provisional_sku']) : '';
$provisional_barcode = isset($_POST['provisional_barcode']) ? trim($_POST['provisional_barcode']) : '';
$expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

// Debug logging
error_log("=== UPDATE_TEMP_PRODUCT START ===");
error_log("temp_product_id: " . $temp_product_id);
error_log("Files received: " . count($_FILES));
error_log("POST keys: " . implode(', ', array_keys($_POST)));

if (!$temp_product_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID สินค้า']);
    exit;
}

// จัดการอัพโหลดรูปภาพ
$image_filename = null;
$compression_info = '';
$upload_dir = '../images/';

// สร้างโฟลเดอร์ถ้ายังไม่มี
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    error_log("Created upload directory: " . $upload_dir);
}

if (isset($_FILES['product_image'])) {
    error_log("File received: " . $_FILES['product_image']['name']);
    error_log("File error: " . $_FILES['product_image']['error']);
    error_log("File type: " . $_FILES['product_image']['type']);
    error_log("File size: " . $_FILES['product_image']['size']);
    
    if ($_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['product_image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            error_log("Invalid file type: " . $file_type);
            echo json_encode(['success' => false, 'message' => 'รองรับเฉพาะไฟล์ JPEG, PNG, GIF, WEBP เท่านั้น']);
            exit;
        }
        
        $original_size = $_FILES['product_image']['size'];
        error_log("Processing image, original size: " . $original_size);
        
        // Check if GD library is available
        $gd_available = extension_loaded('gd');
        error_log("GD Library available: " . ($gd_available ? 'yes' : 'no'));
        
        if ($gd_available) {
            // Use GD Library for server-side compression
            $source_image = null;
            
            switch ($file_type) {
                case 'image/jpeg':
                    $source_image = @imagecreatefromjpeg($_FILES['product_image']['tmp_name']);
                    break;
                case 'image/png':
                    $source_image = @imagecreatefrompng($_FILES['product_image']['tmp_name']);
                    break;
                case 'image/gif':
                    $source_image = @imagecreatefromgif($_FILES['product_image']['tmp_name']);
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $source_image = @imagecreatefromwebp($_FILES['product_image']['tmp_name']);
                    }
                    break;
            }
            
            if ($source_image) {
                error_log("Image resource created successfully");
                
                // Get original dimensions
                $original_width = imagesx($source_image);
                $original_height = imagesy($source_image);
                error_log("Original dimensions: {$original_width}x{$original_height}");
                
                // Calculate new dimensions (max 1200x1200)
                $max_size = 1200;
                $new_width = $original_width;
                $new_height = $original_height;
                
                if ($original_width > $original_height) {
                    if ($original_width > $max_size) {
                        $new_height = round($original_height * ($max_size / $original_width));
                        $new_width = $max_size;
                    }
                } else {
                    if ($original_height > $max_size) {
                        $new_width = round($original_width * ($max_size / $original_height));
                        $new_height = $max_size;
                    }
                }
                
                error_log("Resized dimensions: {$new_width}x{$new_height}");
                
                // Create resized image
                $resized_image = imagecreatetruecolor($new_width, $new_height);
                
                // Fill with white background for transparency
                $white = imagecolorallocate($resized_image, 255, 255, 255);
                imagefill($resized_image, 0, 0, $white);
                
                imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
                
                // Generate unique filename
                $unique_name = 'temp_product_' . $temp_product_id . '_' . time() . '.jpg';
                $file_path = $upload_dir . $unique_name;
                
                error_log("Saving to: " . $file_path);
                
                // Save as JPEG with compression (quality 85)
                if (imagejpeg($resized_image, $file_path, 85)) {
                    $image_filename = $unique_name;
                    
                    // Get file size
                    $compressed_size = filesize($file_path);
                    $ratio = round(($compressed_size / $original_size) * 100, 1);
                    $compression_info = "ภาพถูกบีบอัด: " . round($original_size / 1024, 1) . "KB → " . round($compressed_size / 1024, 1) . "KB (" . $ratio . "%)";
                    error_log("File saved successfully: " . $file_path . " (" . $compressed_size . " bytes)");
                } else {
                    error_log("Failed to save JPEG file");
                }
                
                imagedestroy($source_image);
                imagedestroy($resized_image);
            } else {
                error_log("Failed to create image resource from type: " . $file_type);
            }
        } else {
            // GD not available - save original file directly
            error_log("GD Library not available, saving original file");
            
            // Determine file extension
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];
            
            $file_ext = $extensions[$file_type] ?? 'jpg';
            $unique_name = 'temp_product_' . $temp_product_id . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $unique_name;
            
            error_log("Saving to: " . $file_path);
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $file_path)) {
                $image_filename = $unique_name;
                $compressed_size = filesize($file_path);
                $ratio = 100; // ไม่ได้บีบอัด
                $compression_info = "ไฟล์ที่อัปโหลด: " . round($original_size / 1024, 1) . "KB (ไม่บีบอัดเนื่องจาก GD ไม่พร้อม)";
                error_log("File saved successfully: " . $file_path . " (" . $compressed_size . " bytes)");
            } else {
                error_log("Failed to move uploaded file");
            }
        }
    } else {
        error_log("File upload error code: " . $_FILES['product_image']['error']);
    }
} else {
    error_log("No file in _FILES");
}

try {
    // อัปเดต temp_products - บันทึก provisional_sku, provisional_barcode และชื่อไฟล์รูปภาพ
    $sql = "UPDATE temp_products SET 
            provisional_sku = :provisional_sku,
            provisional_barcode = :provisional_barcode";
    
    if ($image_filename !== null) {
        $sql .= ", product_image = :product_image";
        error_log("Will update product_image to: " . $image_filename);
    }
    
    $sql .= " WHERE temp_product_id = :temp_product_id";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        ':provisional_sku' => $provisional_sku,
        ':provisional_barcode' => $provisional_barcode,
        ':temp_product_id' => $temp_product_id
    ];
    
    if ($image_filename !== null) {
        $params[':product_image'] = $image_filename;
    }
    
    $result = $stmt->execute($params);
    error_log("Update query result: " . ($result ? 'true' : 'false'));
    error_log("Rows affected: " . $stmt->rowCount());
    
    // หากมี expiry_date ให้อัปเดต receive_items
    if (!empty($expiry_date)) {
        $sql_receive = "UPDATE receive_items ri
                        SET ri.expiry_date = :expiry_date
                        WHERE ri.item_id IN (
                            SELECT poi.item_id FROM purchase_order_items poi
                            WHERE poi.temp_product_id = :temp_product_id
                        )";
        
        $stmt_receive = $pdo->prepare($sql_receive);
        $stmt_receive->execute([
            ':expiry_date' => $expiry_date,
            ':temp_product_id' => $temp_product_id
        ]);
        error_log("Expiry date updated, rows: " . $stmt_receive->rowCount());
    }
    
    error_log("=== UPDATE_TEMP_PRODUCT SUCCESS ===");
    
    echo json_encode([
        'success' => true, 
        'message' => 'บันทึกสำเร็จ',
        'compression_info' => $compression_info,
        'image_filename' => $image_filename
    ]);
    
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>



