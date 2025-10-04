<?php
session_start();
include '../templates/sidebar.php';
include '../config/db_connect.php';

// ดึงรายชื่อผู้ขาย
$sql = "SELECT supplier_id, name, phone, email, address FROM suppliers ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// เตรียม array สำหรับ JS
$all_suppliers = [];
foreach ($suppliers as $s) {
    $all_suppliers[$s['supplier_id']] = [
        'name' => $s['name'],
        'phone' => $s['phone'],
        'email' => $s['email'],
        'address' => $s['address']
    ];
}

// ดึงสินค้า
$stmt2 = $pdo->query("SELECT product_id, name, sku, unit FROM products");
$products = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>
<script>
window.allSuppliers = <?php echo json_encode($all_suppliers, JSON_UNESCAPED_UNICODE); ?>;
window.products = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;
</script>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/purchase_order.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  .card {background:#fff; border-radius:14px; border:1px solid #eaeaea; margin-bottom:20px; padding:20px;}
  .form-grid {display:grid; grid-template-columns:1fr 1fr; gap:15px;}
  .submit-btn {background:#1976d2;color:#fff;border:none;border-radius:8px;padding:12px 27px;font-size:17px;font-weight:600;cursor:pointer;}
  .submit-btn:hover {background:#1257a5;}
  .add-row-btn {background:#1976d2;color:#fff;border-radius:6px;border:none;padding:9px 18px;font-size:16px;font-weight:600;cursor:pointer;}
  .remove-row-btn {color:#f23d3d;border:none;background:none;cursor:pointer;font-size:19px;}
  .grid-row {display:grid;grid-template-columns:2fr 90px 100px 130px 35px;gap:11px;}
  .small-label {font-size:15px;margin-bottom:2px;color:#9ca2b6;}
  input, select, textarea {font-size:16px;border-radius:6px;border:1px solid #d7d8e0;padding:8px 10px;width:100%;}
  input:focus, textarea:focus {border:1.2px solid #226ecd;}
  .product-item-card {background:#f9fafd;border:1px solid #e5e6ee;border-radius:11px;padding:18px;margin-bottom:18px;}
  .autocomplete-list {border:1px solid #d0d4e1;background:#fff;max-height:160px;overflow-y:auto;position:absolute;z-index:10;width:100%;left:0;top:36px;box-shadow:0 2px 8px #9aa8d455;border-radius:5px;display:none;}
  .autocomplete-list div {padding:7px 10px;cursor:pointer;}
  .autocomplete-list div:hover {background:#1976d2;color:#fff;}
  .modal-backdrop {position:fixed;top:0;left:0;width:100%;height:100%;background:#00000077;z-index:99;}
  .modal-popup {position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:12px;padding:20px;z-index:100;width:400px;max-width:90%;}
  .modal-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
  .modal-title {font-weight:600;font-size:18px;}
  .modal-actions {display:flex;justify-content:flex-end;gap:10px;}
  .icon-btn {cursor:pointer;padding:6px 12px;border:none;border-radius:6px;font-weight:600;background:#1976d2;color:#fff;}
  .create-btn {background:#43a047;}
  .modal-close {background:none;border:none;font-size:22px;cursor:pointer;}
</style>
</head>
<body>
<div class="mainwrap">
<form id="mainForm" autocomplete="off">
  <div class="container">

    <h2>สร้างใบสั่งซื้อใหม่</h2>
    <div class="desc">กรอกข้อมูลใบสั่งซื้อให้ครบถ้วน</div>

    <!-- ข้อมูลทั่วไป -->
    <div class="card">
      <div class="card-title">ข้อมูลทั่วไป</div>
      <div class="form-grid">
        <div>
          <label class="label">เลขที่ใบสั่งซื้อ (PO Number) *</label>
          <input type="text" name="po_number" placeholder="เช่น PO202410001" required>
          <!-- <div class="small-label" style="color:#666; margin-top:2px;">รูปแบบแนะนำ: POYYYYMMnnn (เช่น PO202410001)</div> -->
        </div>
        <div>
          <label class="label">วันที่สั่งซื้อ *</label>
          <input type="date" name="order_date" value="<?=date('Y-m-d')?>" required>
        </div>
      </div>
    </div>

    <!-- ผู้ขาย -->
    <div class="card">
      <div class="card-title">ข้อมูลผู้ขาย</div>
      <div style="display:flex;align-items:center;gap:7px;">
        <select class="form-control" name="supplier_id" id="supplierSelect" required>
          <option value="">เลือกผู้ขาย</option>
          <?php foreach($suppliers as $s): ?>
            <option value="<?=$s['supplier_id']?>"><?=htmlspecialchars($s['name'])?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="icon-btn add-btn" id="openSupplierModal">
          <span class="material-icons">add</span> 
        </button>
      </div>
      <div class="form-section" style="margin-top:10px">
        <label class="label">เบอร์ติดต่อ</label>
        <input type="text" id="supplierPhone" readonly>
        <label class="label">อีเมล</label>
        <input type="text" id="supplierEmail" readonly>
        <label class="label">ที่อยู่</label>
        <textarea id="supplierAddress" readonly></textarea>
      </div>
    </div>

    <!-- รายการสินค้า -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span class="product-list-title">รายการสินค้า</span>
        <button type="button" class="add-row-btn" id="addProductRowBtn">+ เพิ่มสินค้า</button>
      </div>
      <div id="productRows"></div>
      <div style="margin-top:16px;text-align:right;">
        <span id="totalPrice" style="font-size:18px;font-weight:bold;">ราคาสุทธิ: ฿0.00</span>
      </div>
    </div>

    <!-- ปุ่มบันทึก -->
    <div style="margin-top:28px;display:flex;gap:12px;justify-content:right;">
      <button type="button" onclick="window.location='purchase_orders.php'" class="submit-btn" style="background:#f6f7fd;color:#222;">ยกเลิก</button>
      <button type="submit" class="submit-btn">สร้างใบสั่งซื้อ</button>
    </div>

  </div>
</form>
</div>

<!-- Modal เพิ่มผู้ขาย -->
<div class="modal-backdrop" id="supplierModalBackdrop" style="display:none;"></div>
<div class="modal-popup" id="supplierModal" style="display:none;">
  <div class="modal-header">
    <div>
      <span class="modal-title">เพิ่มผู้ขายใหม่</span>
    </div>
    <button type="button" class="modal-close" id="closeSupplierModal"><span class="material-icons">close</span></button>
  </div>
  <form id="supplierForm" autocomplete="off">
    <label>ชื่อผู้ขาย *</label>
    <input name="name" type="text" class="form-control" required placeholder="ชื่อบริษัทหรือร้านค้า">
    <label>ผู้ติดต่อ</label>
    <input name="contact_name" type="text" class="form-control" placeholder="ชื่อผู้ติดต่อ">
    <label>เบอร์ติดต่อ</label>
    <input name="phone" type="text" class="form-control" placeholder="หมายเลขโทรศัพท์">
    <label>อีเมล</label>
    <input name="email" type="email" class="form-control" placeholder="อีเมลสำหรับติดต่อ">
    <label>เลขประจำตัวผู้เสียภาษี</label>
    <input name="tax_id" type="text" class="form-control" placeholder="เลขประจำตัวผู้เสียภาษี">
    <label>ที่อยู่</label>
    <textarea name="address" class="form-control" placeholder="ที่อยู่ของผู้ขาย"></textarea>
    <div class="modal-actions" style="margin-top:24px;">
      <button type="button" class="icon-btn" id="cancelSupplier">ยกเลิก</button>
      <button type="submit" class="icon-btn create-btn">เพิ่มผู้ขาย</button>
    </div>
    <div id="supplierMsg" style="font-size:15px;color:#e53935;margin-top:8px;"></div>
  </form>
</div>


<script>
/* ==================== Modal ผู้ขาย ==================== */
const modal = document.getElementById('supplierModal');
const backdrop = document.getElementById('supplierModalBackdrop');
const supplierSelect = document.getElementById('supplierSelect');
const supplierForm = document.getElementById('supplierForm');
const supplierMsg = document.getElementById('supplierMsg');

document.getElementById('openSupplierModal').onclick = () => {
    modal.style.display = backdrop.style.display = 'block';
    setTimeout(()=>{ modal.querySelector('input[name="name"]').focus(); }, 100);
};

function closeModal(){
    modal.style.display = backdrop.style.display = 'none';
    supplierMsg.textContent = "";
    supplierForm.reset();
}

document.getElementById('closeSupplierModal').onclick = closeModal;
document.getElementById('cancelSupplier').onclick = closeModal;
backdrop.onclick = closeModal;

/* ==================== เพิ่มผู้ขายใหม่ (AJAX) ==================== */
supplierForm.onsubmit = async function(e){
    e.preventDefault();
    const data = new FormData(this);

    try {
        let res = await fetch('supplier_add_api.php', {method:'POST', body:data});
        let json = await res.json();
        if(json.success){
            // เพิ่มเข้า select
            let opt = document.createElement('option');
            opt.value = json.supplier_id;
            opt.textContent = data.get('name');
            supplierSelect.appendChild(opt);
            supplierSelect.value = json.supplier_id;

            // เพิ่มเข้า allSuppliers
            window.allSuppliers[json.supplier_id] = {
                name: data.get('name'),
                phone: data.get('phone'),
                email: data.get('email'),
                address: data.get('address')
            };

            closeModal();
            supplierSelect.dispatchEvent(new Event('change')); // update details
        } else {
            supplierMsg.textContent = json.error || 'เกิดข้อผิดพลาด';
        }
    } catch(err){
        supplierMsg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
        console.error(err);
    }
};

/* ==================== แสดงรายละเอียดผู้ขาย ==================== */
supplierSelect.addEventListener('change', function(){
    const supid = this.value;
    const phone = document.getElementById('supplierPhone');
    const email = document.getElementById('supplierEmail');
    const address = document.getElementById('supplierAddress');

    if(window.allSuppliers[supid]){
        phone.value = window.allSuppliers[supid].phone || '';
        email.value = window.allSuppliers[supid].email || '';
        address.value = window.allSuppliers[supid].address || '';
    } else {
        phone.value = email.value = address.value = '';
    }
});

/* ==================== รายการสินค้า ==================== */
let rowCount = 0;
const productRows = document.getElementById('productRows');

// สร้างแถวรายการสินค้าใหม่
function addProductRow(detail={}) {
    rowCount++;
    const idx = rowCount;
    const row = document.createElement('div');
    row.className = "product-item-card";
    row.style.position = "relative";
    row.innerHTML = `
        <div style="font-weight:bold; margin-bottom:13px;">รายการที่ ${idx}
            <button type="button" class="remove-row-btn" title="ลบ" style="float:right;margin-top:0;">&times;</button>
        </div>
        <div class="grid-row improved-grid" style="align-items:flex-end;margin-bottom:0;">
            <div>
                <div class="small-label">ชื่อสินค้า *</div>
                <div style="position:relative;">
                    <input type="text" class="product-name-input" placeholder="ค้นหาสินค้าด้วยชื่อ, SKU หรือหน่วย" autocomplete="off" required value="${detail.name||''}" style="width:100%;">
                    <div class="autocomplete-list"></div>
                </div>
            </div>
            <div>
                <div class="small-label">จำนวน *</div>
                <input type="number" class="qty-input" min="1" value="${detail.qty||1}" required style="width:100px;">
            </div>
            <div>
                <div class="small-label">หน่วย *</div>
                <input type="text" class="unit-input" value="${detail.unit||''}" required readonly style="width:100px;">
            </div>
            <div>
                <div class="small-label">ราคาต่อหน่วย *</div>
                <input type="number" class="price-input" min="0" step="0.01" value="${detail.price||0}" required style="width:120px;">
            </div>
        </div>
    `;

    // เก็บ product_id
    row.dataset.productId = detail.product_id || '';

    const input = row.querySelector('.product-name-input');
    const autoList = row.querySelector('.autocomplete-list');

    // ฟังก์ชันแสดงรายการสินค้าเมื่อพิมพ์
    input.oninput = function() {
        const q = input.value.trim().toLowerCase();
        if(!q){
            autoList.innerHTML = "";
            autoList.style.display = "none";
            return;
        }
        const matches = window.products.filter(p =>
            (p.name && p.name.toLowerCase().includes(q)) ||
            (p.sku && p.sku.toLowerCase().includes(q)) ||
            (p.unit && p.unit.toLowerCase().includes(q))
        );
        if(matches.length === 0) {autoList.style.display='none'; autoList.innerHTML=''; return;}
        autoList.innerHTML = matches.map(p =>
            `<div data-id="${p.product_id}" data-name="${p.name}" data-unit="${p.unit}" data-price="${p.price||0}"><b>${p.name}</b> (${p.sku})</div>`
        ).join('');
        autoList.style.display = "block";
    };

    // เลือกสินค้า
    autoList.onclick = function(e) {
        let t = e.target.closest('div[data-id]');
        if(!t) return;
        input.value = t.dataset.name;
        row.querySelector('.unit-input').value = t.dataset.unit;
        row.querySelector('.price-input').value = t.dataset.price;
        row.dataset.productId = t.dataset.id; // เก็บ product_id
        autoList.innerHTML = "";
        autoList.style.display = "none";
        calcTotal();
    }

    // ปิด autocomplete เมื่อคลิกนอก
    document.addEventListener("mousedown", function ev(ev){
        if(!row.contains(ev.target)){
            autoList.innerHTML = "";
            autoList.style.display = "none";
        }
    });

    // ปรับราคาสุทธิเมื่อเปลี่ยนจำนวนหรือราคา
    row.querySelector('.qty-input').onchange = calcTotal;
    row.querySelector('.price-input').onchange = calcTotal;

    // ลบแถว
    row.querySelector('.remove-row-btn').onclick = ()=>{ row.remove(); calcTotal(); };

    productRows.appendChild(row);
    calcTotal();
}

// ปุ่มเพิ่มแถวสินค้า
document.getElementById('addProductRowBtn').onclick = ()=>addProductRow();

// คำนวณราคาสุทธิ
function calcTotal(){
    let sum = 0;
    document.querySelectorAll('#productRows .product-item-card').forEach(card=>{
        let qty = parseFloat(card.querySelector('.qty-input').value)||0;
        let price = parseFloat(card.querySelector('.price-input').value)||0;
        sum += qty*price;
    });
    document.getElementById('totalPrice').textContent = `ราคาสุทธิ: ฿${sum.toFixed(2)}`;
}

// เพิ่มแถวแรกอัตโนมัติ
addProductRow();

/* ==================== ตรวจสอบเลข PO ซ้ำ ==================== */
let poCheckTimeout;
const poInput = document.querySelector('input[name="po_number"]');
const poStatus = document.createElement('div');
poStatus.style.cssText = 'font-size:12px; margin-top:2px; font-weight:bold;';
poInput.parentNode.appendChild(poStatus);

poInput.addEventListener('input', function(){
    clearTimeout(poCheckTimeout);
    const poNumber = this.value.trim();
    
    if(!poNumber) {
        poStatus.textContent = '';
        return;
    }
    
    poStatus.textContent = 'กำลังตรวจสอบ...';
    poStatus.style.color = '#666';
    
    poCheckTimeout = setTimeout(async () => {
        try {
            const formData = new FormData();
            formData.append('check_po', poNumber);
            
            const response = await fetch('purchase_order_save.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if(result.exists) {
                poStatus.textContent = '⚠️ เลข PO นี้มีอยู่แล้ว';
                poStatus.style.color = '#f44336';
            } else {
                poStatus.textContent = '✓ เลข PO สามารถใช้ได้';
                poStatus.style.color = '#4caf50';
            }
        } catch(err) {
            poStatus.textContent = '';
        }
    }, 500);
});

/* ==================== บันทึกใบสั่งซื้อ (PO) ==================== */
document.getElementById('mainForm').onsubmit = async function(e){
    e.preventDefault();
    const fd = new FormData(this);

    // ตรวจสอบเลข PO
    const poNumber = document.querySelector('input[name="po_number"]').value.trim();
    if(!poNumber){
        Swal.fire('เกิดข้อผิดพลาด','กรุณากรอกเลขที่ใบสั่งซื้อ','error');
        return;
    }
    
    // ตรวจสอบรูปแบบเลข PO (ไม่บังคับ แต่แนะนำ)
    if(!/^[A-Za-z0-9\-_]+$/.test(poNumber)){
        const confirm = await Swal.fire({
            title: 'รูปแบบเลข PO',
            text: 'เลข PO ควรประกอบด้วยตัวอักษรและตัวเลขเท่านั้น ต้องการดำเนินการต่อหรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ดำเนินการต่อ',
            cancelButtonText: 'ยกเลิก'
        });
        if(!confirm.isConfirmed) return;
    }

    // ตรวจสอบผู้ขาย
    const supplierId = document.querySelector('#supplierSelect').value;
    if(!supplierId){
        Swal.fire('เกิดข้อผิดพลาด','กรุณาเลือกผู้ขาย','error');
        return;
    }

    // รวมรายการสินค้า
    let orderItems = [];
    document.querySelectorAll('#productRows .product-item-card').forEach(card=>{
        const product_id = card.dataset.productId;
        const qty = parseFloat(card.querySelector('.qty-input').value) || 0;
        const unit = card.querySelector('.unit-input').value.trim();
        const price = parseFloat(card.querySelector('.price-input').value) || 0;
        if(product_id && qty>0){
            orderItems.push({ product_id, qty, unit, price });
        }
    });

    if(orderItems.length === 0){
        Swal.fire('เกิดข้อผิดพลาด','กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ','error');
        return;
    }

    fd.append('orderItems', JSON.stringify(orderItems));

    // ส่งไป PHP
    try {
        let res = await fetch('purchase_order_save.php', {method:'POST', body:fd});
        let json = await res.json();
        if(json.success){
            Swal.fire({
                icon:'success',
                title:'บันทึกใบสั่งซื้อเรียบร้อย!',
                html:'เลขที่ใบสั่งซื้อ: <b>'+json.po_number+'</b>',
                confirmButtonText:'ตกลง'
            }).then(()=>{ window.location='purchase_orders.php'; });
        } else {
            Swal.fire({
                icon:'error',
                title:'เกิดข้อผิดพลาด',
                text: json.error || 'ไม่สามารถบันทึกใบสั่งซื้อได้',
                confirmButtonText:'ตกลง'
            });
        }
    } catch(err){
        Swal.fire('เกิดข้อผิดพลาด','ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้','error');
        console.error(err);
    }
};


</script>

</body>
</html>
