<?php
session_start();
require '../config/db_connect.php';

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
<title>รายการรับสินค้า - IchoicePMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/base.css">
<link rel="stylesheet" href="../assets/sidebar.css">
<link rel="stylesheet" href="../assets/components.css">
<link href="../assets/modern-table.css" rel="stylesheet">
<link href="../assets/mainwrap-modern.css" rel="stylesheet">

<style>
    body {
        font-family: 'Sarabun', sans-serif;
        background-color: #f8fafc;
    }
    
    .product-image {
        width: 36px; 
        height: 36px;
        max-width: 48px; 
        max-height: 48px;
        object-fit: cover;
        border-radius: 6px;
        border: 2px solid #e5e7eb;
    }
    
    .qty-plus {
        color: #059669;
        font-weight: 700;
    }
    
    .qty-minus {
        color: #dc2626;
        font-weight: 700;
    }
    
    .breadcrumb-modern {
        background: none;
        padding: 0;
    }
    
    .breadcrumb-modern .breadcrumb-item {
        color: #6b7280;
    }
    
    .breadcrumb-modern .breadcrumb-item.active {
        color: #111827;
        font-weight: 500;
    }

    @media (max-width: 768px) {
        .product-image { width: 28px; height: 28px; }
    }
    @media (max-width: 480px) {
        .product-image { width: 20px; height: 20px; }
    }
</style>

<?php include '../templates/sidebar.php'; ?>
<div class="mainwrap">
    <div class="container-fluid py-4">
        

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #3b82f6;">receipt_long</span>
                    รายการรับสินค้า
                </h1>
                <p class="text-muted mb-0">ประวัติการรับสินค้าเข้าคลัง และการปรับปรุงสต็อก</p>
            </div>
            <div>
                <button class="btn-modern btn-modern-success" onclick="window.location.href='receive_product.php'">
                    <span class="material-icons" style="font-size: 1.25rem;">add_box</span>
                    รับสินค้าใหม่
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="stats-card stats-primary">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รายการทั้งหมด</div>
                                <div class="stats-value"><?= count($rows) ?></div>
                                <div class="stats-subtitle">รายการรับสินค้า</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">receipt</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6 mb-4">
                <div class="stats-card stats-success">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รายการเพิ่ม</div>
                                <div class="stats-value"><?= count(array_filter($rows, fn($r) => $r['receive_qty'] > 0)) ?></div>
                                <div class="stats-subtitle">เพิ่มเข้าสต็อก</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">add_circle</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6 mb-4">
                <div class="stats-card stats-warning">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รายการลด</div>
                                <div class="stats-value"><?= count(array_filter($rows, fn($r) => $r['receive_qty'] < 0)) ?></div>
                                <div class="stats-subtitle">ลดจากสต็อก</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">remove_circle</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons">table_view</span>
                        รายการรับสินค้า (<?= count($rows) ?> รายการ)
                    </h5>
                    <div class="table-actions">
                        <button class="btn-modern btn-modern-secondary btn-sm refresh-table me-2" onclick="refreshTableData()">
                            <span class="material-icons">refresh</span>
                            รีเฟรช
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-body">
                <!-- Batch Actions Bar -->
                <div class="batch-actions mb-3" style="display: none;">
                    <button id="delete-selected" class="btn-modern btn-modern-danger btn-sm" type="button">
                        <span class="material-icons" style="font-size: 1rem;">delete</span>
                        ลบรายการที่เลือก (<span class="selected-count">0</span>)
                    </button>
                </div>

                <table id="receive-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 60px;">รูปภาพ</th>
                            <th>SKU</th>
                            <th>บาร์โค้ด</th>
                            <th>ผู้เพิ่มรายการ</th>
                            <th>วันที่เพิ่ม</th>
                            <th>จำนวนก่อน</th>
                            <th>เพิ่ม/ลด</th>
                            <th>จำนวนล่าสุด</th>
                            <th>ตำแหน่ง</th>
                            <th>ราคาต้นทุน</th>
                            <th>ราคาขาย</th>
                            <th>PO</th>
                            <th>ประเภท</th>
                            <th>หมายเหตุ</th>
                            <th class="no-sort text-center" style="width: 100px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="15" class="text-center py-4">
                            <div class="d-flex flex-column align-items-center">
                                <span class="material-icons mb-2" style="font-size: 3rem; color: #d1d5db;">receipt</span>
                                <h5 class="text-muted">ไม่พบข้อมูลการรับสินค้า</h5>
                                <p class="text-muted mb-0">ยังไม่มีการรับสินค้าเข้าสต็อก</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($rows as $row): ?>
                        <tr data-id="<?= $row['receive_id'] ?>">
                            <td>
                                <?php 
                                $image_path = '../images/noimg.png';
                                if (!empty($row['image'])) {
                                    if (strpos($row['image'], 'images/') === 0) {
                                        $image_path = '../' . $row['image'];
                                    } else {
                                        $image_path = '../images/' . $row['image'];
                                    }
                                }
                                ?>
                                <img src="<?= htmlspecialchars($image_path) ?>" 
                                     alt="<?= htmlspecialchars($row['product_name'] ?? '') ?>" 
                                     class="product-image" 
                                     onerror="this.src='../images/noimg.png'">
                            </td>
                            <td><span class="fw-bold"><?= htmlspecialchars($row['sku']) ?></span></td>
                            <td><?= htmlspecialchars($row['barcode']) ?></td>
                            <td><?= htmlspecialchars($row['created_by'] ?? 'ไม่ระบุ') ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                            <td class="text-center">
                                <span class="fw-bold text-muted">
                                    <?= number_format(getPrevQty($row['sku'], $row['barcode'], $row['created_at'], $pdo)) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?= qtyChange($row['receive_qty']) ?>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold text-primary">
                                    <?= number_format(getCurrentQty($row['sku'], $row['barcode'], $row['created_at'], $pdo)) ?>
                                </span>
                            </td>
                            <td>
                            <?php
                            $rowcode = trim($row['row_code'] ?? '');
                            $bin = trim($row['bin'] ?? '');
                            $shelf = trim($row['shelf'] ?? '');
                            if ($rowcode !== '' && $bin !== '' && $shelf !== '') {
                                echo htmlspecialchars("$rowcode-$bin-$shelf");
                            } else {
                                echo htmlspecialchars($row['location_desc'] ?? '-');
                            }
                            ?>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold text-success">
                                    <?= number_format($row['price_per_unit'], 2) ?> ฿
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold text-info">
                                    <?= number_format($row['sale_price'], 2) ?> ฿
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['po_number'])): ?>
                                    <span class="badge bg-primary"><?= htmlspecialchars($row['po_number']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= getTypeLabel($row['remark'] ?? '') ?></td>
                            <td data-expiry="<?= htmlspecialchars($row['expiry_date'] ?? '') ?>">
                                <?= htmlspecialchars($row['remark'] ?? '-') ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn action-btn-edit edit-btn" 
                                            data-id="<?= $row['receive_id'] ?>"
                                            title="แก้ไข">
                                        <span class="material-icons">edit</span>
                                    </button>
                                </div>
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
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="../assets/modern-table.js"></script>

<script>
// Global variable for table instance
let receiveTable;

$(document).ready(function() {
    // Destroy existing DataTable if any before initializing
    if ($.fn.DataTable.isDataTable('#receive-table')) {
        $('#receive-table').DataTable().destroy();
    }
    
    // Initialize receive items table with modern template
    receiveTable = new ModernTable('receive-table', {
        pageLength: 50,
        language: 'th',
        exportButtons: true,
        batchOperations: true,
        defaultOrder: [[4, 'desc']] // Sort by created date
    });

    // Custom batch delete handler for receive items - remove existing handlers first
    $('#delete-selected').off('click').on('click', function(){
        const selectedIds = $('.row-checkbox:checked').map(function(){ 
            return $(this).val(); 
        }).get();
        
        if(selectedIds.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาเลือกรายการที่ต้องการลบ'
            });
            return;
        }
        
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: `คุณต้องการลบ ${selectedIds.length} รายการหรือไม่?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ลบ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if(result.isConfirmed){
                Swal.fire({
                    title: 'กำลังลบ...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: 'receive_delete.php',
                    method: 'POST',
                    data: { ids: selectedIds },
                    dataType: 'json',
                    success: function(resp){
                        Swal.close();
                        if(resp && resp.success){
                            Swal.fire('สำเร็จ!', `ลบ ${selectedIds.length} รายการเรียบร้อยแล้ว`, 'success')
                            .then(() => refreshTableData());
                        } else {
                            Swal.fire('ข้อผิดพลาด!', resp.message || 'เกิดข้อผิดพลาดในการลบ', 'error');
                        }
                    },
                    error: function(xhr, status, error){
                        Swal.close();
                        Swal.fire('ข้อผิดพลาด!', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                    }
                });
            }
        });
        });

    // ปุ่มแก้ไข (unbind existing handlers first)
    $(document).off('click', '#receive-table .edit-btn').on('click', '#receive-table .edit-btn', function(){
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

    // บันทึกการแก้ไข (unbind existing handlers first)
    $('#save-edit').off('click').on('click', function(){
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
                            refreshTableData();
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

    // Function to refresh table data without full page reload
    function refreshTableData() {
        // Since this is a server-side rendered table, we need to reload the page
        // But first, show loading indicator
        Swal.fire({
            title: 'กำลังรีเฟรชข้อมูล...',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => { Swal.showLoading(); }
        });
        
        // Use setTimeout to ensure loading shows before reload
        setTimeout(() => {
            location.reload();
        }, 300);
    }
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
