<?php
session_start();
require 'db_connect.php';

$q = trim($_GET['q'] ?? '');

$sql = "
SELECT 
    p.product_id,
    p.name,
    p.sku,
    p.barcode,
    p.unit,
    p.image,
    p.remark_color,
    SUM(ri.receive_qty) AS total_qty,   -- รวมจำนวน
    ri.remark_sale,
    ri.expiry_date
FROM products p
LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
RIGHT JOIN receive_items ri ON ri.item_id = poi.item_id
";

// ถ้ามีการค้นหา
$params = [];
if($q !== '') {
    $sql .= " WHERE p.name LIKE :name OR p.sku LIKE :sku OR p.barcode LIKE :barcode ";
    $params = [
        ':name' => "%$q%",
        ':sku' => "%$q%",
        ':barcode' => "%$q%"
    ];
}

// รวมจำนวนตาม SKU และ Barcode สำหรับสินค้าที่ไม่ถูกแบ่งขาย
$sql .= " 
GROUP BY p.product_id, p.name, p.sku, p.barcode, p.unit, p.image, p.remark_color, ri.remark_sale, ri.expiry_date
ORDER BY p.name
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<table>
<thead>
<tr>
    <th>#</th>
    <th>ภาพสินค้า</th>
    <th>ชื่อสินค้า</th>
    <th>SKU</th>
    <th>Barcode</th>
    <th>จำนวนคงคลัง</th>
        <th>หน่วย</th>
    <th>Remark สี</th>
    <th>Remark แบ่งขาย</th>
    <th>วันหมดอายุ</th>
    <th>จัดการ</th>
</tr>
</thead>
<tbody>';

if($products){
 foreach($products as $k => $p){
    $imgSrc = !empty($p['image']) ? htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8') : 'assets/no-image.png';
    // $qty = floatval($p['receive_qty'] ?? 0); // เปลี่ยนเป็น float สำหรับคำนวณ
    $name = htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $sku = htmlspecialchars($p['sku'] ?? '', ENT_QUOTES, 'UTF-8');
    $barcode = htmlspecialchars($p['barcode'] ?? '', ENT_QUOTES, 'UTF-8');
    $unit = htmlspecialchars($p['unit'] ?? '', ENT_QUOTES, 'UTF-8');
    $remark_color = htmlspecialchars($p['remark_color'] ?? '', ENT_QUOTES, 'UTF-8');
    $remark_sale = $p['remark_sale'] ?? null; // ดึงค่า raw ก่อนคำนวณ
    $expiry_date = htmlspecialchars($p['expiry_date'] ?? '', ENT_QUOTES, 'UTF-8');

    $qty = floatval($p['total_qty'] ?? 0);       // จำนวนจริง
    $remark_sale_raw = $p['remark_sale'] ?? null;  // ค่า remark_sale จาก DB

    if (!empty($remark_sale_raw) && $remark_sale_raw > 0) {
        $display_qty = $qty / floatval($remark_sale_raw);
    } else {
        $display_qty = $qty;
    }


    // แสดงผล remark_sale
   $display_qty = number_format($display_qty);

  echo "<tr>
        <td>".($k+1)."</td>
        <td><img src='{$imgSrc}' style='width:60px;height:60px;object-fit:cover;border-radius:8px;'></td>
        <td>{$name}</td>
        <td>{$sku}</td>
        <td>{$barcode}</td>
        <td>{$display_qty}</td>
        <td>{$unit}</td>
        <td>{$remark_color}</td>
        <td>{$remark_sale}</td>
        <td>{$expiry_date}</td>
        <td>
            <button class='btn btn-sm btn-warning btn-edit-product'
                    data-id='".$p['product_id']."'
                    data-name='".$p['name']."'
                    data-sku='".$p['sku']."'
                    data-barcode='".$p['barcode']."'
                    data-unit='".$p['unit']."'
                    data-qty='".$display_qty ."'>
                แก้ไข
            </button>

            <button class='btn btn-sm btn-danger btn-delete-product' data-id='".$p['product_id']."'>ลบ</button>
        </td>
    </tr>";



    }
} else {
    echo '<tr><td colspan="11" class="center">ไม่พบข้อมูลสินค้า</td></tr>';
}

echo '</tbody></table>';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
    $('.btn-delete-product').on('click', function(){
        var btn = $(this);
        var id = btn.data('id');

        Swal.fire({
            title: 'คุณแน่ใจหรือไม่?',
            text: "หากลบแล้วจะไม่สามารถกู้คืนได้!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('receive_delete.php', {id: id}, function(resp){
                    if(resp.success){
                        btn.closest('tr').fadeOut();
                        Swal.fire('ลบสำเร็จ!','สินค้าถูกลบเรียบร้อยแล้ว','success');
                    } else {
                        Swal.fire('ผิดพลาด!','ไม่สามารถลบสินค้าได้','error');
                    }
                }, 'json');
            }
        });
    });
});
</script>
