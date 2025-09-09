<?php
require 'db_connect.php';

// Query สินค้าทั้งหมด พร้อมจำนวนคงเหลือและวันหมดอายุล่าสุด
$sql = "
SELECT 
	p.product_id,
	p.name,
	p.sku,
	p.barcode,
	p.unit,
	p.image,
	MAX(ri.expiry_date) AS expiry_date,
	SUM(ri.receive_qty) AS total_qty
FROM products p
LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
GROUP BY p.product_id, p.name, p.sku, p.barcode, p.unit, p.image  
HAVING total_qty <= 1
ORDER BY total_qty DESC
";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
	<meta charset="UTF-8">
	<title>รายการสินค้าทั้งหมด</title>
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
	<link rel="stylesheet" href="assets/style.css">
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<style>
		.product-table th, .product-table td { font-size: 14px; vertical-align: middle; text-align: center; }
		.product-table td img { max-width: 48px; max-height: 48px; width: 36px; height: 36px; object-fit: contain; border-radius: 6px; background: #f3f3f3; }
		.mainwrap { max-width: 1100px; margin: 30px auto; background: #fff; border-radius: 14px; box-shadow: 0 2px 8px #0001; padding: 24px; display: flex; flex-direction: column; align-items: center; }
		.page-title { font-size: 1.3rem; font-weight: 600; color: #375dfa; margin-bottom: 18px; text-align: center; width: 100%; }
		.table-responsive { width: 100%; display: flex; justify-content: center; }
		#product-table { margin: 0 auto; }
	</style>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="mainwrap">
	<div class="page-title"><span class="material-icons" style="vertical-align:middle;">inventory_2</span> รายการสินค้าทั้งหมด</div>
	<div class="table-responsive">
		<table id="product-table" class="display product-table" style="width:100%">
			<thead>
				<tr>
					<th>ภาพ</th>
					<th>ชื่อสินค้า</th>
					<th>SKU</th>
					<th>Barcode</th>
					<th>จำนวน</th>
					<th>หน่วย</th>
					<th>วันหมดอายุ</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($products as $row): ?>
				<tr>
					<td><img src="<?= htmlspecialchars($row['image']) ?>" alt="img" onerror="this.src='images/noimg.png'" /></td>
					<td><?= htmlspecialchars($row['name']) ?></td>
					<td><?= htmlspecialchars($row['sku']) ?></td>
					<td><?= htmlspecialchars($row['barcode']) ?></td>
					<td><?= (int)$row['total_qty'] ?></td>
					<td><?= htmlspecialchars($row['unit']) ?></td>
					<td><?= $row['expiry_date'] ? htmlspecialchars($row['expiry_date']) : '-' ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<script>
$(function(){
	$('#product-table').DataTable({
		"order": [[4, "desc"]],
		"language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" }
	});
});
</script>
</body>
</html>
