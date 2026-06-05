<?php
session_start();
require '../config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/combined_login_register.php');
    exit;
}

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $sql = "
        SELECT 
            p.product_id,
            p.sku,
            p.barcode,
            p.name AS product_name,
            p.unit,
            COALESCE(SUM(ri.receive_qty), 0) AS current_stock
        FROM products p
        LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
        LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
        GROUP BY p.product_id, p.sku, p.barcode, p.name, p.unit
        ORDER BY p.name ASC
    ";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stock_export_' . date('Ymd_His') . '.csv"');
    // UTF-8 BOM for Excel
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['product_id', 'sku', 'barcode', 'product_name', 'unit', 'current_stock', 'adjust_qty', 'adjust_note']);
    foreach ($products as $row) {
        fputcsv($out, [
            $row['product_id'],
            $row['sku'],
            $row['barcode'],
            $row['product_name'],
            $row['unit'],
            $row['current_stock'],
            '',   // adjust_qty - ให้ผู้ใช้กรอก
            ''    // adjust_note - หมายเหตุ
        ]);
    }
    fclose($out);
    exit;
}

// Handle CSV Import
$import_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    $errors = [];
    $success_count = 0;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'ไม่สามารถอัปโหลดไฟล์ได้ (error code: ' . $file['error'] . ')';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv'])) {
            $errors[] = 'รองรับเฉพาะไฟล์ .csv เท่านั้น';
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                $errors[] = 'ไม่สามารถเปิดไฟล์ได้';
            } else {
                // Strip BOM if present
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                $header = fgetcsv($handle);
                if (!$header) {
                    $errors[] = 'ไฟล์ว่างหรือไม่มีหัวตาราง';
                } else {
                    // Normalize header
                    $header = array_map('trim', $header);

                    // Validate required columns
                    $required_cols = ['product_id', 'adjust_qty'];
                    foreach ($required_cols as $col) {
                        if (!in_array($col, $header)) {
                            $errors[] = "ไม่พบคอลัมน์ '$col' ในไฟล์";
                        }
                    }

                    if (empty($errors)) {
                        $col_idx = array_flip($header);
                        $row_num = 1;

                        try {
                            $pdo->beginTransaction();

                            while (($row = fgetcsv($handle)) !== false) {
                                $row_num++;
                                if (count($row) < 2) continue;

                                $product_id = (int) trim($row[$col_idx['product_id']] ?? 0);
                                $adjust_qty_raw = trim($row[$col_idx['adjust_qty']] ?? '');
                                $note = trim($row[$col_idx['adjust_note'] ?? -1] ?? 'นำเข้าจากไฟล์ CSV');

                                if ($product_id <= 0 || $adjust_qty_raw === '') continue;

                                $adjust_qty = (float) $adjust_qty_raw;

                                // Validate product exists
                                $chk = $pdo->prepare("SELECT product_id FROM products WHERE product_id = ?");
                                $chk->execute([$product_id]);
                                if (!$chk->fetch()) {
                                    $errors[] = "แถวที่ $row_num: ไม่พบสินค้า ID $product_id";
                                    continue;
                                }

                                if ($adjust_qty == 0) continue;

                                // Insert adjustment record into stock_adjustments table (or receive_items as manual adjustment)
                                // We log into a dedicated stock_adjustments table if it exists, otherwise create it
                                $pdo->exec("CREATE TABLE IF NOT EXISTS stock_adjustments (
                                    adjustment_id INT AUTO_INCREMENT PRIMARY KEY,
                                    product_id INT NOT NULL,
                                    adjust_qty DECIMAL(12,4) NOT NULL,
                                    adjust_note TEXT,
                                    adjusted_by INT,
                                    adjusted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
                                )");

                                $ins = $pdo->prepare("INSERT INTO stock_adjustments (product_id, adjust_qty, adjust_note, adjusted_by, adjusted_at) VALUES (?, ?, ?, ?, NOW())");
                                $ins->execute([$product_id, $adjust_qty, $note ?: 'นำเข้าจากไฟล์ CSV', $user_id]);
                                $success_count++;
                            }

                            $pdo->commit();
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $errors[] = 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage());
                        }
                    }
                }
                fclose($handle);
            }
        }
    }

    $import_result = [
        'success_count' => $success_count,
        'errors' => $errors
    ];
}

include '../templates/sidebar.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Import / Export สต็อก - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link href="../assets/mainwrap-modern.css" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f8fafc; }
        .upload-area {
            border: 2px dashed #6366f1;
            border-radius: 12px;
            padding: 2.5rem;
            text-align: center;
            background: #f5f3ff;
            cursor: pointer;
            transition: background 0.2s;
        }
        .upload-area:hover { background: #ede9fe; }
        .upload-area .material-icons { font-size: 3rem; color: #6366f1; }
        .card-section {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-title .material-icons { color: #6366f1; }
    </style>
</head>
<body>
<div class="mainwrap">
    <div class="container-fluid py-4" style="max-width: 900px;">

        <!-- Header -->
        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="material-icons" style="font-size: 2.2rem; color: #6366f1;">sync_alt</span>
            <div>
                <h1 class="h3 mb-0 fw-bold">Import / Export ปรับสต็อก</h1>
                <p class="text-muted mb-0">ดาวน์โหลดข้อมูลสต็อกหรืออัปโหลดไฟล์ปรับสต็อกแบบ Bulk</p>
            </div>
        </div>

        <?php if ($import_result): ?>
        <div class="alert <?= empty($import_result['errors']) ? 'alert-success' : 'alert-warning' ?> mb-4">
            <?php if ($import_result['success_count'] > 0): ?>
            <strong>บันทึกสำเร็จ <?= $import_result['success_count'] ?> รายการ</strong><br>
            <?php endif; ?>
            <?php foreach ($import_result['errors'] as $err): ?>
            <div>⚠ <?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Export Section -->
        <div class="card-section">
            <div class="section-title">
                <span class="material-icons">file_download</span>
                ดาวน์โหลดข้อมูลสต็อก (Export)
            </div>
            <p class="text-muted mb-3">ดาวน์โหลดไฟล์ CSV ที่มีข้อมูลสินค้าทั้งหมดพร้อมสต็อกปัจจุบัน เพื่อนำไปแก้ไขและอัปโหลดกลับ หรือนำเข้าระบบ Big Seller</p>
            <a href="?action=export" class="btn btn-success">
                <span class="material-icons align-middle me-1">download</span>
                ดาวน์โหลด stock_export.csv
            </a>
            <div class="mt-3">
                <small class="text-muted">
                    <strong>คอลัมน์ในไฟล์:</strong>
                    product_id, sku, barcode, product_name, unit, current_stock, adjust_qty (กรอกเพื่อปรับสต็อก), adjust_note
                </small>
            </div>
        </div>

        <!-- Import Section -->
        <div class="card-section">
            <div class="section-title">
                <span class="material-icons">file_upload</span>
                อัปโหลดไฟล์ปรับสต็อก (Import)
            </div>
            <p class="text-muted mb-3">
                อัปโหลดไฟล์ CSV ที่กรอก <strong>adjust_qty</strong> แล้ว (ตัวเลขบวก = เพิ่มสต็อก, ลบ = ลดสต็อก)
                ระบบจะบันทึกการปรับสต็อกทีละหลายรายการพร้อมกัน
            </p>

            <form method="POST" enctype="multipart/form-data">
                <div class="upload-area mb-3" onclick="document.getElementById('import_file').click()">
                    <span class="material-icons">cloud_upload</span>
                    <h5 class="mt-2 mb-1">คลิกเพื่อเลือกไฟล์ .CSV</h5>
                    <p class="text-muted mb-0" id="file-label">ยังไม่ได้เลือกไฟล์</p>
                    <input type="file" id="import_file" name="import_file" accept=".csv" style="display:none"
                           onchange="document.getElementById('file-label').textContent = this.files[0]?.name || 'ยังไม่ได้เลือกไฟล์'">
                </div>

                <div class="alert alert-info mb-3">
                    <strong>วิธีใช้:</strong><br>
                    1. ดาวน์โหลดไฟล์ CSV จากปุ่ม Export ด้านบน<br>
                    2. เปิดไฟล์ใน Excel แล้วกรอกตัวเลขในคอลัมน์ <code>adjust_qty</code> (บวก=เพิ่ม, ลบ=ลด)<br>
                    3. บันทึกเป็น CSV แล้วอัปโหลดไฟล์ที่นี่<br>
                    4. ระบบจะบันทึกการปรับสต็อกทั้งหมดในครั้งเดียว
                </div>

                <button type="submit" class="btn btn-primary">
                    <span class="material-icons align-middle me-1">upload</span>
                    อัปโหลดและปรับสต็อก
                </button>
            </form>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
