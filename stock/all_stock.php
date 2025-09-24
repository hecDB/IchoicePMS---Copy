<?php
session_start();
include '../config/db_connect.php';
include '../templates/sidebar.php';

// ====== สินค้าคงคลังทั้งหมด ======
$sql_stock = "
    SELECT 
        p.product_id,
        p.name,
        p.sku,
        p.barcode,
        p.unit,
        p.image,
        SUM(ri.receive_qty) AS total_stock,
        GROUP_CONCAT(DISTINCT ri.expiry_date ORDER BY ri.expiry_date SEPARATOR ', ') as expiry_dates
    FROM products p
    LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
    WHERE ri.receive_qty > 0
    GROUP BY p.product_id, p.name, p.sku, p.barcode, p.unit, p.image
    HAVING total_stock > 0
    ORDER BY p.name
";
$stmt = $pdo->query($sql_stock);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สินค้าคงคลังทั้งหมด</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
</head>
<body>

<div class="mainwrap">
    <div class="topbar">
        สินค้าคงคลังทั้งหมด
    </div>
    <div class="main">
        <div class="card table-card mt-3">
            <div class="table-responsive">
                <table id="all-stock-table" class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th>รูปภาพ</th>
                            <th>ชื่อสินค้า</th>
                            <th>SKU</th>
                            <th>บาร์โค้ด</th>
                            <th>จำนวนคงคลัง</th>
                            <th>หน่วย</th>
                            <th>วันหมดอายุ</th>
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
                                <td><?= htmlspecialchars($product['barcode']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($product['total_stock']) ?></td>
                                <td><?= htmlspecialchars($product['unit']) ?></td>
                                <td><?= htmlspecialchars($product['expiry_dates'] ?? 'ไม่ได้กำหนดวันหมดอายุ') ?></td>

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
    $('#all-stock-table').DataTable({
        "order": [[1, "asc"]], // Sort by product name
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json"
        },
        "pageLength": 25
    });
});
</script>

</body>
</html>
