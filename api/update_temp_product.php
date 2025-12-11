<?php
session_start();
require '../config/db_connect.php';

function saveBinaryImageToFilesystem($binaryData, $temp_product_id, $upload_dir, $gd_available) {
    if (!is_string($binaryData) || $binaryData === '') {
        return null;
    }

    $original_size = strlen($binaryData);
    if ($original_size === 0) {
        return null;
    }

    try {
        $randomSuffix = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $randomSuffix = bin2hex(openssl_random_pseudo_bytes(4));
        } else {
            $randomSuffix = substr(str_replace('.', '', uniqid('', true)), -8);
        }
    }

    $unique_name = 'temp_product_' . $temp_product_id . '_' . time() . '_' . $randomSuffix . '.jpg';
    $file_path = $upload_dir . $unique_name;
    $relative_path = 'images/' . $unique_name;
    $message = 'ภาพถูกจัดเก็บจากข้อมูลเดิม';

    if ($gd_available) {
        $source_image = @imagecreatefromstring($binaryData);
        if ($source_image !== false) {
            $original_width = imagesx($source_image);
            $original_height = imagesy($source_image);

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

            $resized_image = imagecreatetruecolor($new_width, $new_height);
            $white = imagecolorallocate($resized_image, 255, 255, 255);
            imagefill($resized_image, 0, 0, $white);
            imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

            if (imagejpeg($resized_image, $file_path, 85)) {
                $compressed_size = filesize($file_path);
                $ratio = $original_size > 0 ? round(($compressed_size / $original_size) * 100, 1) : 100;
                $message = 'ภาพถูกจัดเก็บจากข้อมูลเดิม: ' . round($original_size / 1024, 1) . 'KB → ' . round($compressed_size / 1024, 1) . 'KB (' . $ratio . '%)';
                imagedestroy($resized_image);
                imagedestroy($source_image);
                return [
                    'path' => $relative_path,
                    'filename' => $unique_name,
                    'message' => $message
                ];
            }

            imagedestroy($resized_image);
            imagedestroy($source_image);
        }
    }

    if (file_put_contents($file_path, $binaryData) !== false) {
        return [
            'path' => $relative_path,
            'filename' => $unique_name,
            'message' => 'ภาพถูกจัดเก็บจากข้อมูลเดิม (ไม่บีบอัด)'
        ];
    }

    return null;
}

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
$stored_image_path = null;
$compression_info = '';
$upload_dir = '../images/';
$gd_available = extension_loaded('gd');

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
                    $stored_image_path = 'images/' . $unique_name;
                    
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
                $stored_image_path = 'images/' . $unique_name;
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

if ($stored_image_path === null) {
    $stmtCurrentImage = $pdo->prepare("SELECT product_image FROM temp_products WHERE temp_product_id = :temp_product_id");
    $stmtCurrentImage->execute([':temp_product_id' => $temp_product_id]);
    $existingImage = $stmtCurrentImage->fetchColumn();

    if ($existingImage !== false && $existingImage !== null) {
        if (is_resource($existingImage)) {
            $existingImage = stream_get_contents($existingImage);
        }

        if (is_string($existingImage)) {
            $existingImageTrimmed = trim($existingImage);

            if ($existingImageTrimmed !== '') {
                $existingPathCandidate = null;
                $binaryData = null;

                if (stripos($existingImageTrimmed, 'data:') === 0) {
                    $commaPos = strpos($existingImageTrimmed, ',');
                    if ($commaPos !== false) {
                        $base64Part = substr($existingImageTrimmed, $commaPos + 1);
                        $decoded = base64_decode($base64Part, true);
                        $binaryData = $decoded !== false ? $decoded : $existingImage;
                    }
                } else {
                    $sanitizedExisting = preg_replace('/\s+/', '', $existingImageTrimmed);
                    $isLikelyBase64 = preg_match('/^[A-Za-z0-9+\/]+=*$/', $sanitizedExisting) && strlen($sanitizedExisting) >= 60 && strlen($sanitizedExisting) % 4 === 0;

                    if ($isLikelyBase64) {
                        $decoded = base64_decode($sanitizedExisting, true);
                        $binaryData = $decoded !== false ? $decoded : $existingImage;
                    } elseif (stripos($existingImageTrimmed, 'images/') === 0) {
                        $existingPathCandidate = ltrim($existingImageTrimmed, './');
                    } elseif (strpos($existingImageTrimmed, '../') === 0) {
                        $existingPathCandidate = ltrim($existingImageTrimmed, './');
                    } elseif ($existingImageTrimmed[0] === '/' || stripos($existingImageTrimmed, 'http') === 0) {
                        $existingPathCandidate = $existingImageTrimmed;
                    } else {
                        $binaryData = $existingImage;
                    }
                }

                if ($existingPathCandidate !== null) {
                    $stored_image_path = $existingPathCandidate;
                    $image_filename = basename($stored_image_path);
                    error_log('Reusing existing image path: ' . $stored_image_path);
                } elseif (is_string($binaryData) && strlen($binaryData) > 0) {
                    $saveResult = saveBinaryImageToFilesystem($binaryData, $temp_product_id, $upload_dir, $gd_available);
                    if ($saveResult) {
                        $stored_image_path = $saveResult['path'];
                        $image_filename = $saveResult['filename'];
                        $message = $saveResult['message'];
                        if ($compression_info) {
                            $compression_info .= '\n' . $message;
                        } else {
                            $compression_info = $message;
                        }
                        error_log('Existing image converted to file: ' . $stored_image_path);
                    } else {
                        error_log('Failed to convert existing image data to file');
                    }
                }
            }
        }
    }
}

try {
    // อัปเดต temp_products - บันทึก provisional_sku, provisional_barcode และชื่อไฟล์รูปภาพ
    $sql = "UPDATE temp_products SET 
            provisional_sku = :provisional_sku,
            provisional_barcode = :provisional_barcode";
    
    if ($stored_image_path !== null) {
        $sql .= ", product_image = :product_image";
        error_log("Will update product_image to: " . $stored_image_path);
    }
    
    $sql .= " WHERE temp_product_id = :temp_product_id";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        ':provisional_sku' => $provisional_sku,
        ':provisional_barcode' => $provisional_barcode,
        ':temp_product_id' => $temp_product_id
    ];
    
    if ($stored_image_path !== null) {
        $params[':product_image'] = $stored_image_path;
    }
    
    $result = $stmt->execute($params);
    error_log("Update query result: " . ($result ? 'true' : 'false'));
    error_log("Rows affected: " . $stmt->rowCount());
    
    // หากมี expiry_date ให้อัปเดต receive_items
        if ($stored_image_path !== null) {
            $sqlProductImage = "UPDATE products SET image = :image_path
                                WHERE product_id IN (
                                    SELECT DISTINCT poi.product_id
                                    FROM purchase_order_items poi
                                    WHERE poi.temp_product_id = :temp_product_id
                                      AND poi.product_id IS NOT NULL
                                )";
            $stmtProductImage = $pdo->prepare($sqlProductImage);
            $stmtProductImage->execute([
                ':image_path' => $stored_image_path,
                ':temp_product_id' => $temp_product_id
            ]);
            error_log("Products image updated rows: " . $stmtProductImage->rowCount());
        }

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
        'image_filename' => $image_filename,
        'image_path' => $stored_image_path
    ]);
    
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>



