
<!-- ZXing UMD (global window.ZXing) -->
<script defer src="https://cdn.jsdelivr.net/npm/@zxing/library@0.20.0/umd/index.min.js"></script>
<style>
/* Scanner UI */
.barcode-wrap { position: relative; }
.barcode-wrap .scan-btn {
  position:absolute; right:6px; top:50%; transform:translateY(-50%);
  border:0; padding:6px 10px; border-radius:6px; cursor:pointer;
  background:#2261ad; color:#fff; font-weight:600;
}
.scanner-modal{
  position:fixed; inset:0; background:rgba(0,0,0,.6);
  display:none; align-items:center; justify-content:center; z-index:9999;
}
.scanner-box{ background:#111; padding:10px; border-radius:12px; width:min(96vw,560px); }
#preview{ width:100%; border-radius:8px; background:#000; }
.scanner-controls{
  display:flex; gap:8px; align-items:center; justify-content:center;
  flex-wrap:wrap; margin-top:8px;
}
#cameraSelect{ max-width: 70%; }
.mirror { transform: scaleX(-1); }
</style>

<?php
session_start();
require_once '../config/db_connect.php';
include '../templates/sidebar.php';
//if($_SESSION['user_role']!=='admin'){ http_response_code(403); exit; }//จำกัดสิทธิ์ให้ admin

$user_id = $_SESSION['user_id'] ?? 0;
$message = "";

// โฟลเดอร์เก็บรูป
$uploadDir = __DIR__ . '/images/';
$imgWebPath = 'images/';

// DEBUG: เปิด error reporting
ini_set('display_errors', 1); error_reporting(E_ALL);

if(isset($_POST['submit'])) {
    if(!isset($_POST['items']) || !is_array($_POST['items'])){
        $message = "ไม่พบข้อมูลสินค้า (POST['items'])";
    }else{
        $pdo->beginTransaction();
        try {
            // === สร้างเลข PO
            $date = date('Ymd');
            $last_po = $pdo->query("SELECT po_number FROM purchase_orders WHERE po_number LIKE 'PO{$date}%' ORDER BY po_number DESC LIMIT 1")->fetchColumn();
            $num = $last_po ? intval(substr($last_po, -3)) + 1 : 1;
            $po_number = 'PO'.$date.str_pad($num,3,'0',STR_PAD_LEFT);
    
            // === คำนวณยอดรวม
            $total_amount = 0;
            foreach($_POST['items'] as $item){
                $total_amount += floatval($item['qty']) * floatval($item['price']);
            }
    
            // === insert PO
            $stmt = $pdo->prepare("INSERT INTO purchase_orders 
                (po_number, supplier_id, order_date, total_amount, ordered_by, status, remark)
                VALUES (?, ?, NOW(), ?, ?, ?, ?)");
            $stmt->execute([
                $po_number,        // po_number
                1,                 // supplier_id
                $total_amount,     // total_amount
                $user_id,          // ordered_by
                'pending',         // status
                'imported from form' // remark
            ]);


            $po_id = $pdo->lastInsertId();
    
            // === สินค้าแต่ละรายการ
            foreach($_POST['items'] as $idx => $item){
                $sku = $item['sku'];
                $barcode = $item['barcode'];
                $name = $item['name'];
                $unit = $item['unit'];
                $category = $item['category'] ?? '';
                $row_code = $item['row_code'];
                $bin = $item['bin'];
                $shelf = $item['shelf'];
                $qty = floatval($item['qty']);
                $price = floatval($item['price']);
    
                // === upload IMAGE
                $imageFile = '';
                if(isset($_FILES['items']['name'][$idx]['image']) && $_FILES['items']['name'][$idx]['image']){
                    $tmp_name = $_FILES['items']['tmp_name'][$idx]['image'];
                    $filename = time().'_'.basename($_FILES['items']['name'][$idx]['image']);
                    if(is_uploaded_file($tmp_name)){
                        if(move_uploaded_file($tmp_name, $uploadDir.$filename)){
                            $imageFile = $filename;
                        }else{
                            $message = "อัปโหลดภาพไม่สำเร็จ: $filename";
                            $pdo->rollBack(); // สำคัญ!
                            break;
                        }
                    }else{
                        $message = "ไม่พบไฟล์ที่อัปโหลด: $filename";
                        $pdo->rollBack(); break;
                    }
                }
    
                // ดึง category_id จากชื่อประเภท
                $category_id = null;
                if (!empty($category)) {
                    $stmt_cat = $pdo->prepare("SELECT category_id FROM product_category WHERE category_name = ?");
                    $stmt_cat->execute([$category]);
                    $category_id = $stmt_cat->fetchColumn();
                }
    
                // ===== ตรวจสอบ/เพิ่มสินค้า
                $stmt = $pdo->prepare("SELECT product_id FROM products WHERE sku=? OR barcode=?");
                $stmt->execute([$sku, $barcode]);
                $product_id = $stmt->fetchColumn();
    
                if(!$product_id){
                    $stmt = $pdo->prepare("INSERT INTO products (name, sku, barcode, unit, product_category_id, category_name, image, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
                    $stmt->execute([$name, $sku, $barcode, $unit, $category_id, $category, 'images/'.$imageFile]);
                    $product_id = $pdo->lastInsertId();
                }
    
                // ===== ตรวจสอบ/เพิ่ม location
                $stmt = $pdo->prepare("SELECT location_id FROM locations WHERE row_code=? AND bin=? AND shelf=?");
                $stmt->execute([$row_code, $bin, $shelf]);
                $loc = $stmt->fetch();
                $location_id = $loc ? $loc['location_id'] : null;
                if(!$location_id){
                    $desc = "แถว $row_code ล็อค $bin ชั้น $shelf";
                    $stmt = $pdo->prepare("INSERT INTO locations (row_code, bin, shelf, description) VALUES (?,?,?,?)");
                    $stmt->execute([$row_code, $bin, $shelf, $desc]);
                    $location_id = $pdo->lastInsertId();
                }
    
                // ===== product_location
                $stmt = $pdo->prepare("SELECT 1 FROM product_location WHERE product_id=? AND location_id=?");
                $stmt->execute([$product_id, $location_id]);
                if(!$stmt->fetch()){
                    $stmt = $pdo->prepare("INSERT INTO product_location (product_id, location_id) VALUES (?,?)");
                    $stmt->execute([$product_id, $location_id]);
                }
    
                // ===== insert POItem
                $stmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, price_per_unit, total) VALUES (?,?,?,?,?)");
                $stmt->execute([$po_id, $product_id, $qty, $price, $qty*$price]);
                $item_id = $pdo->lastInsertId();
    
                // ===== insert รับเข้า
                $stmt = $pdo->prepare("INSERT INTO receive_items (receive_date, po_id, item_id, receive_qty, received_by, remark) VALUES (NOW(),?,?,?,?,?)");
                $stmt->execute([$po_id, $item_id, $qty, $user_id, 'imported']);
            }
            
            // ถ้าไม่มี error
            if(!$message){
                $pdo->commit();
                $message = "บันทึกข้อมูลสำเร็จ PO: <b>$po_number</b>";
            }
        } catch(Exception $e){
            $pdo->rollBack();
            $message = "เกิดข้อผิดพลาด: ".$e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<title>เพิ่มสินค้า</title>

<style>


    .table-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 18px #0001;
        padding: 20px;
        max-width: 1200px;
        margin: auto;
    }

    .table-card h2 {
        color: #fff;
        background: #2261ad;
        padding: 12px 20px;
        border-radius: 12px 12px 0 0;
        margin: -20px -20px 20px -20px;
    }

    .table-product {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 5px;
    }

    .table-product thead th {
        background: #e7edf7;
        color: #333;
        text-align: center;
        padding: 10px;
        border-bottom: 1px solid #d1d7e0;
    }

    .table-product tbody td {
        padding: 6px 10px;
    }

    .form-control {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid #d1d7e0;
        border-radius: 6px;
        font-size: 0.95rem;
    }

    .file-input {
        font-size: 0.85rem;
    }

    .add-row-btn, .save-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #2261ad;
        color: #fff;
        border: none;
        padding: 10px 16px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 0.95rem;
        margin-top: 10px;
    }

    .add-row-btn span.material-icons,
    .save-btn span.material-icons {
        vertical-align: middle;
    }

    .remove-row-btn {
        background: #fff;
        border: 1px solid #d1d7e0;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
    }

    .remove-row-btn span {
        color: #285599;
        font-size: 1.5em;
    }
</style>
<div class="mainwrap">
    <div class="topbar">
        เพิ่มสินค้าใหม่
    </div>
    <br>
<div class="table-card">
  <h2>เพิ่มรายการสินค้า</h2>
  <form method="post" enctype="multipart/form-data">
    <table class="table-product" id="items-table">
      <thead>
        <tr>
          <th>SKU</th><th>Barcode</th><th>ชื่อสินค้า</th><th>ประเภท</th><th>รูปภาพ<br><span style="font-size:0.8em;color:#3a945e;">(ไฟล์รูป)</span></th>
          <th>หน่วย</th><th>Row</th><th>Bin</th><th>Shelf</th>
          <th>จำนวน</th><th>ราคา/หน่วย</th><th>ลบ</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><input class="form-control" type="text" name="items[0][sku]"></td>
          <td><input class="form-control" type="text" name="items[0][barcode]"></td>
          <td><input class="form-control" type="text" name="items[0][name]" required></td>
          <td>
            <select class="form-control" name="items[0][category]">
              <option value="">-- เลือก --</option>
              <option value="อาหารเสริม">อาหารเสริม</option>
              <option value="เครื่องใช้ไฟฟ้า">เครื่องใช้ไฟฟ้า</option>
              <option value="เครื่องสำอาง/ความงาม">เครื่องสำอาง</option>
              <option value="สำหรับแม่และเด็ก">แม่และเด็ก</option>
              <option value="สัตว์เลี้ยง">สัตว์เลี้ยง</option>
              <option value="เครื่องใช้ในบ้าน/ออฟฟิศ">บ้าน/ออฟฟิศ</option>
            </select>
          </td>
          <td><input type="file" name="image" accept="image/*" capture="camera"></td>
          <td><input class="form-control" type="text" name="items[0][unit]"></td>
          <td>
              <select class="form-control" name="items[0][row_code]" required>
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
            <select class="form-control"  name="items[0][bin]" required>
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
            </select>
           </td>
          <td>

            <select class="form-control"  name="items[0][shelf]" required>
                <option value="">ชั้น</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
            </select>
            </td>
          <td><input class="form-control" type="number" min="1" name="items[0][qty]" required></td>
          <td><input class="form-control" type="number" min="0" step="0.01" name="items[0][price]" required></td>
          <td>
            <button type="button" class="remove-row-btn"><span class="material-icons">delete</span></button>
          </td>
        </tr>
      </tbody>
    </table>

            <button type="button" class="add-row-btn" id="add-item-btn">
            <span class="material-icons">add_circle</span> เพิ่มสินค้า
            </button>
            <button type="submit" name="submit" class="save-btn">
            <span class="material-icons">save_alt</span> บันทึกข้อมูล
            </button>

            <?php if(!empty($message)): ?>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <script>
                Swal.fire({
                icon: "<?= (strpos($message,'สำเร็จ')!==false) ? 'success':'error' ?>",
                title: "<?= (strpos($message,'สำเร็จ')!==false) ? 'สำเร็จ' : 'ผิดพลาด' ?>",
                html: '<?= addslashes($message) ?>',
                timer: 3200,
                showConfirmButton: false
                });
                </script>
                <?php endif; ?>

  </form>
</div>
</div>




<script>
$(document).ready(function(){
  let rowIdx = $('#items-table tbody tr').length;

  $('#add-item-btn').click(function(){
    let newRow = `<tr>
      <td><input class="form-control" type="text" name="items[${rowIdx}][sku]"></td>
      <td><input class="form-control" type="text" name="items[${rowIdx}][barcode]"></td>
      <td><input class="form-control" type="text" name="items[${rowIdx}][name]" required></td>
      <td><input type="file" name="image" accept="image/*" capture="camera"></td>
      <td><input class="form-control" type="text" name="items[${rowIdx}][unit]"></td>
      <td>
            <select class="form-control" name="items[${rowIdx}][row_code]" required>
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
                <option value="Sale">Sale</option>
            </select>
       </td>
       <td>
            <select class="form-control" name="items[${rowIdx}][bin]" required>
                <option value="">ล็อค</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
            </select>
       </td>

      <td>

           <select class="form-control" name="items[${rowIdx}][shelf]" required>
                <option value="">ชั้น</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
            </select>
      </td>
      <td><input class="form-control" type="number" min="1" name="items[${rowIdx}][qty]" required></td>
      <td><input class="form-control" type="number" min="0" step="0.01" name="items[${rowIdx}][price]" required></td>
      <td><button type="button" class="remove-row-btn"><span class="material-icons" style="color:#285599;font-size:1.5em;">delete</span></button></td>
    </tr>`;
    $('#items-table tbody').append(newRow);
    rowIdx++;
  });

  $('#items-table').on('click', '.remove-row-btn', function(){
    if($('#items-table tbody tr').length > 1){
      $(this).closest('tr').fadeOut(150,function(){ $(this).remove(); });
    }
  });
});



</script>

<!-- Scanner Modal -->
<div id="scannerModal" class="scanner-modal" style="display:none;">
  <div class="scanner-box">
    <video id="preview" playsinline autoplay></video>
    <div class="scanner-controls">
      <select id="cameraSelect" class="form-select"></select>
      <label style="color:#fff; display:flex;align-items:center;gap:4px">
        <input type="checkbox" id="mirrorToggle"> กลับภาพ
      </label>
      <span id="scanStatus" style="color:#9ee;min-width:120px;text-align:center;"></span>
      <button type="button" id="btnStop" class="btn btn-light">หยุด</button>
    </div>
  </div>
</div>
<script>
(function(){
  function ensureZXing(){
    return new Promise((resolve, reject) => {
      if (window.ZXing && window.ZXing.BrowserMultiFormatReader) return resolve();
      const tryLoad = (src) => {
        const s = document.createElement('script');
        s.src = src; s.async = true;
        s.onload = () => (window.ZXing ? resolve() : reject(new Error('ZXing not available after load')));
        s.onerror = () => reject(new Error('Failed to load ' + src));
        document.head.appendChild(s);
      };
      tryLoad('https://cdn.jsdelivr.net/npm/@zxing/library@0.20.0/umd/index.min.js');
      setTimeout(() => {
        if (!(window.ZXing && window.ZXing.BrowserMultiFormatReader)) {
          tryLoad('https://unpkg.com/@zxing/library@0.20.0/umd/index.min.js');
        }
      }, 1200);
    });
  }

  let codeReader = null;
  let currentDeviceId = null;
  let activeInput = null;
  const modal = document.getElementById('scannerModal');
  const video = document.getElementById('preview');
  const cameraSelect = document.getElementById('cameraSelect');
  const mirrorToggle = document.getElementById('mirrorToggle');
  const scanStatus = document.getElementById('scanStatus');

  const REQUIRED_HITS = 2;
  let lastCandidate = '';
  let hitCount = 0;

  function ean13ChecksumOk(code){
    if(!/^\d{13}$/.test(code)) return false;
    const digits = code.split('').map(d => +d);
    const check = digits.pop();
    const sum = digits.reduce((acc,d,i)=> acc + d * (i%2?3:1), 0);
    const calc = (10 - (sum % 10)) % 10;
    return calc === check;
  }
  function normalize(text){ return (text||'').trim(); }
  function acceptIfStable(result){
    const fmt = result.getBarcodeFormat && result.getBarcodeFormat();
    let txt = normalize(result.getText && result.getText());
    if(!txt) return false;
    if(fmt === ZXing.BarcodeFormat.EAN_13 && (!/^\d{13}$/.test(txt) || !ean13ChecksumOk(txt))){
      scanStatus && (scanStatus.textContent = 'กำลังตรวจสอบ… (EAN13 ไม่ผ่าน)');
      return false;
    }
    if (txt === lastCandidate) hitCount++; else { lastCandidate = txt; hitCount = 1; }
    scanStatus && (scanStatus.textContent = 'พบ: ' + txt + ' (' + hitCount + '/' + REQUIRED_HITS + ')');
    return hitCount >= REQUIRED_HITS ? txt : false;
  }

  function enhanceBarcodeInputs(root){
    const inputs = (root || document).querySelectorAll('input[name*="[barcode]"], input#barcode, input[name="barcode"], input[name="Barcode"]');
    inputs.forEach(inp => {
      if (inp.closest('.barcode-wrap')) return;
      const wrap = document.createElement('div');
      wrap.className = 'barcode-wrap';
      inp.parentNode.insertBefore(wrap, inp);
      wrap.appendChild(inp);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'scan-btn btn-scan';
      btn.title = 'สแกนด้วยกล้อง';
      btn.textContent = 'สแกน';
      wrap.appendChild(btn);
    });
  }

  async function listCameras(){
    try { await navigator.mediaDevices.getUserMedia({video:true}); } catch(e){}
    const devices = await navigator.mediaDevices.enumerateDevices();
    return devices.filter(d => d.kind === 'videoinput');
  }

  async function startReader(deviceId){
    await ensureZXing();
    if(!codeReader){
      const hints = new Map();
      hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.EAN_13, ZXing.BarcodeFormat.CODE_128]);
      codeReader = new ZXing.BrowserMultiFormatReader(hints);
    }
    try { codeReader.reset(); } catch(e){}
    currentDeviceId = deviceId;
    await codeReader.decodeFromVideoDevice(currentDeviceId, video, (result, err) => {
      if(result){
        const ok = acceptIfStable(result);
        if(ok !== false){
          if(activeInput){ activeInput.value = ok; activeInput.dispatchEvent(new Event('change')); }
          scanStatus && (scanStatus.textContent = '✓ ยืนยัน: ' + ok);
          stopScanner();
        }
      }
    });
  }

  async function openScanner(){
    await ensureZXing();
    modal.style.display = 'flex';
    lastCandidate=''; hitCount=0; if(scanStatus) scanStatus.textContent='กำลังสแกน…';
    video.classList.toggle('mirror', mirrorToggle.checked);

    try{
      const cams = await listCameras();
      if(!cams.length){ alert('ไม่พบกล้อง'); stopScanner(); return; }

      const prev = cameraSelect.value;
      cameraSelect.innerHTML = '';
      cams.forEach((d,i) => {
        const opt = document.createElement('option');
        opt.value = d.deviceId;
        opt.textContent = d.label || ('Camera ' + (i+1));
        cameraSelect.appendChild(opt);
      });
      const back = cams.find(d => /back|rear|environment/i.test(d.label));
      cameraSelect.value = prev && cams.some(c=>c.deviceId===prev) ? prev : (back ? back.deviceId : cams[0].deviceId);

      await startReader(cameraSelect.value);
    }catch(e){
      console.error(e);
      alert('เปิดกล้องไม่สำเร็จ: ' + (e.message || e));
      stopScanner();
    }
  }

  function stopScanner(){
    try{ codeReader && codeReader.reset(); }catch(e){}
    modal.style.display = 'none';
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.btn-scan');
    if(!btn) return;
    const wrap = btn.closest('.barcode-wrap');
    activeInput = wrap ? wrap.querySelector('input') : null;
    openScanner();
  });

  document.getElementById('btnStop').addEventListener('click', stopScanner);
  cameraSelect.addEventListener('change', async function(){
    try{ await startReader(this.value); }catch(e){ console.error(e); }
  });
  mirrorToggle.addEventListener('change', function(){
    video.classList.toggle('mirror', this.checked);
  });

  document.addEventListener('keydown', function(e){
    const tgt = e.target;
    if((tgt.matches && (tgt.matches('input[name*="[barcode]"]') || tgt.id==='barcode' || tgt.name==='barcode')) && e.key === 'Enter'){
      e.preventDefault();
    }
  });

  document.addEventListener('DOMContentLoaded', function(){
    enhanceBarcodeInputs(document);
    const observer = new MutationObserver(muts => {
      muts.forEach(m => m.addedNodes.forEach(node => {
        if (node.nodeType === 1) enhanceBarcodeInputs(node);
      }));
    });
    observer.observe(document.body, {childList: true, subtree: true});
  });
})();
</script>

</body>
</html>
