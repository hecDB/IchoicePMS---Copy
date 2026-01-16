<?php
// Clear any existing output buffer
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response
ini_set('log_errors', 1);
ini_set('error_log', '../logs/api_errors.log');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../config/db_connect.php';
    require_once __DIR__ . '/../includes/tag_validator.php';

    // Check if PDO connection exists
    if (!isset($pdo)) {
        throw new Exception('PDO connection not established');
    }
    
} catch (Exception $e) {
    error_log('Database connection failed in issue_product_api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get session user
session_start();
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
$issue_tag = $data['issue_tag'] ?? '';
$products = $data['products'] ?? [];

if (empty($issue_tag)) {
    echo json_encode(['success' => false, 'message' => 'แท็คส่งออกจำเป็นต้องระบุ']);
    exit;
}

if (empty($products) || !is_array($products)) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลสินค้าที่จะยิงออก']);
    exit;
}

error_log('Issue request: Tag=' . $issue_tag . ', Products=' . count($products) . ', User=' . $user_id . ', Selected Platform=' . ($data['selected_platform'] ?? 'null'));

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // ตรวจสอบรูปแบบแท็คส่งออกผ่านระบบ pattern กลาง หากไม่พบให้ fallback ไปตรรกะเดิม
    $platform = '';
    $patternName = '';
    $selectedPlatform = $data['selected_platform'] ?? null;
    $selectedPatternName = $data['selected_pattern_name'] ?? null;
    
    error_log('Selected platform from request: ' . ($selectedPlatform ? $selectedPlatform : 'NULL'));
    error_log('Selected pattern_name from request: ' . ($selectedPatternName ? $selectedPatternName : 'NULL'));
    
    try {
        // ถ้ามี selected_platform ให้ validate กับ platform ที่เลือกเฉพาะนั้น
        $validation = validateTagNumber($issue_tag, $selectedPlatform);
        
        error_log('Validation result: ' . json_encode($validation));
        
        // ถ้ามีหลาย pattern ตรงกัน และไม่มี selected_platform ให้คืน response ขอให้เลือก platform ก่อน
        if (!empty($validation['needs_confirmation']) && $validation['needs_confirmation'] && !$selectedPlatform) {
            $pdo->rollBack();
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'needs_platform_confirmation' => true,
                'tag' => $issue_tag,
                'matched_patterns' => $validation['matched_patterns'] ?? [],
                'message' => 'โปรดเลือกว่าแท็คนี้อยู่ในแพลตฟอร์มไหน'
            ]);
            exit;
        }
        
        if (!empty($validation['valid'])) {
            $platform = $validation['platform'] ?? '';
            $patternName = $validation['pattern']['pattern_name'] ?? $validation['pattern_name'] ?? '';
        } elseif ($selectedPlatform) {
            // ถ้า validation ไม่ผ่าน แต่มี selected_platform ให้ใช้ selected_platform
            $platform = $selectedPlatform;
            $patternName = '';
        }
    } catch (Throwable $t) {
        error_log('Tag validator unavailable: ' . $t->getMessage());
    }

    // ถ้ามี pattern ที่ผู้ใช้เลือกมาให้ใช้ข้อมูลนั้นเป็นหลัก
    if ($selectedPatternName) {
        $patternName = $selectedPatternName;
    }

    // ถ้ายังไม่มี platform และไม่มี selectedPlatform ให้ใช้ fallback logic
    if ($platform === '' && !$selectedPlatform) {
        if (strlen($issue_tag) == 14) {
            $first_six = substr($issue_tag, 0, 6);
            $seventh_char = substr($issue_tag, 6, 1);

            if (ctype_digit($first_six) && ctype_alpha($seventh_char)) {
                $platform = 'Shopee';
            }
            if ($platform === '' && ctype_digit($issue_tag)) {
                $platform = 'Lazada';
            }
        } elseif (strlen($issue_tag) == 16 && ctype_digit($issue_tag)) {
            $platform = 'Lazada';
        }
    } elseif ($platform === '' && $selectedPlatform) {
        // ถ้า validation ไม่ผ่าน แต่มี selectedPlatform ให้ใช้
        $platform = $selectedPlatform;
    }
    
    // ถ้าเลือก platform ไปแล้ว ไม่ว่า validation ผลลัพธ์เป็นอะไรก็ตาม ให้ใช้ selectedPlatform
    if ($selectedPlatform && $platform !== $selectedPlatform) {
        error_log("Platform mismatch: validation said '$platform', but selected is '$selectedPlatform'. Using selected.");
        $platform = $selectedPlatform;
        // ใช้ pattern_name ที่เลือกมา
        if ($selectedPatternName) {
            $patternName = $selectedPatternName;
        }
    }

    if ($platform === '') {
        $platform = 'Internal';
    }
    
    error_log("Final platform for issue: $platform, Pattern: $patternName");
    
    // สร้าง Sales Order ก่อน
    $insert_sale_order = $pdo->prepare("
        INSERT INTO sales_orders (
            issue_tag, 
            platform, 
            total_amount, 
            total_items, 
            issued_by, 
            remark,
            sale_date
        ) VALUES (?, ?, 0.00, ?, ?, ?, NOW())
    ");
    
    $total_items = count($products);
    $remarkParts = ["แท็คส่งออก: {$issue_tag}"];
    if (!empty($platform)) {
        $remarkParts[] = "แพลตฟอร์ม: {$platform}";
    }
    if (!empty($patternName)) {
        $remarkParts[] = "รูปแบบ: {$patternName}";
    }
    $remark = implode(' | ', $remarkParts);
    
    $sale_order_result = $insert_sale_order->execute([
        $issue_tag,
        $platform,
        $total_items,
        $user_id,
        $remark
    ]);
    
    if (!$sale_order_result) {
        $errorInfo = $insert_sale_order->errorInfo();
        error_log("Failed to create sale order: " . json_encode($errorInfo));
        throw new Exception('ไม่สามารถสร้างรายการขายได้: ' . ($errorInfo[2] ?? 'Unknown error'));
    }
    
    $sale_order_id = $pdo->lastInsertId();
    error_log("Created sale_order_id: {$sale_order_id} with platform: {$platform}, remark: {$remark}");
    
    $stockCheckStmt = $pdo->prepare("
        SELECT 
            ri.receive_qty,
            p.name,
            p.sku,
            COALESCE(poi.price_per_unit, 0) AS cost_price
        FROM receive_items ri
        LEFT JOIN purchase_order_items poi ON poi.item_id = ri.item_id
        LEFT JOIN products p ON p.product_id = poi.product_id OR p.product_id = ?
        WHERE ri.receive_id = ?
        LIMIT 1
    ");

    $insertIssueStmt = $pdo->prepare("
        INSERT INTO issue_items (
            product_id, 
            receive_id,
            sale_order_id,
            issue_qty, 
            sale_price,
            cost_price,
            issued_by, 
            remark,
            expiry_date,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $updateReceiveStmt = $pdo->prepare("
        UPDATE receive_items 
        SET receive_qty = receive_qty - ? 
        WHERE receive_id = ?
    ");

    $success_count = 0;
    $error_messages = [];
    $total_amount = 0;
    
    foreach ($products as $product) {
        error_log("Raw product data: " . json_encode($product));
        
        $product_id = isset($product['product_id']) ? (int)$product['product_id'] : 0;
        $receive_id = isset($product['receive_id']) ? (int)$product['receive_id'] : 0;
        $issue_qty = isset($product['issue_qty']) ? (float)$product['issue_qty'] : 0.0;
        $sale_price = isset($product['sale_price']) && $product['sale_price'] !== '' ? (float)$product['sale_price'] : 0.0;
        $product_name = $product['name'] ?? 'ไม่ทราบชื่อ';
        $item_remark = "ยิงสินค้าจากแท็ค: {$issue_tag}";
        $receive_batches = isset($product['receive_batches']) && is_array($product['receive_batches']) ? $product['receive_batches'] : [];

        error_log("Processing product: ID=$product_id, Name=$product_name, Qty=$issue_qty, Price=$sale_price, Receive_ID=$receive_id");

        if (!$product_id || $issue_qty <= 0) {
            $error_messages[] = "ข้อมูลสินค้า {$product_name} ไม่ครบถ้วน (product_id=$product_id, qty=$issue_qty)";
            error_log("Skipping product due to invalid data");
            continue;
        }

        if (!empty($receive_batches)) {
            $normalized_batches = [];
            foreach ($receive_batches as $batch) {
                $batchReceiveId = isset($batch['receive_id']) ? (int)$batch['receive_id'] : 0;
                if ($batchReceiveId <= 0) {
                    continue;
                }
                $createdAt = $batch['created_at'] ?? null;
                $available_hint = isset($batch['available_qty']) ? (float)$batch['available_qty'] : (isset($batch['receive_qty']) ? (float)$batch['receive_qty'] : 0.0);
                if (!isset($normalized_batches[$batchReceiveId])) {
                    $normalized_batches[$batchReceiveId] = [
                        'receive_id' => $batchReceiveId,
                        'created_at' => $createdAt,
                        'hint_qty' => $available_hint
                    ];
                } else {
                    if ($available_hint > $normalized_batches[$batchReceiveId]['hint_qty']) {
                        $normalized_batches[$batchReceiveId]['hint_qty'] = $available_hint;
                    }
                    if ($createdAt !== null) {
                        $currentCreated = $normalized_batches[$batchReceiveId]['created_at'];
                        if ($currentCreated === null || strtotime($createdAt) < strtotime($currentCreated)) {
                            $normalized_batches[$batchReceiveId]['created_at'] = $createdAt;
                        }
                    }
                }
            }

            if (!empty($normalized_batches)) {
                $normalized_batches = array_values($normalized_batches);
                usort($normalized_batches, function ($a, $b) {
                    $timeA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
                    $timeB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
                    if ($timeA === $timeB) {
                        return ($a['receive_id'] ?? 0) <=> ($b['receive_id'] ?? 0);
                    }
                    return $timeA <=> $timeB;
                });
            }

            $batchHints = [];
            foreach ($normalized_batches as $batchEntry) {
                $batchHints[$batchEntry['receive_id']] = max(0.0, isset($batchEntry['hint_qty']) ? (float) $batchEntry['hint_qty'] : 0.0);
            }

            $batchDetails = [];
            $totalAvailable = 0.0;

            foreach ($normalized_batches as $batch) {
                $batchReceiveId = $batch['receive_id'];
                if (!isset($batchDetails[$batchReceiveId])) {
                    $stockCheckStmt->execute([$product_id, $batchReceiveId]);
                    $available = $stockCheckStmt->fetch(PDO::FETCH_ASSOC);
                    $stockCheckStmt->closeCursor();
                    if (!$available) {
                        continue;
                    }
                    $availableQty = isset($available['receive_qty']) ? (float) $available['receive_qty'] : 0.0;
                    $hintQty = $batchHints[$batchReceiveId] ?? null;
                    if ($hintQty !== null) {
                        $availableQty = min($availableQty, max(0.0, $hintQty));
                    }
                    if ($availableQty <= 0) {
                        $batchDetails[$batchReceiveId] = [
                            'receive_qty' => 0.0,
                            'name' => $available['name'] ?? $product_name,
                            'sku' => $available['sku'] ?? '',
                            'cost_price' => isset($available['cost_price']) ? (float) $available['cost_price'] : 0.0
                        ];
                    } else {
                        $batchDetails[$batchReceiveId] = [
                            'receive_qty' => $availableQty,
                            'name' => $available['name'] ?? $product_name,
                            'sku' => $available['sku'] ?? '',
                            'cost_price' => isset($available['cost_price']) ? (float) $available['cost_price'] : 0.0
                        ];
                    }
                }
                $totalAvailable += max(0.0, $batchDetails[$batchReceiveId]['receive_qty']);
            }

            if ($totalAvailable < $issue_qty) {
                $formattedTotal = number_format($totalAvailable, 2);
                $formattedNeed = number_format($issue_qty, 2);
                $error_messages[] = "สินค้า {$product_name} มีสต็อกไม่เพียงพอ (รวมคงเหลือ: {$formattedTotal}, ต้องการ: {$formattedNeed})";
                continue;
            }

            $operations = [];
            $remainingQty = $issue_qty;

            foreach ($normalized_batches as $batch) {
                if ($remainingQty <= 0) {
                    break;
                }
                $batchReceiveId = $batch['receive_id'];
                if (!isset($batchDetails[$batchReceiveId])) {
                    continue;
                }
                $batchAvailable = max(0.0, $batchDetails[$batchReceiveId]['receive_qty']);
                if ($batchAvailable <= 0) {
                    continue;
                }
                $deductQty = min($batchAvailable, $remainingQty);
                if ($deductQty <= 0) {
                    continue;
                }
                $operations[] = [
                    'receive_id' => $batchReceiveId,
                    'quantity' => $deductQty,
                    'cost_price' => $batchDetails[$batchReceiveId]['cost_price'],
                    'name' => $batchDetails[$batchReceiveId]['name']
                ];
                $remainingQty -= $deductQty;
                $batchDetails[$batchReceiveId]['receive_qty'] -= $deductQty;
            }

            if ($remainingQty > 0) {
                $formattedNeed = number_format($remainingQty, 2);
                $error_messages[] = "สินค้า {$product_name} มีสต็อกไม่เพียงพอ (ขาดอีก {$formattedNeed})";
                continue;
            }

            $allocationFailed = false;
            foreach ($operations as $operation) {
                $insert_result = $insertIssueStmt->execute([
                    $product_id,
                    $operation['receive_id'],
                    $sale_order_id,
                    $operation['quantity'],
                    $sale_price,
                    $operation['cost_price'],
                    $user_id,
                    $item_remark,
                    isset($product['expiry_date']) && !empty($product['expiry_date']) ? $product['expiry_date'] : null
                ]);

                if (!$insert_result) {
                    $error_messages[] = "ไม่สามารถบันทึกการยิงสินค้า {$operation['name']} ได้";
                    $allocationFailed = true;
                    break;
                }

                $update_result = $updateReceiveStmt->execute([$operation['quantity'], $operation['receive_id']]);
                if (!$update_result) {
                    $error_messages[] = "ไม่สามารถอัปเดตสต็อกของสินค้า {$operation['name']} ได้";
                    $allocationFailed = true;
                    break;
                }

                $total_amount += ($sale_price * $operation['quantity']);
                $success_count++;
                error_log("Successfully issued: Product ID {$product_id}, Qty {$operation['quantity']}, Receive ID {$operation['receive_id']}, Sale Order ID {$sale_order_id}");
            }

            if ($allocationFailed) {
                continue;
            }

            continue;
        }

        if (!$receive_id) {
            $error_messages[] = "ข้อมูลสินค้า {$product_name} ไม่ครบถ้วน (ไม่มี receive_id)";
            continue;
        }

        $stockCheckStmt->execute([$product_id, $receive_id]);
        $available = $stockCheckStmt->fetch(PDO::FETCH_ASSOC);
        $stockCheckStmt->closeCursor();

        if (!$available) {
            $error_messages[] = "ไม่พบข้อมูลสต็อกของสินค้า {$product_name}";
            continue;
        }

        $available_qty = isset($available['receive_qty']) ? (float)$available['receive_qty'] : 0.0;

        if ($available_qty < $issue_qty) {
            $formattedAvailable = number_format($available_qty, 2);
            $formattedNeed = number_format($issue_qty, 2);
            $error_messages[] = "สินค้า {$available['name']} มีสต็อกไม่เพียงพอ (คงเหลือ: {$formattedAvailable}, ต้องการ: {$formattedNeed})";
            continue;
        }

        $cost_price = isset($available['cost_price']) && $available['cost_price'] !== '' ? (float)$available['cost_price'] : 0.0;

        $insert_result = $insertIssueStmt->execute([
            $product_id,
            $receive_id,
            $sale_order_id,
            $issue_qty,
            $sale_price,
            $cost_price,
            $user_id,
            $item_remark,
            isset($product['expiry_date']) && !empty($product['expiry_date']) ? $product['expiry_date'] : null
        ]);

        if (!$insert_result) {
            $error_messages[] = "ไม่สามารถบันทึกการยิงสินค้า {$available['name']} ได้";
            continue;
        }

        $update_result = $updateReceiveStmt->execute([$issue_qty, $receive_id]);

        if (!$update_result) {
            $error_messages[] = "ไม่สามารถอัปเดตสต็อกของสินค้า {$available['name']} ได้";
            continue;
        }

        $success_count++;
        $total_amount += ($sale_price * $issue_qty);
        error_log("Successfully issued: Product ID {$product_id}, Qty {$issue_qty}, Receive ID {$receive_id}, Sale Order ID {$sale_order_id}");
    }
    
    // อัพเดท total_amount และ total_items ใน sales_orders
    if ($success_count > 0) {
        $update_sale_order = $pdo->prepare("
            UPDATE sales_orders 
            SET total_amount = ?, total_items = ? 
            WHERE sale_order_id = ?
        ");
        $update_sale_order->execute([$total_amount, $success_count, $sale_order_id]);
    }
    
    // Check if any products were successfully processed
    if ($success_count === 0) {
        $pdo->rollBack();
        $message = 'ไม่สามารถยิงสินค้าออกได้: ' . implode(', ', $error_messages);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
    // If there were some errors but some successes, still commit but warn
    if (!empty($error_messages) && $success_count > 0) {
        $pdo->commit();
        $message = "ยิงสินค้าออกสำเร็จ {$success_count} รายการ แต่มีปัญหา: " . implode(', ', $error_messages);
        echo json_encode(['success' => true, 'message' => $message, 'warnings' => $error_messages]);
    } else {
        // All successful
        $pdo->commit();
        $message = "ยิงสินค้าออกสำเร็จทั้งหมด {$success_count} รายการ (แท็ค: {$issue_tag})";
        echo json_encode(['success' => true, 'message' => $message, 'count' => $success_count]);
    }
    
    error_log("Issue completed: {$success_count} successful, " . count($error_messages) . " errors");

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('PDO Error in issue_product_api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('General Error in issue_product_api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}

// Ensure output is flushed
if (ob_get_level()) {
    ob_end_flush();
} else {
    flush();
}
?>