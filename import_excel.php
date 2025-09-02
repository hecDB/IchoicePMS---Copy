<?php
include 'sidebar.php';
require 'vendor/autoload.php'; 
require 'db_connect.php';      

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = "";
$user_id = $_SESSION['user_id'] ?? 0;

if(isset($_POST['submit'])) {
    if(isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {

        $pdo->beginTransaction();
        try {
            // ====== สร้างเลข PO ======
            $date = date('Ymd');
            $last_po = $pdo->query("SELECT po_number FROM purchase_orders WHERE po_number LIKE 'PO{$date}%' ORDER BY po_number DESC LIMIT 1")->fetchColumn();
            $num = $last_po ? intval(substr($last_po, -3)) + 1 : 1;
            $po_number = 'PO'.$date.str_pad($num, 3, '0', STR_PAD_LEFT);

            // ====== โหลด Excel ======
            $file_tmp = $_FILES['excel_file']['tmp_name'];
            $spreadsheet = IOFactory::load($file_tmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // ====== คำนวณยอดรวม ======
            $total_amount = 0;
            for ($i=1; $i<count($rows); $i++) {
                $qty = floatval($rows[$i][8]);
                $price = floatval($rows[$i][9]);
                $total_amount += $qty * $price;
            }

            // ====== เพิ่ม PO ======
            $stmt = $pdo->prepare("INSERT INTO purchase_orders 
                (po_number, supplier_id, order_date, total_amount, ordered_by, status, remark) 
                VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$po_number, 1, date('Y-m-d'), $total_amount, $user_id, 'pending', 'imported from excel']);
            $po_id = $pdo->lastInsertId();

            // ====== เพิ่มสินค้า / PO Items / Receive Items ======
            for ($i=1; $i<count($rows); $i++) {
                $row = $rows[$i];

                $sku      = trim($row[0] ?? '');
                $barcode  = trim($row[1] ?? '');
                $name     = trim($row[2] ?? '');
                $image    = trim($row[3] ?? '');
                $unit     = trim($row[4] ?? '');
                $row_code = trim($row[5] ?? '');
                $bin      = trim($row[6] ?? '');
                $shelf    = trim($row[7] ?? '');
                $qty      = floatval($row[8] ?? 0);
                $price    = floatval($row[9] ?? 0);
                $expiry_date = !empty($row[10]) ? date('Y-m-d', strtotime($row[10])) : null;
                $remark_color = trim($row[11] ?? '');
                $remark_split = trim($row[12] ?? '');

                if(empty($sku) && empty($barcode)) continue;

                // ====== ตรวจสอบสินค้า ======
                $stmt = $pdo->prepare("SELECT product_id, remark_color FROM products WHERE sku=? OR barcode=?");
                $stmt->execute([$sku, $barcode]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $product_id = $product['product_id'];
                    // อัปเดตสีสินค้า ถ้า remark_color ใหม่ไม่ว่าง
                    if ($remark_color !== '') {
                        $stmt = $pdo->prepare("UPDATE products SET remark_color=? WHERE product_id=?");
                        $stmt->execute([$remark_color, $product_id]);
                    }
                } else {
                    // insert product ใหม่
                    $stmt = $pdo->prepare("INSERT INTO products (name, sku, barcode, unit, image, remark_color, created_at) 
                                           VALUES (?,?,?,?,?,?,NOW())");
                    $stmt->execute([$name, $sku, $barcode, $unit, 'images/'.$image, $remark_color]);
                    $product_id = $pdo->lastInsertId();
                }

                // ====== ตรวจสอบ/เพิ่ม location ======
                $stmt = $pdo->prepare("SELECT location_id FROM locations WHERE row_code=? AND bin=? AND shelf=?");
                $stmt->execute([$row_code, $bin, $shelf]);
                $location = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($location) {
                    $location_id = $location['location_id'];
                } else {
                    $desc = "แถว $row_code ล็อค $bin ชั้น $shelf";
                    $stmt = $pdo->prepare("INSERT INTO locations (row_code, bin, shelf, description) VALUES (?,?,?,?)");
                    $stmt->execute([$row_code, $bin, $shelf, $desc]);
                    $location_id = $pdo->lastInsertId();
                }

                // ====== เชื่อม product_location ======
                $stmt = $pdo->prepare("SELECT 1 FROM product_location WHERE product_id=? AND location_id=?");
                $stmt->execute([$product_id, $location_id]);
                if(!$stmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO product_location (product_id, location_id) VALUES (?, ?)");
                    $stmt->execute([$product_id, $location_id]);
                }

                // ====== เพิ่ม PO Item ======
                $stmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, price_per_unit, total) 
                                       VALUES (?,?,?,?,?)");
                $stmt->execute([$po_id, $product_id, $qty, $price, $qty*$price]);
                $item_id = $pdo->lastInsertId();

                // ====== เพิ่ม Receive Item ======
                $stmt = $pdo->prepare("INSERT INTO receive_items (receive_date, po_id, item_id, receive_qty, received_by, remark_sale, expiry_date) 
                                       VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([date('Y-m-d'), $po_id, $item_id, $qty, $user_id, $remark_split, $expiry_date]);
            }

            $pdo->commit();
            $message = "Import สำเร็จ! PO = $po_number";

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Import ล้มเหลว: ".$e->getMessage();
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
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            margin:0;
            padding:0;
            background:#f4f6f9;
            font-family:'Prompt', sans-serif;
        }
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
            <a href="download_template.php" class="btn btn-download">
                <span class="material-icons">download</span> ดาวน์โหลดฟอร์ม Excel
            </a>
        </div>
        <h2><span class="material-icons" style="vertical-align:middle;color:#0072ff;">upload_file</span> Import Excel</h2>

        <?php if($message): ?>
           <script>
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: <?= json_encode($message) ?>,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = "product_activity.php";
                });
            </script>

        <?php endif; ?>

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
