<?php
session_start();
require 'db_connect.php';

// Query join ข้อมูลที่ต้องการ
$sql = "
SELECT r.receive_id, p.image, p.sku, p.barcode, u.name AS created_by, r.created_at, 
       r.receive_qty, r.remark_color, r.remark_split, r.remark, r.expiry_date,
       l.row_code, l.bin, l.shelf, l.description AS location_desc,
       poi.price_per_unit, poi.sale_price,
       r.po_id, r.item_id,
       p.name AS product_name,
       r.created_by AS created_by_id,
       po.remark AS po_remark,
       po.po_number AS po_number
FROM receive_items r
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN products p ON poi.product_id = p.product_id
LEFT JOIN locations l ON l.location_id = (
    SELECT pl.location_id FROM product_location pl WHERE pl.product_id = p.product_id LIMIT 1
)
LEFT JOIN users u ON r.created_by = u.user_id
LEFT JOIN purchase_orders po ON r.po_id = po.po_id
ORDER BY r.created_at DESC
LIMIT 500
";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายการรับสินค้า</title>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- jQuery CDN: ต้องมาก่อน DataTables, Bootstrap JS, และ script JS อื่นๆ ที่ใช้ $ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS (ต้องตามหลัง jQuery) -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<!-- Bootstrap 5 JS (bundle รวม Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 (สำหรับ Swal.fire) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<link rel="stylesheet" href="assets/base.css">
<link rel="stylesheet" href="assets/sidebar.css">
<link rel="stylesheet" href="assets/components.css">
<!-- Google Fonts: Prompt -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
<style>
    .table-img {
        width: 36px; height: 36px;
        max-width: 48px; max-height: 48px;
        object-fit: contain;
        border-radius: 6px;
        background: #f3f3f3;
        }
        @media (max-width: 768px) {
        .table-img { width: 28px; height: 28px; }
        }
        @media (max-width: 480px) {
        .table-img { width: 20px; height: 20px; }
        }
</style>

<?php include 'sidebar.php'; ?>
<div class="mainwrap">
    <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="page-bar shadow-sm d-flex align-items-center mb-2" style="background:#fff;border-radius:12px;padding:14px 22px 14px 18px;box-shadow:0 2px 8px 0 rgba(8,86,205,0.07);width:100%;min-width:0;">
                <i class="fa-solid fa-check-square me-2" style="font-size:1.5em;vertical-align:middle;color:#0856cd;"></i>
                <span class="page-title" style="font-size:1.25rem;font-weight:600;color:#0856cd;letter-spacing:0.5px;">รายการรับสินค้า</span>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex mb-2">
                    <button id="delete-selected" class="btn btn-danger btn-sm me-2" type="button"><i class="fa fa-trash"></i> ลบรายการที่เลือก</button>
                </div>
                <div class="table-responsive">
                    <table id="receive-table">
                    <thead>
                    <tr>
                        <th>#<input type="checkbox" id="select-all"></th>
                        <th>ภาพ</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>ผู้เพิ่มรายการ</th>
                        <th>วันที่เพิ่ม</th>
                        <th>จำนวนก่อน</th>
                        <th>เพิ่ม/ลด</th>
                        <th>จำนวนล่าสุด</th>
                        <th>ตำแหน่ง</th>
                        <th>ราคาต้นทุน</th>
                        <th>ราคาขายออก</th>
                        <th>หมายเลข PO</th>
                        <th>ประเภทสินค้า</th>
                        <th>หมายเหตุ</th>
                        <th>แก้ไข</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $row): ?>
                        <tr>
                            <td><input type="checkbox" class="row-checkbox" value="<?= $row['receive_id'] ?>"></td>
                            <td><img   class="table-img" src="<?= htmlspecialchars($row['image']) ?>" alt="img" onerror="this.src='images/noimg.png'" /></td>
                            <td class="sku-col" title="<?= htmlspecialchars($row['sku']) ?>"><?= htmlspecialchars($row['sku']) ?></td>
                            <td><?= htmlspecialchars($row['barcode']) ?></td>
                            <td><?= htmlspecialchars($row['created_by']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td><?= getPrevQty($row['sku'], $row['barcode'], $row['created_at'], $pdo) ?></td>
                            <td><?= qtyChange($row['receive_qty']) ?></td>
                            <td><?= getCurrentQty($row['sku'], $row['barcode'], $row['created_at'], $pdo) ?></td>
                            <td>
                            <?php
                            $rowcode = trim($row['row_code'] ?? '');
                            $bin = trim($row['bin'] ?? '');
                            $shelf = trim($row['shelf'] ?? '');
                            if ($rowcode !== '' && $bin !== '' && $shelf !== '') {
                                echo htmlspecialchars("$rowcode-$bin-$shelf");
                            } else {
                                echo htmlspecialchars($row['location_desc']);
                            }
                            ?>
                            </td>
                            <td class="price-cost"><?= number_format($row['price_per_unit'],2) ?></td>
                            <td class="price-sale"><?= number_format($row['sale_price'],2) ?></td>
                            <td><?= htmlspecialchars($row['po_number']) ?></td>
                            <td><?= getTypeLabel($row['po_remark']) ?></td>
                            <td data-expiry="<?= htmlspecialchars($row['expiry_date'] ?? '') ?>"><?= htmlspecialchars($row['remark']) ?></td>
                            <td><button class="btn btn-sm btn-warning edit-btn" data-id="<?= $row['receive_id'] ?>">แก้ไข</button></td>
                        </tr>
                    <?php endforeach; ?>
                        </tbody>
            </table>
            </div>
    <script>
    $(function(){
        let table = $('#receive-table').DataTable({
            "order": [[5, "desc"]],
            "pageLength": 50,
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" }
        });
        $('#select-all').on('click', function(){
            $('.row-checkbox').prop('checked', this.checked);
        });

        // ปุ่มลบหลายรายการ
        $('#delete-selected').on('click', function(){
            let ids = $('.row-checkbox:checked').map(function(){ return $(this).val(); }).get();
            if(ids.length === 0) {
                Swal.fire({icon:'warning',title:'กรุณาเลือกรายการที่ต้องการลบ'});
                return;
            }
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: `คุณต้องการลบ ${ids.length} รายการหรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result)=>{
               if(result.isConfirmed){
                    $.ajax({
                        url: 'receive_delete.php',
                        method: 'POST',
                        data: { ids: ids },
                        dataType: 'json',
                        success: function(resp){
                            if(resp && resp.success){
                                // ลบแถวที่เลือกออกจาก DataTable
                                $('.row-checkbox:checked').each(function(){
                                    table.row($(this).closest('tr')).remove();
                                });
                                table.draw();

                                Swal.fire({
                                    icon: 'success',
                                    title: 'ลบสำเร็จ!',
                                    text: `รายการ ${ids.length} รายการถูกลบเรียบร้อยแล้ว`,
                                    showConfirmButton: false,
                                    timer: 2000   // 2 วิ
                                });

                                $('#select-all').prop('checked', false);
                            } else {
                                Swal.fire('ผิดพลาด!', (resp && resp.msg) ? resp.msg : 'ไม่สามารถลบได้', 'error');
                            }
                        },
                        error: function(xhr, status, error){
                            Swal.fire('ผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
                        }
                    });
                }

            });
        });

        // ปุ่มแก้ไข (bind ครั้งเดียว)
        $('#receive-table').off('click', '.edit-btn').on('click', '.edit-btn', function(){
            let row = $(this).closest('tr');
            let id = $(this).data('id');
            let remark = row.find('td').eq(13).text();
            let qtyText = row.find('td').eq(7).text();
            let qty = qtyText.replace(/[^\d]/g, '');
            let qtyType = qtyText.indexOf('-') !== -1 ? 'minus' : 'plus';
            let expiry = row.find('td').eq(13).attr('data-expiry') || '';
            let priceCost = row.find('td').eq(10).text().replace(/,/g, '');
            let priceSale = row.find('td').eq(11).text().replace(/,/g, '');
            // ใส่ค่าอื่นใน modal ก่อน
            $('#edit-receive-id').val(id);
            $('#edit-remark').val(remark);
            $('#edit-price-cost').val(priceCost);
            $('#edit-price-sale').val(priceSale);
            $('#edit-qty-type').val(qtyType);
            $('#edit-receive-qty').val(qty);
            $('#edit-expiry-date').val(expiry);
            // clear select ก่อน
            $('#edit-row-code').val('');
            $('#edit-bin').val('');
            $('#edit-shelf').val('');
            // AJAX ไปหา row_code, bin, shelf
            $.get('receive_position_api.php', { receive_id: id }, function(resp){
                if(resp && resp.success) {
                    let rowCode = resp.row_code || '';
                    let bin = resp.bin || '';
                    let shelf = resp.shelf || '';
                    function setSelectWithDynamicOption(sel, val) {
                        val = (val || '').toString().trim();
                        if(val && sel.find('option[value="'+val+'"]').length === 0) {
                            sel.append('<option value="'+val+'">'+val+'</option>');
                        }
                        sel.val(val).trigger('change');
                    }
                    setSelectWithDynamicOption($('#edit-row-code'), rowCode);
                    setSelectWithDynamicOption($('#edit-bin'), bin);
                    setSelectWithDynamicOption($('#edit-shelf'), shelf);
                }
                var modal = new bootstrap.Modal(document.getElementById('editModal'));
                modal.show();
            }, 'json');
        });

        // บันทึกการแก้ไข
        $('#save-edit').on('click', function(){
            // ป้องกันกดซ้ำ
            let $btn = $(this);
            if ($btn.prop('disabled')) return;
            $btn.prop('disabled', true);

            // Validate
            let qty = parseInt($('#edit-receive-qty').val()) || 0;
            let priceCost = parseFloat($('#edit-price-cost').val()) || 0;
            let priceSale = parseFloat($('#edit-price-sale').val()) || 0;
            let remark = $('#edit-remark').val().trim();
            if (isNaN(qty) || $('#edit-receive-qty').val() === '') {
                Swal.fire('กรุณากรอกจำนวน', '', 'warning'); $btn.prop('disabled', false); return;
            }
            if (isNaN(priceCost) || $('#edit-price-cost').val() === '') {
                Swal.fire('กรุณากรอกราคาต้นทุน', '', 'warning'); $btn.prop('disabled', false); return;
            }
            if (isNaN(priceSale) || $('#edit-price-sale').val() === '') {
                Swal.fire('กรุณากรอกราคาขาย', '', 'warning'); $btn.prop('disabled', false); return;
            }

            // ปรับจำนวนตามประเภท
            let qtyType = $('#edit-qty-type').val();
            if(qtyType === 'minus') qty = -Math.abs(qty);
            else qty = Math.abs(qty);
            $('#edit-receive-qty').val(qty);

            // รวมตำแหน่งเป็น description ด้วย (optionally ส่งไป backend)
            let rowCode = $('#edit-row-code').val();
            let bin = $('#edit-bin').val();
            let shelf = $('#edit-shelf').val();
            if($('#edit-form input[name="location_desc"]').length === 0){
                $('#edit-form').append('<input type="hidden" name="location_desc" id="edit-location-desc">');
            }
            let locDesc = rowCode && bin && shelf ? `${rowCode}-${bin}-${shelf}` : '';
            $('#edit-location-desc').val(locDesc);

            let formData = $('#edit-form').serialize();
            Swal.fire({
                title: 'กำลังบันทึก...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            $.post('receive_edit.php', formData, function(resp){
                $btn.prop('disabled', false);
                 if(resp.success){
                        Swal.fire({
                            icon: 'success',
                            title: 'บันทึกสำเร็จ',
                            text: 'บันทึกการแก้ไขเรียบร้อย',
                            showConfirmButton: false,
                            timer: 2000   // 2 วิ
                        }).then(() => {
                            location.reload();
                        });

                        var modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
                        modal.hide();
                }else{
                    Swal.fire('ผิดพลาด', resp.message || 'ไม่สามารถบันทึกได้', 'error');
                }
            },'json').fail(function(xhr){
                $btn.prop('disabled', false);
                Swal.fire('ผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
            });
        });
    });
    </script>

<!-- Modal แก้ไข -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">แก้ไขรายการรับสินค้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-form">
                    <input type="hidden" name="receive_id" id="edit-receive-id">
                    <div class="mb-2">
                        <label for="edit-remark" class="form-label">หมายเหตุ</label>
                        <input type="text" class="form-control" name="remark" id="edit-remark">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">ตำแหน่ง</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <select class="form-select" name="row_code" id="edit-row-code">
                                    <option value="">แถว</option>
                                    <?php
                                    foreach (range('A','X') as $c) {
                                        echo '<option value="'.$c.'">'.$c.'</option>';
                                    }
                                    echo '<option value="T">T(ตู้)</option>';
                                    echo '<option value="sale(บน)">sale(บน)</option>';
                                    echo '<option value="sale(ล่าง)">sale(ล่าง)</option>';
                                    ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <select class="form-select" name="bin" id="edit-bin">
                                    <option value="">ล๊อค</option>
                                    <?php for($i=1;$i<=10;$i++) echo '<option value="'.$i.'">'.$i.'</option>'; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <select class="form-select" name="shelf" id="edit-shelf">
                                    <option value="">ชั้น</option>
                                    <?php for($i=1;$i<=10;$i++) echo '<option value="'.$i.'">'.$i.'</option>'; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="edit-price-cost" class="form-label">ราคาต้นทุน</label>
                        <input type="number" step="0.01" class="form-control" name="price_per_unit" id="edit-price-cost">
                    </div>
                    <div class="mb-2">
                        <label for="edit-price-sale" class="form-label">ราคาขายออก</label>
                        <input type="number" step="0.01" class="form-control" name="sale_price" id="edit-price-sale">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">ประเภทการเปลี่ยนแปลง</label>
                        <select class="form-select" id="edit-qty-type">
                            <option value="plus">เพิ่ม</option>
                            <option value="minus">ลด</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="edit-receive-qty" class="form-label">จำนวน</label>
                        <input type="number" class="form-control" name="receive_qty" id="edit-receive-qty">
                    </div>
                    <div class="mb-2">
                        <label for="edit-expiry-date" class="form-label">วันหมดอายุ</label>
                        <input type="date" class="form-control" name="expiry_date" id="edit-expiry-date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" id="save-edit">บันทึก</button>
            </div>
        </div>
    </div>
</div>
</script>
<?php
// Helper ฟังก์ชัน (ควรย้ายไปไฟล์แยกถ้า production)
function getPrevQty($sku, $barcode, $created_at, $pdo) {
    $stmt = $pdo->prepare("SELECT SUM(receive_qty) FROM receive_items r LEFT JOIN purchase_order_items poi ON r.item_id=poi.item_id LEFT JOIN products p ON poi.product_id=p.product_id WHERE p.sku=? AND p.barcode=? AND r.created_at < ?");
    $stmt->execute([$sku, $barcode, $created_at]);
    return (int)$stmt->fetchColumn();
}
function getCurrentQty($sku, $barcode, $created_at, $pdo) {
    $stmt = $pdo->prepare("SELECT SUM(receive_qty) FROM receive_items r LEFT JOIN purchase_order_items poi ON r.item_id=poi.item_id LEFT JOIN products p ON poi.product_id=p.product_id WHERE p.sku=? AND p.barcode=? AND r.created_at <= ?");
    $stmt->execute([$sku, $barcode, $created_at]);
    return (int)$stmt->fetchColumn();
}
function qtyChange($qty) {
    if($qty > 0) return '<span class="qty-plus" style="color:#22bb33;font-weight:bold;">+'.(int)$qty.'</span>';
    if($qty < 0) return '<span class="qty-minus" style="color:#e74c3c;font-weight:bold;">'.(int)$qty.'</span>';
    return $qty;
}
function getTypeLabel($remark) {
    if(stripos($remark, 'excel') !== false) return '<span class="badge bg-info">Excel</span>';
    return '<span class="badge bg-secondary">Manual</span>';
}
?>
</body>
</html>
