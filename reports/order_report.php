<?php
session_start();
require_once '../config/db_connect.php';
include '../templates/sidebar.php';

$maxRangeDays = 90;
$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-29 days'));
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : $defaultFrom;
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : $today;
$errors = [];

$fromObj = DateTime::createFromFormat('Y-m-d', $dateFrom);
if (!$fromObj || $fromObj->format('Y-m-d') !== $dateFrom) {
    $errors[] = 'รูปแบบวันที่เริ่มต้นไม่ถูกต้อง ระบบได้ปรับเป็นค่าเริ่มต้นให้แล้ว';
    $dateFrom = $defaultFrom;
    $fromObj = new DateTime($dateFrom);
}

$toObj = DateTime::createFromFormat('Y-m-d', $dateTo);
if (!$toObj || $toObj->format('Y-m-d') !== $dateTo) {
    $errors[] = 'รูปแบบวันที่สิ้นสุดไม่ถูกต้อง ระบบได้ปรับเป็นวันนี้ให้แล้ว';
    $dateTo = $today;
    $toObj = new DateTime($dateTo);
}

if ($fromObj > $toObj) {
    $errors[] = 'วันที่เริ่มต้นต้องไม่มากกว่าวันที่สิ้นสุด ระบบได้สลับช่วงเวลาให้แล้ว';
    $tmp = $fromObj;
    $fromObj = $toObj;
    $toObj = $tmp;
    $dateFrom = $fromObj->format('Y-m-d');
    $dateTo = $toObj->format('Y-m-d');
}

$rangeDays = (int)$fromObj->diff($toObj)->days;
if ($rangeDays > $maxRangeDays) {
    $errors[] = 'เลือกช่วงเวลาได้สูงสุด 90 วัน ระบบได้ปรับเป็นช่วง 90 วันล่าสุดให้แล้ว';
    $toObj = new DateTime($today);
    $fromObj = (clone $toObj)->sub(new DateInterval('P' . ($maxRangeDays - 1) . 'D'));
    $dateFrom = $fromObj->format('Y-m-d');
    $dateTo = $toObj->format('Y-m-d');
}

$platformData = [];
$summaryTotals = [
    'qty' => 0,
    'sales' => 0.0,
    'cost' => 0.0,
    'profit' => 0.0
];

try {
    $sql = "
        SELECT 
            COALESCE(NULLIF(TRIM(so.platform), ''),
                CASE 
                    WHEN so.issue_tag REGEXP '^[A-Za-z]{5,6}[0-9]{8}$' THEN 'Shopee'
                    WHEN so.issue_tag REGEXP '^[0-9]{11}$' THEN 'Lazada'
                    ELSE 'ทั่วไป'
                END
            ) AS platform_label,
            COALESCE(SUM(ii.issue_qty), 0) AS total_qty,
            COALESCE(SUM(ii.issue_qty * COALESCE(ii.sale_price, 0)), 0) AS total_sales,
            COALESCE(SUM(ii.issue_qty * COALESCE(ii.cost_price, 0)), 0) AS total_cost
        FROM sales_orders so
        LEFT JOIN issue_items ii ON ii.sale_order_id = so.sale_order_id
        WHERE DATE(so.sale_date) BETWEEN :date_from AND :date_to
        GROUP BY platform_label
        HAVING total_qty > 0 OR total_sales > 0
        ORDER BY total_sales DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':date_from' => $dateFrom,
        ':date_to' => $dateTo
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $sales = (float)$row['total_sales'];
        $cost = (float)$row['total_cost'];
        $qty = (int)$row['total_qty'];
        $profit = ($sales * 0.80) - $cost;

        $platformData[] = [
            'platform' => $row['platform_label'],
            'qty' => $qty,
            'sales' => $sales,
            'cost' => $cost,
            'profit' => $profit
        ];

        $summaryTotals['qty'] += $qty;
        $summaryTotals['sales'] += $sales;
        $summaryTotals['cost'] += $cost;
        $summaryTotals['profit'] += $profit;
    }
} catch (PDOException $e) {
    error_log('Order report query failed: ' . $e->getMessage());
    $errors[] = 'ไม่สามารถดึงข้อมูลรายงานได้ชั่วคราว กรุณาลองใหม่อีกครั้ง';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานคำสั่งซื้อ - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link rel="stylesheet" href="../assets/mainwrap-modern.css">
    <style>
        .mainwrap { padding: 32px 32px 48px; }
        @media (max-width: 768px) { .mainwrap { padding: 24px 18px 36px; } }
        .report-header { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .report-header .material-icons { font-size: 40px; color: #385dfa; }
        .report-header h1 { margin: 0; font-size: 28px; font-weight: 600; color: #1f2937; }
        .report-header p { margin: 4px 0 0; color: #64748b; font-size: 15px; }
        .filters-card { background: #ffffff; border-radius: 18px; border: 1px solid #e2e8f0; padding: 20px 24px; margin-bottom: 24px; display: flex; align-items: flex-end; gap: 18px; flex-wrap: wrap; }
        .filters-card .field { flex: 1 1 200px; }
        .filters-card label { display: block; font-weight: 600; color: #475569; font-size: 14px; margin-bottom: 6px; }
        .filters-card input[type="date"] { width: 100%; padding: 10px 12px; border: 1px solid #cbd5f5; border-radius: 10px; font-size: 14px; color: #1f2937; background: #f8fafc; }
        .filters-card small { display: block; margin-top: 6px; color: #94a3b8; font-size: 12px; }
        .filters-card button { background: linear-gradient(135deg, #385dfa 0%, #4f46e5 100%); color: #ffffff; border: none; border-radius: 12px; padding: 12px 22px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 8px 16px rgba(56,93,250,0.18); transition: transform 0.2s ease, box-shadow 0.2s ease; display: inline-flex; align-items: center; gap: 8px; }
        .filters-card button:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(56,93,250,0.24); }
        .alert { background: #fef3c7; border: 1px solid #fde68a; border-radius: 12px; padding: 14px 18px; color: #92400e; font-size: 14px; margin-bottom: 18px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .summary-card { background: #ffffff; border-radius: 18px; border: 1px solid #e2e8f0; padding: 18px 20px; }
        .summary-card h2 { margin: 0; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .summary-card strong { display: block; margin-top: 8px; font-size: 22px; font-weight: 700; color: #1f2937; }
        .summary-card span { display: block; margin-top: 4px; color: #94a3b8; font-size: 12px; }
        .table-card { background: #ffffff; border-radius: 18px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 12px 24px rgba(15,23,42,0.05); }
        .table-card header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); }
        .table-card header h2 { margin: 0; font-size: 18px; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 10px; }
        .table-card header span { color: #64748b; font-size: 13px; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table thead th { background: #f1f5f9; color: #0f172a; text-align: left; padding: 14px 18px; font-size: 13px; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
        .report-table tbody td { padding: 12px 18px; border-bottom: 1px solid #e2e8f0; color: #1f2937; font-size: 14px; }
        .report-table tbody tr:nth-child(odd) { background: #f8fafc; }
        .report-table tbody tr:hover { background: #eef2ff; }
        .report-table tfoot td { padding: 14px 18px; font-weight: 700; background: #e0e7ff; border-top: 2px solid #4338ca; }
        .report-table .numeric { text-align: right; font-variant-numeric: tabular-nums; }
        .empty-state { background: #f8fafc; border: 1px dashed #cbd5f5; border-radius: 16px; padding: 36px; text-align: center; color: #94a3b8; font-size: 15px; }
        .empty-state .material-icons { font-size: 48px; display: block; margin-bottom: 12px; color: #cbd5f5; }
        .report-notice { font-size: 13px; color: #94a3b8; padding: 16px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    </style>
</head>
<body>
    <div class="mainwrap">
        <section class="report-header">
            <span class="material-icons" aria-hidden="true">assignment</span>
            <div>
                <h1>รายงานคำสั่งซื้อ</h1>
                <p>สรุปยอดขาย ต้นทุน และกำไรตามแพลตฟอร์มในช่วงเวลาที่เลือก (สูงสุด 90 วัน)</p>
            </div>
        </section>

        <?php if (!empty($errors)): ?>
            <div class="alert" role="alert">
                <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
            </div>
        <?php endif; ?>

        <form class="filters-card" method="get">
            <div class="field">
                <label for="date_from">วันที่เริ่มต้น</label>
                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" max="<?= htmlspecialchars($dateTo) ?>" required>
                <small>เลือกย้อนหลังได้ไม่เกิน 90 วัน</small>
            </div>
            <div class="field">
                <label for="date_to">วันที่สิ้นสุด</label>
                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" max="<?= htmlspecialchars($today) ?>" required>
                <small>ระบบจะใช้งานข้อมูลตามวันที่ขาย</small>
            </div>
            <button type="submit">
                <span class="material-icons" aria-hidden="true">search</span>
                แสดงรายงาน
            </button>
        </form>

        <div class="summary-grid">
            <div class="summary-card">
                <h2>จำนวนแพลตฟอร์ม</h2>
                <strong><?= number_format(count($platformData)) ?></strong>
                <span>เรียงตามยอดขายสูงสุด</span>
            </div>
            <div class="summary-card">
                <h2>จำนวนสินค้า</h2>
                <strong><?= number_format($summaryTotals['qty']) ?></strong>
                <span>รวมสินค้าที่ถูกขายทั้งหมด</span>
            </div>
            <div class="summary-card">
                <h2>ยอดขายรวม</h2>
                <strong><?= number_format($summaryTotals['sales'], 2) ?> ฿</strong>
                <span>ก่อนหักค่าธรรมเนียม 20%</span>
            </div>
            <div class="summary-card">
                <h2>กำไรรวมโดยประมาณ</h2>
                <strong><?= number_format($summaryTotals['profit'], 2) ?> ฿</strong>
                <span>หักค่าธรรมเนียม 20% และต้นทุนแล้ว</span>
            </div>
        </div>

        <?php if (!empty($platformData)): ?>
            <article class="table-card">
                <header>
                    <h2><span class="material-icons" aria-hidden="true">leaderboard</span>สรุปคำสั่งซื้อแยกแพลตฟอร์ม</h2>
                    <span>กำไร = (ยอดขาย - 20%) - ต้นทุน</span>
                </header>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>แพลตฟอร์ม</th>
                            <th class="numeric">จำนวนสินค้า</th>
                            <th class="numeric">ยอดขาย (บาท)</th>
                            <th class="numeric">ต้นทุน (บาท)</th>
                            <th class="numeric">กำไรโดยประมาณ (บาท)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($platformData as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['platform']) ?></td>
                                <td class="numeric"><?= number_format($row['qty']) ?></td>
                                <td class="numeric"><?= number_format($row['sales'], 2) ?></td>
                                <td class="numeric"><?= number_format($row['cost'], 2) ?></td>
                                <td class="numeric"><?= number_format($row['profit'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>รวมทุกแพลตฟอร์ม</td>
                            <td class="numeric"><?= number_format($summaryTotals['qty']) ?></td>
                            <td class="numeric"><?= number_format($summaryTotals['sales'], 2) ?></td>
                            <td class="numeric"><?= number_format($summaryTotals['cost'], 2) ?></td>
                            <td class="numeric"><?= number_format($summaryTotals['profit'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="report-notice">
                    กำไรโดยประมาณใช้อัตราค่าธรรมเนียม 20% หากอัตราจริงแตกต่าง สามารถปรับสูตรได้ในภายหลัง
                </div>
            </article>
        <?php else: ?>
            <div class="empty-state">
                <span class="material-icons" aria-hidden="true">hourglass_empty</span>
                ยังไม่พบข้อมูลการขายสำหรับช่วงเวลาที่เลือก
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
