<?php
session_start();

require '../config/db_connect.php';

$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_role = $_SESSION['user_role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<!-- jQuery & DataTables JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- <style>
/* --- ใส่ style ของคุณที่นี่ --- */
.table-card{
    background:#fff;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    overflow:hidden;
    margin-top:15px;
}
</style> -->
</head>
<body>
  <?php include '../templates/sidebar.php'; ?>
<div class="topbar">
        ความเคลื่อนไหวสินค้า
    </div>

    <div class="card-sec">
    <div class="card">
    <div class="d-flex justify-content-end mb-3">
        <button id="delete-selected" class="btn btn-danger">ลบรายการที่เลือก</button>
    </div>

    <div class="table-responsive">
        <table id="activity-table" class="display table table-striped" style="width:100%">
            <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>SKU Merchant</th>
                <th>ผู้ทำรายการ</th>
                <th>เวลา</th>
                <th>ประเภท</th>
                <th>จาก</th>
                <th>ไป</th>
                <th>จำนวน</th>
                <th>หมายเหตุ/เอกสาร</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let table = $('#activity-table').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "product_activity_search.php",
            "type": "GET"
        },
        "columns": [
            {
                "data": "activity_id",
                "render": function(data, type, row) {
                    return `<input type="checkbox" class="activity-checkbox" value="${data}">`;
                },
                "orderable": false,
                "searchable": false
            },
            { "data": "product_info", "orderable": false },
            { "data": "user_name" },
            { "data": "activity_date" },
            { "data": "activity_type" },
            { "data": "location_from" },
            { "data": "location_to" },
            { "data": "quantity" },
            { "data": "reference", "orderable": false }
        ],
        "order": [[3, "desc"]],
        "pageLength": 50,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json"
        }
    });

    // Select all checkboxes
    $('#select-all').on('click', function(){
        $('.activity-checkbox').prop('checked', this.checked);
    });

    // Handle click on individual checkbox to update "select-all"
    $('#activity-table tbody').on('click', '.activity-checkbox', function(){
        if(!this.checked){
            $('#select-all').prop('checked', false);
        }
    });

    // Delete Selected button
    $('#delete-selected').on('click', function(){
        let selectedIds = [];
        $('.activity-checkbox:checked').each(function(){
            selectedIds.push($(this).val());
        });

        if(selectedIds.length === 0){
            Swal.fire('ไม่ได้เลือกรายการ', 'กรุณาเลือกรายการที่ต้องการลบ', 'info');
            return;
        }

        Swal.fire({
            title: 'คุณแน่ใจหรือไม่?',
            text: `คุณต้องการลบ ${selectedIds.length} รายการที่เลือกใช่หรือไม่?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('activity_delete.php', { ids: selectedIds }, function(resp){
                    if(resp.success){
                        table.draw(false); // refresh table หน้าเดิม
                        Swal.fire('ลบสำเร็จ!',`รายการ ${selectedIds.length} รายการถูกลบเรียบร้อยแล้ว`,'success');
                        $('#select-all').prop('checked', false);
                    } else {
                        Swal.fire('ผิดพลาด!', resp.message || 'ไม่สามารถลบได้', 'error');
                    }
                }, 'json');
            }
        });
    });
});
</script>
</body>
</html>
</script>
</body>
</html>
