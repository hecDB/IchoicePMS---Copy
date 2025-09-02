<?php
session_start();
include 'sidebar.php';
require 'db_connect.php';

$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_role = $_SESSION['user_role'] ?? 'user';
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
 <div class="mainwrap">
       <div class="topbar">
   ความเคลื่อนไหวสินค้า 
    </div>  

    <div class="table-card">
<div class="table-card">
    <div class="search-bar">
        <input type="text" id="search" placeholder="ค้นหาสินค้าด้วยชื่อ, SKU หรือ Barcode">
    </div>

    <div id="result-table">
        <div class="table-card">
        <!-- ตารางผลลัพธ์จะแสดงตรงนี้ -->
         </div>
    </div>
    </div>
 </div>


  
<!-- Bootstrap Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="formEditProduct">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">แก้ไขข้อมูลสินค้า</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="product_id" id="edit_product_id">
          <div class="mb-2">
            <label>ชื่อสินค้า</label>
            <input type="text" class="form-control" name="name" id="edit_name">
          </div>
          <div class="mb-2">
            <label>SKU</label>
            <input type="text" class="form-control" name="sku" id="edit_sku">
          </div>
          <div class="mb-2">
            <label>Barcode</label>
            <input type="text" class="form-control" name="barcode" id="edit_barcode">
          </div>
          <div class="mb-2">
            <label>หน่วยนับ</label>
            <input type="text" class="form-control" name="unit" id="edit_unit">
          </div>
          <div class="mb-2">
            <label>จำนวน (รับเข้า)</label>
            <input type="number" class="form-control" name="receive_qty" id="edit_qty">
            <small class="text-danger">* การแก้ไขจำนวนจะลบข้อมูลรับเข้าปัจจุบันออกทั้งหมด</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">บันทึก</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        </div>
      </div>
    </form>
  </div>
</div>
  </div>
<script>
    $(document).ready(function(){
        function fetchData(query='') {
            $.ajax({
                url: 'product_activity_search.php',
                type: 'GET',
                data: { q: query },
                success: function(data){
                    $('#result-table').html(data);
                }
            });
        }

        // เรียกครั้งแรกเพื่อแสดงทั้งหมด
        fetchData();

        // ค้นหาแบบ real-time
        $('#search').on('keyup', function(){
            var q = $(this).val();
            fetchData(q);
        });
    });

    // เปิด modal และเติม data ลงฟอร์ม
    $(document).on('click', '.btn-edit-product', function() {
        $('#edit_product_id').val($(this).data('id'));
        $('#edit_name').val($(this).data('name'));
        $('#edit_sku').val($(this).data('sku'));
        $('#edit_barcode').val($(this).data('barcode'));
        $('#edit_unit').val($(this).data('unit'));
        $('#edit_qty').val($(this).data('qty'));

        // Bootstrap 5 วิธีเปิด modal
        var myModal = new bootstrap.Modal(document.getElementById('editProductModal'), {});
        myModal.show();
    });


// บันทึกการแก้ไข
$('#formEditProduct').on('submit', function(e) {
    e.preventDefault();
    $.post('product_edit_save.php', $(this).serialize(), function(resp){
        if(resp.success){
            Swal.fire('สำเร็จ!', resp.message, 'success').then(() => location.reload());
        }else{
            Swal.fire('ผิดพลาด!', resp.message, 'error');
        }
    }, 'json');
});

</script>


</body>
</html>
