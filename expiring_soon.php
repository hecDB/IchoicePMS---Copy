<?php
session_start();
include 'db_connect.php';
include 'sidebar.php';

// ====== สินค้าใกล้หมดอายุ (ใน 90 วัน) ======
$ninety_days_later = date('Y-m-d', strtotime('+90 days'));
$today = date('Y-m-d');

$sql_expiring_soon = "
    SELECT 
        p.product_id,
        p.name,
        p.sku,
        p.barcode,
        p.unit,
        p.image,
        ri.receive_qty AS stock_on_hand,
        ri.expiry_date,
        DATEDIFF(ri.expiry_date, CURDATE()) as days_to_expire
    FROM products p
    LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
    WHERE ri.expiry_date IS NOT NULL 
      AND ri.expiry_date BETWEEN ? AND ?
      AND ri.receive_qty > 0
    ORDER BY ri.expiry_date ASC, p.name ASC
";
$stmt = $pdo->prepare($sql_expiring_soon);
$stmt->execute([$today, $ninety_days_later]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สินค้าใกล้หมดอายุ</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/base.css">
    <link rel="stylesheet" href="assets/sidebar.css">
    <link rel="stylesheet" href="assets/components.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
</head>
<body>

<div class="mainwrap">
    <div class="topbar">
        สินค้าใกล้หมดอายุ (ภายใน 90 วัน)
    </div>
    <div class="main">
        <div class="card table-card mt-3">
            <div class="table-responsive">
                <table id="expiring-soon-table" class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th>รูปภาพ</th>
                            <th>ชื่อสินค้า</th>
                            <th>SKU</th>
                            <th>จำนวนคงคลัง</th>
                            <th>หน่วย</th>
                            <th>วันหมดอายุ</th>
                            <th>เหลืออีก (วัน)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td class="text-center">
                                    <img src="images/<?= htmlspecialchars($product['image'] ?: 'noimg.png') ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;">
                                </td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['sku']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($product['stock_on_hand']) ?></td>
                                <td><?= htmlspecialchars($product['unit']) ?></td>
                                <td class="text-danger">
                                    <?= date("d/m/Y", strtotime($product['expiry_date'])) ?>
                                </td>
                                <td class="text-center text-danger fw-bold">
                                    <?= $product['days_to_expire'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#expiring-soon-table').DataTable({
        "order": [[5, "asc"]], // Sort by expiry date
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json"
        },
        "pageLength": 25
    });
});
</script>

</body>
</html>
