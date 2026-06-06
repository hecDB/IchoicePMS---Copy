<?php
session_start();
require '../config/db_connect.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/combined_login_register.php');
    exit;
}

// ----- วันที่ default: today -----
$today = date('Y-m-d');
$default_start = $today;
$default_end   = $today;

$start_date = $_GET['start_date'] ?? $default_start;
$end_date   = $_GET['end_date']   ?? $default_end;

// Sanitize dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) $start_date = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date))   $end_date   = $today;
if ($start_date > $end_date) $end_date = $start_date;

$action = $_GET['action'] ?? '';

// ----- SQL หลัก -----
// จำนวนคงเหลือทั้งหมด ณ วันที่ end_date = รับเข้าสะสม - จ่ายออกสะสม
// ผู้บันทึก = คนล่าสุดที่รับสินค้าเข้าสำหรับสินค้านั้น ภายในช่วงวันที่เลือก
$sql = "
    SELECT
        p.product_id,
        p.sku,
        p.name,
        COALESCE(
            (SELECT SUM(ri2.receive_qty)
             FROM receive_items ri2
             JOIN purchase_order_items poi2 ON ri2.item_id = poi2.item_id
             WHERE poi2.product_id = p.product_id
               AND DATE(ri2.created_at) <= :end_date
            ), 0
        ) -
        COALESCE(
            (SELECT SUM(ii.issue_qty)
             FROM issue_items ii
             WHERE ii.product_id = p.product_id
               AND DATE(ii.created_at) <= :end_date2
            ), 0
        ) +
        COALESCE(
            (SELECT SUM(sa.adjust_qty)
             FROM stock_adjustments sa
             WHERE sa.product_id = p.product_id
               AND DATE(sa.adjusted_at) <= :end_date3
            ), 0
        ) AS balance_qty,
        (SELECT u2.name
         FROM receive_items ri3
         JOIN purchase_order_items poi3 ON ri3.item_id = poi3.item_id
         JOIN users u2 ON ri3.created_by = u2.user_id
         WHERE poi3.product_id = p.product_id
           AND DATE(ri3.created_at) BETWEEN :start_date AND :end_date4
         ORDER BY ri3.created_at DESC
         LIMIT 1
        ) AS recorder
    FROM products p
    WHERE EXISTS (
        SELECT 1
        FROM receive_items ri4
        JOIN purchase_order_items poi4 ON ri4.item_id = poi4.item_id
        WHERE poi4.product_id = p.product_id
          AND DATE(ri4.created_at) BETWEEN :start_date2 AND :end_date5
    )
    ORDER BY p.name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':end_date'    => $end_date,
    ':end_date2'   => $end_date,
    ':end_date3'   => $end_date,
    ':start_date'  => $start_date,
    ':end_date4'   => $end_date,
    ':start_date2' => $start_date,
    ':end_date5'   => $end_date,
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Export CSV =====
if ($action === 'export_csv') {
    $filename = 'stock_report_' . $start_date . '_to_' . $end_date . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM สำหรับ Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ลำดับ', 'SKU', 'ชื่อสินค้า', 'จำนวนคงเหลือ ณ ' . $end_date, 'ผู้บันทึก']);
    $i = 1;
    foreach ($rows as $row) {
        fputcsv($out, [
            $i++,
            $row['sku'],
            $row['name'],
            max(0, (float)$row['balance_qty']),
            $row['recorder'] ?? '-',
        ]);
    }
    fclose($out);
    exit;
}

// ===== Export Excel =====
if ($action === 'export_excel') {

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Stock Report');

    // Title row
    $sheet->mergeCells('A1:E1');
    $sheet->setCellValue('A1', 'รายงานสต็อกเข้า ช่วงวันที่ ' . $start_date . ' ถึง ' . $end_date);
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '10b981']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(24);

    // Header row
    $headers = ['ลำดับ', 'SKU', 'ชื่อสินค้า', 'จำนวนคงเหลือ ณ ' . $end_date, 'ผู้บันทึก'];
    $colLetter = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($colLetter . '2', $h);
        $colLetter++;
    }
    $sheet->getStyle('A2:E2')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1f2937']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
    ]);

    // Data rows
    $rowNum = 3;
    $i = 1;
    foreach ($rows as $row) {
        $sheet->setCellValue('A' . $rowNum, $i++);
        $sheet->setCellValue('B' . $rowNum, $row['sku']);
        $sheet->setCellValue('C' . $rowNum, $row['name']);
        $sheet->setCellValue('D' . $rowNum, max(0, (float)$row['balance_qty']));
        $sheet->setCellValue('E' . $rowNum, $row['recorder'] ?? '-');

        // Alternate row color
        $fillColor = ($rowNum % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
        $sheet->getStyle('A' . $rowNum . ':E' . $rowNum)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $rowNum++;
    }

    // Column widths
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(18);
    $sheet->getColumnDimension('C')->setWidth(35);
    $sheet->getColumnDimension('D')->setWidth(24);
    $sheet->getColumnDimension('E')->setWidth(20);

    $filename = 'stock_report_' . $start_date . '_to_' . $end_date . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ===== หน้า UI =====
include '../templates/sidebar.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสต็อกเข้า - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link href="../assets/mainwrap-modern.css" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f8fafc; }

        .filter-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .shortcut-btn {
            border: 2px solid #d1d5db;
            background: #fff;
            color: #374151;
            border-radius: 8px;
            padding: 0.4rem 1rem;
            font-family: 'Prompt', sans-serif;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
        }
        .shortcut-btn:hover, .shortcut-btn.active {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
        }
        .export-btn-excel {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-family: 'Prompt', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .export-btn-excel:hover { opacity: 0.88; color: #fff; }

        .export-btn-csv {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-family: 'Prompt', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .export-btn-csv:hover { opacity: 0.88; color: #fff; }

        .table-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .table-header-bar {
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .table-header-bar h5 {
            color: #fff;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .table-header-bar .count-badge {
            background: #10b981;
            color: #fff;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .balance-cell {
            font-weight: 700;
            color: #10b981;
        }
        .balance-zero {
            color: #9ca3af;
        }
        .recorder-pill {
            background: #f3f4f6;
            color: #374151;
            border-radius: 20px;
            padding: 0.2rem 0.7rem;
            font-size: 0.78rem;
            font-weight: 500;
        }
        .date-range-label {
            font-size: 0.78rem;
            color: #6b7280;
            font-weight: 500;
        }
        .period-display {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 0.4rem 1rem;
            font-size: 0.85rem;
            color: #166534;
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="mainwrap">
    <div class="container-fluid py-4">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #10b981;">assignment_returned</span>
                    รายงานสต็อกเข้า
                </h1>
                <p class="text-muted mb-0">ดาวน์โหลดข้อมูลสต็อกเข้าพร้อมยอดคงเหลือ เพื่อนำเข้าระบบอื่น</p>
            </div>
            <a href="product_management.php" class="btn-modern btn-modern-secondary btn-sm">
                <span class="material-icons" style="font-size: 1.1rem;">arrow_back</span>
                กลับจัดการสินค้า
            </a>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <form method="GET" id="filterForm">
                <div class="row g-3 align-items-end">
                    <div class="col-auto">
                        <label class="date-range-label mb-1 d-block">เมนูลัด</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="shortcut-btn <?= ($start_date === $today && $end_date === $today) ? 'active' : '' ?>"
                                    onclick="setShortcut('today')">
                                <span class="material-icons align-middle" style="font-size: 1rem;">today</span>
                                1 วัน
                            </button>
                            <button type="button" class="shortcut-btn <?= ($end_date === $today && $start_date === date('Y-m-d', strtotime('-6 days'))) ? 'active' : '' ?>"
                                    onclick="setShortcut('week')">
                                <span class="material-icons align-middle" style="font-size: 1rem;">date_range</span>
                                1 สัปดาห์
                            </button>
                        </div>
                    </div>
                    <div class="col-auto">
                        <label class="date-range-label mb-1 d-block" for="start_date">ตั้งแต่วันที่</label>
                        <input type="date" id="start_date" name="start_date"
                               class="form-control" style="min-width:160px;"
                               value="<?= htmlspecialchars($start_date) ?>"
                               max="<?= $today ?>">
                    </div>
                    <div class="col-auto">
                        <label class="date-range-label mb-1 d-block" for="end_date">ถึงวันที่</label>
                        <input type="date" id="end_date" name="end_date"
                               class="form-control" style="min-width:160px;"
                               value="<?= htmlspecialchars($end_date) ?>"
                               max="<?= $today ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-dark px-4">
                            <span class="material-icons align-middle me-1" style="font-size:1rem;">search</span>
                            ดูรายงาน
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Export Actions -->
        <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
            <div class="period-display">
                <span class="material-icons align-middle me-1" style="font-size:1rem;">calendar_month</span>
                ช่วงวันที่: <?= htmlspecialchars($start_date) ?> ถึง <?= htmlspecialchars($end_date) ?>
                &nbsp;·&nbsp; <?= count($rows) ?> สินค้า
            </div>
            <a href="?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&action=export_excel"
               class="export-btn-excel">
                <span class="material-icons" style="font-size:1.1rem;">table_view</span>
                Export Excel (.xlsx)
            </a>
            <a href="?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&action=export_csv"
               class="export-btn-csv">
                <span class="material-icons" style="font-size:1.1rem;">download</span>
                Export CSV
            </a>
        </div>

        <!-- Preview Table -->
        <div class="table-card">
            <div class="table-header-bar">
                <h5>
                    <span class="material-icons">preview</span>
                    ตัวอย่างข้อมูล
                    <span class="count-badge"><?= count($rows) ?> รายการ</span>
                </h5>
            </div>
            <div class="p-0">
                <div class="table-responsive">
                    <table id="stock-report-table" class="table table-hover table-striped mb-0" style="font-size:0.9rem;">
                        <thead style="background:#f9fafb;">
                            <tr>
                                <th class="px-4 py-3" style="width:60px;">ลำดับ</th>
                                <th class="px-3 py-3">SKU</th>
                                <th class="px-3 py-3">ชื่อสินค้า</th>
                                <th class="px-3 py-3 text-center">จำนวนคงเหลือ ณ <?= htmlspecialchars($end_date) ?></th>
                                <th class="px-3 py-3 text-center">ผู้บันทึก</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <span class="material-icons d-block mb-2" style="font-size:3rem;color:#d1d5db;">inbox</span>
                                    <p class="text-muted mb-0">ไม่พบรายการสต็อกเข้าในช่วงวันที่เลือก</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php $i = 1; foreach ($rows as $row): ?>
                            <?php $bal = max(0, (float)$row['balance_qty']); ?>
                            <tr>
                                <td class="px-4 text-center text-muted"><?= $i++ ?></td>
                                <td class="px-3"><span class="fw-bold text-secondary"><?= htmlspecialchars($row['sku'] ?? '-') ?></span></td>
                                <td class="px-3 fw-medium"><?= htmlspecialchars($row['name']) ?></td>
                                <td class="px-3 text-center">
                                    <span class="<?= $bal > 0 ? 'balance-cell' : 'balance-zero' ?> fs-6">
                                        <?= number_format($bal) ?>
                                    </span>
                                </td>
                                <td class="px-3 text-center">
                                    <span class="recorder-pill"><?= htmlspecialchars($row['recorder'] ?? '-') ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
    const TODAY = '<?= $today ?>';

    function setShortcut(type) {
        const startInput = document.getElementById('start_date');
        const endInput   = document.getElementById('end_date');
        endInput.value = TODAY;
        if (type === 'today') {
            startInput.value = TODAY;
        } else if (type === 'week') {
            const d = new Date();
            d.setDate(d.getDate() - 6);
            startInput.value = d.toISOString().split('T')[0];
        }
        document.querySelectorAll('.shortcut-btn').forEach(b => b.classList.remove('active'));
        event.currentTarget.classList.add('active');
        document.getElementById('filterForm').submit();
    }

    // Remove active from shortcut buttons when date inputs are manually changed
    document.getElementById('start_date').addEventListener('change', () => {
        document.querySelectorAll('.shortcut-btn').forEach(b => b.classList.remove('active'));
    });
    document.getElementById('end_date').addEventListener('change', () => {
        document.querySelectorAll('.shortcut-btn').forEach(b => b.classList.remove('active'));
    });

    $(document).ready(function () {
        if ($('#stock-report-table tbody tr td').length > 1) {
            $('#stock-report-table').DataTable({
                pageLength: 50,
                language: {
                    search: 'ค้นหา:',
                    lengthMenu: 'แสดง _MENU_ รายการ',
                    info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
                    paginate: { first: 'แรก', last: 'สุดท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' },
                    zeroRecords: 'ไม่พบข้อมูล',
                    infoEmpty: 'ไม่มีข้อมูล',
                },
                order: [[2, 'asc']],
            });
        }
    });
</script>
</body>
</html>
