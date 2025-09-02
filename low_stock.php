<?php
session_start();
include 'db_connect.php';
// SQL ดึงสินค้าที่หมดแล้ว (รวมจำนวน < 1)
$sql = "
SELECT 
    p.product_id,
    p.name,
    p.sku,
    p.barcode,
    p.unit,
    p.image,
    p.remark_color,
    SUM(ri.receive_qty) AS total_qty,
    ri.remark_sale,
    ri.expiry_date
FROM products p
LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
RIGHT JOIN receive_items ri ON ri.item_id = poi.item_id
GROUP BY p.product_id, p.name, p.sku, p.barcode, p.unit, p.image, p.remark_color, ri.remark_sale, ri.expiry_date
HAVING total_qty <= 1
ORDER BY total_qty ASC, p.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<!-- Bootstrap 5 CSS (สำคัญมากสำหรับให้ modal สวยงาม!) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<!-- ... style tag ... -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap Bundle JS (รวม modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<style>

    /* .mainwrap { 
        max-width:1100px; 
        margin:30px auto; 
        padding:20px; 
        background:#fff; 
        border-radius:16px; 
        box-shadow:0 6px 18px rgba(0,0,0,0.08);
    } */
    h2 { 
        text-align:center; 
        margin-bottom:20px; 
        color:#375dfa; 
        font-weight:600;
    }
    .search-bar { 
        margin-bottom:20px; 
        display:flex; 
        gap:8px; 
    }
    .search-bar input { 
        flex:1; 
        padding:10px 14px; 
        border-radius:12px; 
        border:1px solid #d0d7de; 
        font-size:15px;
        transition:0.3s;
    }
    .search-bar input:focus {
        border-color:#375dfa;
        box-shadow:0 0 6px rgba(55,93,250,0.2);
        outline:none;
    }

    /* พื้นที่ตารางเป็นการ์ด */
    .table-card {
        background:#fff;
        border-radius:12px;
        box-shadow:0 4px 12px rgba(0,0,0,0.08);
        overflow:hidden;
        margin-top:15px;
    }

    /* ตาราง */
    table { 
        width:100%; 
        border-collapse:collapse; 
        font-size:15px;
    }
    th { 
        background:#f1f4ff; 
        color:#375dfa; 
        font-weight:600; 
        text-align:left; 
        padding:12px 14px; 
        border-bottom:2px solid #e0e6f0;
    }
    td { 
        padding:12px 14px; 
        border-bottom:1px solid #eee; 
    }
    tr:hover td { 
        background:#f9fbff; 
    }
    td.center { text-align:center; }


</style>

<body>

<?php  include 'sidebar.php';?>

<div class="mainwrap">
    <div class="topbar">
        แดชบอร์ด (Dashboard)
    </div>

    <div id="result-table">
        <div class="table-card">

            <table>
                <thead>
                    <tr>
                        <th>รูป</th>
                        <th>ชื่อสินค้า</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>จำนวนคงเหลือ</th>
                        <th>หน่วย</th>
                        <th>หมายเหตุ</th>
                        <th>วันหมดอายุ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($products as $p): ?>
                    <tr>
                        <td>
                            <?php if($p['image']): ?>
                                <img src="<?=htmlspecialchars($p['image'])?>" alt="" width="50">
                            <?php endif; ?>
                        </td>
                        <td><?=htmlspecialchars($p['name'])?></td>
                        <td><?=htmlspecialchars($p['sku'])?></td>
                        <td><?=htmlspecialchars($p['barcode'])?></td>
                        <td class="out-of-stock"><?=intval($p['total_qty'])?></td>
                        <td><?=htmlspecialchars($p['unit'])?></td>
                        <td><?=htmlspecialchars($p['remark_sale'])?></td>
                        <td><?=htmlspecialchars($p['expiry_date'])?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($products)): ?>
                    <tr><td colspan="8" style="text-align:center;">ไม่มีสินค้าหมดแล้ว</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

                 </div>
    </div>
</body>
</html>