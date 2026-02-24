<?php
include '../templates/sidebar.php';
require '../vendor/autoload.php'; 
require '../config/db_connect.php';      


use PhpOffice\PhpSpreadsheet\IOFactory;

$message = "";
$user_id = $_SESSION['user_id'] ?? 0;

// ตรวจสอบ ZipArchive extension
if (!extension_loaded('zip')) {
    $message = "❌ ระบบต้องการ ZipArchive PHP Extension\n\n";
    $message .= "กรุณาติดต่อผู้ดูแลระบบเพื่อ enable extension นี้:\n";
    $message .= "1. เปิดไฟล์ C:\\xampp\\php\\php.ini\n";
    $message .= "2. ค้นหา ;extension=zip\n";
    $message .= "3. ลบ semicolon หน้า (;) ออก\n";
    $message .= "4. บันทึกไฟล์และ Restart Apache";
}

if(isset($_POST['submit'])) {
    if (!extension_loaded('zip')) {
        $message = "❌ ข้อผิดพลาด: ZipArchive extension ไม่ถูกติดตั้ง ระบบไม่สามารถอ่านไฟล์ Excel ได้";
    } else if(isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {

        $pdo->beginTransaction();
        try {
            // ตรวจสอบว่าตาราง purchase_order_items มีคอลัมน์ currency และ original_price หรือไม่
            $check_columns = $pdo->query("SHOW COLUMNS FROM purchase_order_items LIKE 'currency'");
            $has_currency_column = $check_columns->rowCount() > 0;
            
            $check_original_price = $pdo->query("SHOW COLUMNS FROM purchase_order_items LIKE 'original_price'");
            $has_original_price_column = $check_original_price->rowCount() > 0;
            // ====== สร้างเลข PO ======
            $last_po = $pdo->query("SELECT po_number FROM purchase_orders WHERE po_number LIKE 'IMEXCAL%' ORDER BY po_number DESC LIMIT 1")->fetchColumn();
            $num = $last_po ? intval(substr($last_po, 7)) + 1 : 1; // เริ่มจาก IMEXCAL (7 ตัวอักษร)
            $po_number = 'IMEXCAL'.str_pad($num, 5, '0', STR_PAD_LEFT);

            // ====== โหลด Excel ======
            $file_tmp = $_FILES['excel_file']['tmp_name'];
            $spreadsheet = IOFactory::load($file_tmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // ====== เพิ่ม PO ก่อน (จะอัพเดต total_amount หลังคำนวณได้) ======
            $stmt = $pdo->prepare("INSERT INTO purchase_orders 
                (po_number, supplier_id, order_date, total_amount, ordered_by, status, remark, created_at) 
                VALUES (?,?,?,?,?,?,?,NOW())");
            $stmt->execute([$po_number, 1, date('Y-m-d H:i:s'), 0, $user_id, 'completed', 'imported from excel']);
            $po_id = $pdo->lastInsertId();
            
            $items_imported = 0;  // นับจำนวนแถวที่นำเข้าสำเร็จ

            // ====== Loop เพิ่มสินค้า / PO Items / Receive Items ======
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                $sanitize = fn($str) => trim(preg_replace('/\s+/', ' ', str_replace("\xC2\xA0", ' ', $str ?? '')));

                $sku = substr(strtolower(trim($sanitize($row[0] ?? ''))), 0, 255);
                $barcode = substr(strtolower(trim($sanitize($row[1] ?? ''))), 0, 50);
                $name         = $sanitize($row[2]);
                $category_name = $sanitize($row[3]); // ประเภท (Column D)
                $image        = $sanitize($row[4]);
                $unit         = $sanitize($row[5]);
                $row_code     = $sanitize($row[6]);
                
                $bin          = $sanitize($row[7]);
                $shelf        = $sanitize($row[8]);
                $qty          = floatval($row[9] ?? 0);
                $price        = floatval($row[10] ?? 0);
                $sale_price   = floatval($row[11] ?? 0);
                $currency_raw = strtoupper(trim($sanitize($row[12] ?? 'THB')));
                
                // ตรวจสอบและทำความสะอาดสกุลเงิน
                $allowed_currencies = ['THB', 'USD'];
                $currency = in_array($currency_raw, $allowed_currencies) ? $currency_raw : 'THB';
                
                // บันทึก log หากมีการแก้ไขสกุลเงิน
                if ($currency !== $currency_raw && !empty($currency_raw)) {
                    error_log("Row " . ($i + 1) . ": Invalid currency '{$currency_raw}' changed to 'THB'");
                }
                $expiry_date  = !empty($row[13]) ? date('Y-m-d', strtotime($row[13])) : null;
                $remark_color = strtolower(trim($sanitize($row[14] ?? '')));
                $remark_split = intval($row[15] ?? 0);
                $remark       = $sanitize($row[16]);

                if($sku === '' && $barcode === '') continue;

                // ====== ดึง category_id จาก category_name ======
                $product_category_id = null;
                if (!empty($category_name)) {
                    $stmt = $pdo->prepare("SELECT category_id FROM product_category WHERE category_name = ?");
                    $stmt->execute([$category_name]);
                    $category = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($category) {
                        $product_category_id = $category['category_id'];
                    } else {
                        error_log("Row " . ($i + 1) . ": Category '{$category_name}' not found in database");
                        // ข้ามแถวนี้หรือสร้างประเภทใหม่ (ในที่นี้ข้ามแถว)
                        continue;
                    }
                }

                // ====== ตรวจสอบสินค้าเดิม ======
                $stmt = $pdo->prepare("
                    SELECT product_id FROM products
                    WHERE sku = ? AND barcode = ? AND remark_color = ? AND remark_split = ?
                ");
                $stmt->execute([$sku, $barcode, $remark_color, $remark_split]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    // ====== ตรวจสอบสินค้าเดิม (robust for NULL/empty) ======
                    $skuParam = $sku === '' ? '' : $sku;
                    $barcodeParam = $barcode === '' ? '' : $barcode;

                    $stmt = $pdo->prepare(
                        "SELECT product_id, sku, barcode, remark_color, remark_split FROM products WHERE " .
                        "(sku = ? OR (sku IS NULL AND ? = '')) " .
                        "AND (barcode = ? OR (barcode IS NULL AND ? = '')) " .
                        "AND remark_color = ? AND remark_split = ?"
                    );
                    $stmt->execute([$skuParam, $skuParam, $barcodeParam, $barcodeParam, $remark_color, $remark_split]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    // Debug: prepare info for web if not found
                    $debug_web = [];
                    if (!$product) {
                        $debug_web['select_param'] = [
                            'sku'=>$skuParam, 'barcode'=>$barcodeParam, 'remark_color'=>$remark_color, 'remark_split'=>$remark_split
                        ];
                        $stmt2 = $pdo->prepare("SELECT product_id, sku, barcode, remark_color, remark_split FROM products WHERE sku LIKE ? OR barcode LIKE ?");
                        $stmt2->execute(['%'.substr($skuParam,0,8).'%', '%'.substr($barcodeParam,0,5).'%']);
                        $all = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                        $debug_web['similar_products'] = $all;
                    }

                    if ($product) {
                        $product_id = $product['product_id'];
                        $pdo->prepare("UPDATE products SET is_active = 1 WHERE product_id = ?")
                            ->execute([$product_id]);
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO products (name, sku, barcode, unit, image, remark_color, remark_split, product_category_id, is_active, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
                            $image_path = empty($image) ? null : 'images/'.$image;
                            $stmt->execute([
                                $name,
                                $sku === '' ? null : $sku,
                                $barcode === '' ? null : $barcode,
                                $unit,
                                $image_path,
                                $remark_color,
                                $remark_split,
                                $product_category_id,
                                1,
                                $user_id
                            ]);
                            $product_id = $pdo->lastInsertId();
                        } catch (PDOException $pe) {
                            if ($pe->getCode() === '23000') {
                                // Duplicate entry: re-query to get product_id
                                $stmt = $pdo->prepare(
                                    "SELECT product_id FROM products WHERE (sku = ? OR (sku IS NULL AND ? = '')) " .
                                    "AND (barcode = ? OR (barcode IS NULL AND ? = '')) AND remark_color = ? AND remark_split = ?"
                                );
                                $stmt->execute([$skuParam, $skuParam, $barcodeParam, $barcodeParam, $remark_color, $remark_split]);
                                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($product) {
                                    $product_id = $product['product_id'];
                                    $pdo->prepare("UPDATE products SET is_active = 1 WHERE product_id = ?")
                                        ->execute([$product_id]);
                                } else {
                                    $debug = [
                                        'sku' => $sku,
                                        'barcode' => $barcode,
                                        'remark_color' => $remark_color,
                                        'remark_split' => $remark_split,
                                        'name' => $name,
                                        'select_param' => $debug_web['select_param'] ?? null,
                                        'similar_products' => $debug_web['similar_products'] ?? null
                                    ];
                                    throw new Exception('Duplicate entry but cannot find product.', 0, $pe);
                                }
                            } else {
                                throw $pe;
                            }
                        } catch (Exception $e) {
                            $debug = [
                                'sku' => $sku,
                                'barcode' => $barcode,
                                'remark_color' => $remark_color,
                                'remark_split' => $remark_split,
                                'name' => $name,
                                'select_param' => $debug_web['select_param'] ?? null,
                                'similar_products' => $debug_web['similar_products'] ?? null
                            ];
                            throw new Exception('General error in product insert.', 0, $e);
                        }
                    }

                // ====== ตรวจสอบ/เพิ่ม location ======
                $stmt = $pdo->prepare("SELECT location_id FROM locations WHERE row_code=? AND bin=? AND shelf=?");
                $stmt->execute([$row_code, $bin, $shelf]);
                $location = $stmt->fetch(PDO::FETCH_ASSOC);

                $location_id = $location ? $location['location_id'] : null;
                if(!$location_id){
                    $desc = "แถว $row_code ล็อค $bin ชั้น $shelf";
                    $stmt = $pdo->prepare("INSERT INTO locations (row_code, bin, shelf, description) VALUES (?,?,?,?)");
                    $stmt->execute([$row_code, $bin, $shelf, $desc]);
                    $location_id = $pdo->lastInsertId();
                }

                // ====== เชื่อม product_location ======
                $stmt = $pdo->prepare("SELECT 1 FROM product_location WHERE product_id=? AND location_id=?");
                $stmt->execute([$product_id, $location_id]);
                if(!$stmt->fetch()){
                    $stmt = $pdo->prepare("INSERT INTO product_location (product_id, location_id) VALUES (?, ?)");
                    $stmt->execute([$product_id, $location_id]);
                }

                // ====== แปลงราคาเป็น THB ก่อนบันทึก ======
                $price_thb = $price;
                $sale_price_thb = $sale_price;
                
                if($currency !== 'THB') {
                    // ดึงอัตราแลกเปลี่ยน
                    $rate_stmt = $pdo->prepare("SELECT exchange_rate FROM currencies WHERE code = ? AND is_active = 1");
                    $rate_stmt->execute([$currency]);
                    $rate = $rate_stmt->fetchColumn();
                    
                    if($rate) {
                        $price_thb = $price * $rate;
                        $sale_price_thb = $sale_price * $rate;
                    }
                }

                // ====== เพิ่ม PO Item ======
                if ($has_currency_column && $has_original_price_column) {
                    // ใหม่: มีคอลัมน์สกุลเงินแล้ว
                    $stmt = $pdo->prepare("INSERT INTO purchase_order_items 
                        (po_id, product_id, qty, price_per_unit, sale_price, total, currency, original_price) 
                        VALUES (?,?,?,?,?,?,?,?)");
                    $stmt->execute([$po_id, $product_id, $qty, $price_thb, $sale_price_thb, $qty*$price_thb, $currency, $price]);
                    $item_id = $pdo->lastInsertId();
                } else {
                    // เก่า: ไม่มีคอลัมน์สกุลเงิน - ใช้รูปแบบเดิม
                    $stmt = $pdo->prepare("INSERT INTO purchase_order_items 
                        (po_id, product_id, qty, price_per_unit, sale_price, total) 
                        VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$po_id, $product_id, $qty, $price_thb, $sale_price_thb, $qty*$price_thb]);
                    $item_id = $pdo->lastInsertId();
                    
                    // บันทึกข้อมูลสกุลเงินไว้ใน remark ของ PO ชั่วคราว
                    if ($currency !== 'THB') {
                        $currency_info = " (Original: {$price} {$currency})";
                        $stmt_update_po = $pdo->prepare("UPDATE purchase_orders SET remark = CONCAT(COALESCE(remark, ''), ?) WHERE po_id = ?");
                        $stmt_update_po->execute([$currency_info, $po_id]);
                    }
                }

                // ====== เพิ่ม Receive Item ======
                $stmt = $pdo->prepare("INSERT INTO receive_items 
                    (po_id, item_id, receive_qty, remark_color, remark_split, created_by, expiry_date, remark) 
                    VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$po_id, $item_id, $qty, $remark_color, $remark_split, $user_id, $expiry_date, $remark]);
                
                $items_imported++;  // นับแถวที่บันทึกสำเร็จ
            }

            // ====== คำนวณยอดรวมจากราคา THB และอัพเดต PO ======
            $stmt = $pdo->prepare("SELECT SUM(total) as total_amount FROM purchase_order_items WHERE po_id = ?");
            $stmt->execute([$po_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $final_total = floatval($result['total_amount'] ?? 0);
            
            $stmt = $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE po_id = ?");
            $stmt->execute([$final_total, $po_id]);

            $pdo->commit();
            
            $message = "Import สำเร็จ! PO = $po_number ({$items_imported} รายการ)";
            $message .= "<br><small style='color: #ff6b35; font-weight: 500;'>⚠️ ตารางยังไม่รองรับสกุลเงินเต็มรูปแบบ - กรุณารัน migration script</small>";
            $message .= "<br><small style='color: #666;'>หากมีสกุลเงินอื่น ระบบจะปรับเป็น THB อัตโนมัติ</small>";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_row = isset($i) ? " at row " . ($i + 1) : "";
            $debug = '';
            if ($e->getPrevious() && method_exists($e->getPrevious(), 'getMessage')) {
                $debug .= $e->getPrevious()->getMessage();
            }
            if (isset($debug_web) && !empty($debug_web)) {
                $debug .= '<br><b>DEBUG:</b> <pre>' . htmlspecialchars(json_encode($debug_web, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            }
            
            // เพิ่มข้อมูลเกี่ยวกับโครงสร้างตาราง
            $debug .= '<br><b>DATABASE SCHEMA:</b> <pre>';
            $debug .= 'Currency column exists: ' . ($has_currency_column ? 'YES' : 'NO') . "\n";
            $debug .= 'Original price column exists: ' . ($has_original_price_column ? 'YES' : 'NO') . "\n";
            if (isset($currency)) {
                $debug .= 'Attempted currency: ' . $currency . "\n";
            }
            $debug .= '</pre>';
            if (isset($debug)) {
                $debug .= '<br><b>EXCEPTION:</b> <pre>' . htmlspecialchars(json_encode($e, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            }
            $message = "Import ล้มเหลว{$error_row}: " . $e->getMessage() . $debug;
        }

    } else {
        $message = "กรุณาเลือกไฟล์ Excel ให้ถูกต้อง";
    }
}


?>


<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
  <meta charset="UTF-8">
  <title>IchoicePMS</title>
  <link rel="icon" href="images/favicon.png" type="image/png">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="../assets/base.css">
<link rel="stylesheet" href="../assets/sidebar.css">
<link rel="stylesheet" href="../assets/components.css">
    <style>
      
        .content-card {
            max-width:700px;
            margin:40px auto;
            padding:30px;
            background:#fff;
            border-radius:15px;
            box-shadow:0 6px 20px rgba(0,0,0,0.08);
        }
        h2 {
            text-align:center;
            color:#2c3e50;
            font-weight:600;
            margin-bottom:25px;
        }
        .btn {
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:12px 22px;
            border:none;
            border-radius:8px;
            font-size:15px;
            font-weight:600;
            cursor:pointer;
            transition:all 0.25s ease;
            text-decoration:none;
        }
        .btn-download {
            background:linear-gradient(45deg,#00c6ff,#0072ff);
            color:#fff;
        }
        .btn-download:hover { opacity:0.9; transform:translateY(-2px); }

        .btn-submit {
            background:linear-gradient(45deg,#42e695,#3bb2b8);
            color:#fff;
            width:100%;
            margin-top:15px;
        }
        .btn-submit:hover { opacity:0.9; transform:translateY(-2px); }

        .file-upload {
            margin:20px 0;
            text-align:center;
        }
        .file-upload input[type=file] {
            display:none;
        }
        .file-upload label {
            display:inline-block;
            padding:14px 20px;
            background:#f1f3f5;
            border:2px dashed #bbb;
            border-radius:8px;
            cursor:pointer;
            transition:all 0.25s ease;
            font-weight:500;
        }
        .file-upload label:hover {
            background:#e9ecef;
            border-color:#0072ff;
        }
        p.note {
            text-align:center;
            font-size:14px;
            color:#666;
            margin-top:8px;
        }
    </style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="mainwrap">
    <div class="topbar">นำเข้าข้อมูลจาก Excel</div>
    <div class="content-card">
        <div class="card-header">
            <a href="../templates/download_template.php" class="btn btn-download">
                <span class="material-icons">download</span> ดาวน์โหลดฟอร์ม Excel
            </a>
        </div>
        <h2><span class="material-icons" style="vertical-align:middle;color:#0072ff;">upload_file</span> Import Excel</h2>

        <?php if($message): ?>
           <script>
                const message = <?= json_encode($message) ?>;
                const isSuccess = message.includes("สำเร็จ");
                
                Swal.fire({
                    icon: isSuccess ? "success" : "error",
                    title: isSuccess ? "สำเร็จ" : "เกิดข้อผิดพลาด",
                    html: message.replace(/\n/g, '<br>'),
                    timer: isSuccess ? 3000 : 0,
                    showConfirmButton: !isSuccess
                }).then(() => {
                    if (isSuccess) {
                        window.location.href = "../receive/receive_items_view.php";
                    }
                });
            </script>

        <?php endif; ?>

        <!-- <div class="migration-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 20px 0; color: #856404;">
            <h4 style="margin: 0 0 10px 0; color: #d68910;">
                <span class="material-icons" style="vertical-align: middle; margin-right: 8px;">info</span>
                คำแนะนำสำหรับการรองรับสกุลเงิน
            </h4>
            <p style="margin: 0 0 10px 0; font-size: 14px;">
                เพื่อให้ระบบรองรับสกุลเงินได้เต็มรูปแบบ กรุณารัน SQL script ต่อไปนี้ก่อน:
            </p>
            <code style="background: #f8f9fa; padding: 8px 12px; border-radius: 4px; font-size: 12px; display: block; overflow-x: auto;">
                SOURCE db/add_currency_to_purchase_order_items.sql;
            </code>
            <p style="margin: 10px 0 0 0; font-size: 13px; color: #856404;">
                <strong>หมายเหตุ:</strong> หากยังไม่ได้รัน migration ระบบจะยังใช้งานได้แต่จะบันทึกข้อมูลสกุลเงินไว้ใน remark ของ PO
            </p>
        </div> -->

        <form method="post" enctype="multipart/form-data">
            <div class="file-upload">
                <input type="file" id="excel_file" name="excel_file" accept=".xlsx" required hidden>
                <label for="excel_file" class="file-label">
                    <span class="material-icons" style="vertical-align:middle;color:#0072ff;">attach_file</span>
                    เลือกไฟล์ Excel (.xlsx)
                </label>
                <p class="note">รองรับไฟล์ Excel เท่านั้น (.xlsx)</p>
                <p id="file-name" class="file-name"></p>
            </div>

            <script>
            document.getElementById('excel_file').addEventListener('change', function() {
                const fileName = this.files.length > 0 ? this.files[0].name : "ยังไม่ได้เลือกไฟล์";
                document.getElementById('file-name').textContent = "ไฟล์ที่เลือก: " + fileName;
            });
            </script>

            <button type="submit" name="submit" class="btn btn-submit">
                <span class="material-icons">cloud_upload</span> Import
            </button>
        </form>
    </div>
</div>
</body>
</html>
