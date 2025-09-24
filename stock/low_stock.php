<?php
require '../config/db_connect.php';

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
ORDER BY total_qty ASC
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

<link rel="stylesheet" href="../assets/base.css">
<link rel="stylesheet" href="../assets/sidebar.css">
<link rel="stylesheet" href="../assets/components.css">

	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		/* page-scoped container: keep content centered but let central stylesheet control sidebar offset */
		.content-card { max-width: 1100px; margin: 0; background: transparent; border-radius: 8px; padding: 0; display: block; }
		.page-title { font-size: 1.25rem; font-weight: 600; color: #0856cd; margin-bottom: 0; }
		.table-responsive { width: 100%; }
		#product-table { margin: 0; width: 100% !important; }
		@media (max-width: 700px) {
			.product-name { max-width: 180px; }
			/* hide SKU and Barcode on very small screens */
			#product-table thead th:nth-child(3),
			#product-table thead th:nth-child(4) { display: none; }
			#product-table tbody td:nth-child(3),
			#product-table tbody td:nth-child(4) { display: none; }
		}
	</style>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<?php include '../templates/sidebar.php'; ?>
<div class="mainwrap">
	<div class="container-fluid py-3">
		<div class="d-flex align-items-center justify-content-between mb-3">
			<div class="page-bar shadow-sm d-flex align-items-center mb-2" style="background:#fff;border-radius:12px;padding:14px 22px 14px 18px;box-shadow:0 2px 8px 0 rgba(8,86,205,0.07);width:100%;min-width:0;">
				<i class="fa-solid fa-boxes-stacked me-2" style="font-size:1.4em;vertical-align:middle;color:#0856cd;"></i>
				<span class="page-title">สินค้าคงเหลือต่ำ (Low stock)</span>
			</div>
			<!-- back to dashboard button -->
			<!-- <a href="dashboard.php" class="btn btn-outline-secondary btn-sm ms-3" title="กลับไปหน้าแดชบอร์ด"><i class="fa fa-arrow-left me-1"></i>กลับไปหน้าแดชบอร์ด</a> -->
		</div>
		<!-- header kept in page-bar; table moved out of card to sit directly on the page -->
        <div class="card shadow-sm">
            <div class="card-body">
				<table id="product-table" class="display table-po table table-striped table-hover" style="width:100%">
			<thead>
				<tr>
					<th>รูป</th>
					<th>รายการสินค้า</th>
					<th>SKU</th>
					<th>Barcode</th>
					<th>จำนวนคงเหลือ</th>
					<th>หน่วย</th>
					<th>วันหมดอายุ</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($products as $row): ?>
				<tr>
					<td class="center"><img class="table-img" src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" onerror="this.src='images/noimg.png'" /></td>
					<td>
						<div class="product-name" title="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></div>
					</td>
					<td class="center"><?= htmlspecialchars($row['sku']) ?></td>
					<td class="center"><?= htmlspecialchars($row['barcode']) ?></td>
					<td class="center">
						<?php $qty = (int)$row['total_qty']; ?>
						<?php if($qty <= 0): ?>
							<span class="qty-badge qty-zero">0</span>
						<?php else: ?>
							<span class="qty-badge qty-low"><?= $qty ?></span>
						<?php endif; ?>
					</td>
					<td class="center"><?= htmlspecialchars($row['unit']) ?></td>
					<td class="center"><?php if($row['expiry_date']){ echo date('d/m/Y', strtotime($row['expiry_date'])); } else { echo '-'; } ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
					</div>
		</div>
	</div>
</div>
<script>
$(function(){
	$('#product-table').DataTable({
		"order": [[4, "asc"]],
		"pageLength": 50,
		"lengthMenu": [[10,25,50,100], [10,25,50,100]],
		"language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" },
		"columnDefs": [ { "orderable": false, "targets": 0 }, { "className": "dt-center", "targets": [0,2,3,4,5,6] } ],
		"responsive": true
	});
});
</script>
</body>
</html>
