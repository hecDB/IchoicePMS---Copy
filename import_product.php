<?php
session_start();
require_once 'db_connect.php';
include 'sidebar.php';
// if($_SESSION['user_role']!=='admin'){ http_response_code(403); exit; }

$user_id = $_SESSION['user_id'] ?? 0;
$message = "";
$uploadDir = __DIR__ . '/images/';
$imgWebPath = 'images/';

ini_set('display_errors', 1); error_reporting(E_ALL);

if(isset($_POST['submit']) && !empty($_POST['items'])) {
    $pdo->beginTransaction();
    try {
        // สร้างเลข PO
        $date = date('Ymd');
        $last_po = $pdo->query("SELECT po_number FROM purchase_orders WHERE po_number LIKE 'PO{$date}%' ORDER BY po_number DESC LIMIT 1")->fetchColumn();
        $num = $last_po ? intval(substr($last_po, -3)) + 1 : 1;
        $po_number = 'PO'.$date.str_pad($num,3,'0',STR_PAD_LEFT);

        // คำนวณยอดรวม
        $total_amount = 0;
        foreach($_POST['items'] as $item){
            $total_amount += floatval($item['qty']) * floatval($item['price']);
        }

        // insert PO
        $stmt = $pdo->prepare("INSERT INTO purchase_orders 
            (po_number, supplier_id, order_date, total_amount, ordered_by, status, remark)
            VALUES (?, ?, NOW(), ?, ?, ?, ?)");
        $stmt->execute([$po_number, 1, $total_amount, $user_id, 'pending', 'imported from form']);
        $po_id = $pdo->lastInsertId();

        // insert items
        foreach($_POST['items'] as $idx=>$item){
            $sku = $item['sku']; $barcode=$item['barcode']; $name=$item['name'];
            $unit=$item['unit']; $row_code=$item['row_code']; $bin=$item['bin']; $shelf=$item['shelf'];
            $qty=floatval($item['qty']); $price=floatval($item['price']);

            // upload image
            $imageFile = '';
            if(!empty($_FILES['items']['name'][$idx]['image'])){
                $tmp_name = $_FILES['items']['tmp_name'][$idx]['image'];
                $filename = time().'_'.basename($_FILES['items']['name'][$idx]['image']);
                if(is_uploaded_file($tmp_name) && move_uploaded_file($tmp_name,$uploadDir.$filename)){
                    $imageFile = $filename;
                }
            }

            // ตรวจสอบ/เพิ่มสินค้า
            $stmt = $pdo->prepare("SELECT product_id FROM products WHERE sku=? OR barcode=?");
            $stmt->execute([$sku,$barcode]);
            $product_id = $stmt->fetchColumn();
      if(!$product_id){
        $stmt = $pdo->prepare("INSERT INTO products (name, sku, barcode, unit, image, created_by, created_at) VALUES (?,?,?,?,?,?,NOW())");
        $stmt->execute([$name,$sku,$barcode,$unit,'images/'.$imageFile,$user_id]);
        $product_id = $pdo->lastInsertId();
      }

            // ตรวจสอบ/เพิ่ม location
            $stmt = $pdo->prepare("SELECT location_id FROM locations WHERE row_code=? AND bin=? AND shelf=?");
            $stmt->execute([$row_code,$bin,$shelf]);
            $loc = $stmt->fetch();
            $location_id = $loc ? $loc['location_id'] : null;
      if(!$location_id){
        $desc = "$row_code-$bin-$shelf";
        $stmt = $pdo->prepare("INSERT INTO locations (row_code, bin, shelf, description) VALUES (?,?,?,?)");
        $stmt->execute([$row_code,$bin,$shelf,$desc]);
        $location_id = $pdo->lastInsertId();
      }

            // product_location
            $stmt = $pdo->prepare("SELECT 1 FROM product_location WHERE product_id=? AND location_id=?");
            $stmt->execute([$product_id,$location_id]);
            if(!$stmt->fetch()){
                $stmt = $pdo->prepare("INSERT INTO product_location (product_id, location_id) VALUES (?,?)");
                $stmt->execute([$product_id,$location_id]);
            }

            // insert PO item
            $stmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, price_per_unit, total) VALUES (?,?,?,?,?)");
            $stmt->execute([$po_id,$product_id,$qty,$price,$qty*$price]);
            $item_id = $pdo->lastInsertId();

            // insert receive
            $stmt = $pdo->prepare("INSERT INTO receive_items (created_at, po_id, item_id, receive_qty, created_by, remark) VALUES (NOW(),?,?,?,?,?)");
            $stmt->execute([$po_id,$item_id,$qty,$user_id,'imported']);
        }

        $pdo->commit();
        $message = "บันทึกข้อมูลสำเร็จ PO: <b>$po_number</b>";
    } catch(Exception $e){
        $pdo->rollBack();
        $message = "เกิดข้อผิดพลาด: ".$e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>

<style>
.table-card {
    background:#fff; border-radius:12px; box-shadow:0 2px 18px #0001; padding:20px; max-width:1200px; margin:auto;
}
.table-card h2 { color:#fff; background:#2261ad; padding:12px 20px; border-radius:12px 12px 0 0; margin:-20px -20px 20px -20px; }
.table-product thead th { background:#e7edf7; color:#333; text-align:center; padding:10px; border-bottom:1px solid #d1d7e0; }
.table-product tbody td { padding:6px 10px; }
.form-control { width:100%; padding:6px 10px; border:1px solid #d1d7e0; border-radius:6px; font-size:0.95rem; }
.file-input { font-size:0.85rem; }
.add-row-btn, .save-btn { display:inline-flex; align-items:center; gap:6px; background:#2261ad; color:#fff; border:none; padding:10px 16px; border-radius:12px; cursor:pointer; font-size:0.95rem; margin-top:10px; }
.remove-row-btn { background:#fff; border:1px solid #d1d7e0; border-radius:50%; width:36px; height:36px; display:flex; justify-content:center; align-items:center; cursor:pointer; }
.remove-row-btn span { color:#285599; font-size:1.5em; }

.table-product input,
.table-product select,
.table-product button {
  font-size: 0.85rem;
  padding: 4px 6px;
}

.table-product th,
.table-product td {
  padding: 6px 4px;
  white-space: nowrap; /* ป้องกันข้อความ wrap เกิน */
}

.table-responsive {
  overflow-x: auto;
}

/* ปรับขนาดบนมือถือ */
@media (max-width: 768px) {
  .table-product input,
  .table-product select,
  .table-product button {
    font-size: 0.75rem;
    padding: 2px 4px;
  }
  .table-product th,
  .table-product td {
    padding: 2px 3px;
  }
  .table-product {
    font-size: 0.8rem;
  }
  .table-product td > div.d-flex {
    flex-wrap: wrap;
  }
}


</style>
</head>
<body>

<div class="mainwrap">
<div class="topbar mb-3">เพิ่มสินค้าใหม่</div>

<div class="table-card">
  <h2>เพิ่มรายการสินค้า</h2>

  <form method="post" enctype="multipart/form-data">
   
<div class="table-responsive">
  <table class="table-product" id="items-table">
    <thead>
      <tr>
        <th>SKU</th><th>Barcode</th><th>ชื่อสินค้า</th><th>รูปภาพ</th>
        <th>หน่วย</th><th>Row</th><th>Bin</th><th>Shelf</th>
        <th>จำนวน</th><th>ราคา/หน่วย</th><th>ราคาขาย</th><th>ลบ</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><input class="form-control" type="text" name="items[0][sku]"></td>
        <td>
          <div class="d-flex gap-1">
            <input class="form-control" type="text" name="items[0][barcode]" >
            <button type="button" class="btn btn-sm btn-primary scan-btn">สแกน</button>
          </div>
        </td>
        <td><input class="form-control" type="text" name="items[0][name]" required></td>
        <!-- <td><input class="form-control file-input" type="file" name="items[0][image]" accept="image/*"></td> -->
        <td>
        <input class="form-control file-input" type="file" name="items[0][image]" accept="image/*" capture="environment">
        </td>

        <td><input class="form-control" type="text" name="items[0][unit]"></td>
        <td>
          <select class="form-select" name="items[0][row_code]" required>
            <option value="">แถว</option>
            <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
                <option value="E">E</option>
                <option value="F">F</option>
                <option value="G">G</option>
                <option value="H">H</option>
                <option value="I">I</option>
                <option value="J">J</option>
                <option value="K">K</option>
                <option value="L">L</option>
                <option value="M">M</option>
                <option value="N">N</option>
                <option value="O">O</option>
                <option value="P">P</option>
                <option value="Q">Q</option>
                <option value="R">R</option>
                <option value="S">S</option>
                <option value="T">T (ตู้)</option>
                <option value="U">U</option>
                <option value="V">V</option>
                <option value="W">W</option>
                <option value="X">X</option>
                <option value="Sale(บน)">Sale(บน)</option>
                <option value="Sale(ล่าง)">Sale(ล่าง)</option>
          </select>
        </td>
        <td>
          <select class="form-select" name="items[0][bin]" required>
            <option value="">ล็อค</option>
            <option value="1">1</option>
              <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
                <option value="8">8</option>
                <option value="9">9</option>
                <option value="10">10</option>
          </select>
        </td>
        <td>
          <select class="form-select" name="items[0][shelf]" required>
            <option value="">ชั้น</option><option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
                <option value="8">8</option>
                <option value="9">9</option>
                <option value="10">10</option>
          </select>
        </td>
        <td><input class="form-control" type="number" min="1" name="items[0][qty]" required></td>
        <td><input class="form-control" type="number" min="0" step="0.01" name="items[0][price]" required></td>
        <td><input class="form-control" type="number" min="0" step="0.01" name="items[0][sale_price]"></td>
        <td><button type="button" class="remove-row-btn btn btn-outline-secondary btn-sm"><span class="material-icons">delete</span></button></td>
      </tr>
    </tbody>
  </table>
</div>


    <button type="button" class="add-row-btn btn btn-primary btn-sm mt-2">
      <span class="material-icons">add_circle</span> เพิ่มสินค้า
    </button>
    <button type="submit" name="submit" class="save-btn btn btn-success btn-sm mt-2">
      <span class="material-icons">save_alt</span> บันทึกข้อมูล
    </button>
  </form>
</div>

<!-- Modal สแกนเต็มหน้าจอมือถือ -->
<div class="modal fade" id="barcodeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">สแกนบาร์โค้ด</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div id="reader" style="width:100%; height:100vh;"></div>
      </div>
    </div>
  </div>
</div>


<?php if($message): ?>
<script>
Swal.fire({
icon: "<?= strpos($message,'สำเร็จ')!==false?'success':'error' ?>",
title: "<?= strpos($message,'สำเร็จ')!==false?'สำเร็จ':'ผิดพลาด' ?>",
html: '<?= addslashes($message) ?>',
timer: 3200,
showConfirmButton: false
});
</script>
<?php endif; ?>

<script>
$(document).ready(function(){

let rowIdx = $('#items-table tbody tr').length;

// เพิ่มแถวใหม่
$('.add-row-btn').click(function(){
let newRow = $('#items-table tbody tr:first').clone();
newRow.find('input,select').each(function(){
let name = $(this).attr('name');
if(name){ $(this).attr('name',name.replace(/\d+/,rowIdx)); $(this).val(''); }
});
$('#items-table tbody').append(newRow);
rowIdx++;
});

// ลบแถว
$('#items-table').on('click','.remove-row-btn',function(){
if($('#items-table tbody tr').length>1){
$(this).closest('tr').fadeOut(150,function(){ $(this).remove(); });
}
});

// สแกนบาร์โค้ด
let html5QrCode;
let currentInput;

$(document).on('click', '.scan-btn', function(){
  currentInput = $(this).closest('td').find('input');
  $('#barcodeModal').modal('show');

  html5QrCode = new Html5Qrcode("reader");
  html5QrCode.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: 250 },
    decodedText => {
      currentInput.val(decodedText);
      html5QrCode.stop();
      $('#barcodeModal').modal('hide');
    },
    errorMessage => {}
  ).catch(err => console.error(err));
});

// ปิด modal หยุดกล้อง
$('#barcodeModal').on('hidden.bs.modal', function(){
  if(html5QrCode){ html5QrCode.stop().catch(err=>console.error(err)); }
});


});
</script>

</body>
</html>
