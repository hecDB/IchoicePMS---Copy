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
$stmt2 = $pdo->query("SELECT product_id, name, sku, unit, image FROM products");
$products = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// ดึงสกุลเงิน
$stmt3 = $pdo->query("SELECT currency_id, code, name, symbol, exchange_rate FROM currencies WHERE is_active = 1 ORDER BY is_base DESC, code ASC");
$currencies = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// ดึงประเภทสินค้า
$stmt4 = $pdo->query("SELECT category_id, category_name FROM product_category ORDER BY category_name");
$categories = $stmt4->fetchAll(PDO::FETCH_ASSOC);
?>
<script>
window.allSuppliers = <?php echo json_encode($all_suppliers, JSON_UNESCAPED_UNICODE); ?>;
window.products = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;
window.currencies = <?php echo json_encode($currencies, JSON_UNESCAPED_UNICODE); ?>;
window.productCategories = <?php echo json_encode($categories, JSON_UNESCAPED_UNICODE); ?>;
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
  .container {max-width:1400px; margin:0 auto; padding:0 15px;}
  .card {background:#fff; border-radius:14px; border:1px solid #eaeaea; margin-bottom:20px; padding:30px; max-width:100%;}
  .form-grid {display:grid; grid-template-columns:1fr 1fr; gap:25px;}
  .submit-btn {background:#1976d2;color:#fff;border:none;border-radius:8px;padding:12px 27px;font-size:17px;font-weight:600;cursor:pointer;}
  .submit-btn:hover {background:#1257a5;}
  .add-row-btn {background:#1976d2;color:#fff;border-radius:6px;border:none;padding:9px 18px;font-size:16px;font-weight:600;cursor:pointer;}
  .remove-row-btn {color:#f23d3d;border:none;background:none;cursor:pointer;font-size:19px;}
  .grid-row {display:grid;grid-template-columns:1fr 2fr 1fr 1fr 1.5fr 0.5fr;gap:15px;}
  .small-label {font-size:15px;margin-bottom:5px;color:#9ca2b6;font-weight:500;}
  input, select, textarea {font-size:16px;border-radius:6px;border:1px solid #d7d8e0;padding:10px 12px;width:100%;}
  input:focus, textarea:focus {border:1.2px solid #226ecd;box-shadow:0 0 0 3px rgba(41, 128, 185, 0.1);}
  .product-item-card {background:#f9fafd;border:1px solid #e5e6ee;border-radius:11px;padding:20px;margin-bottom:18px;}
  .autocomplete-list {border:1px solid #d0d4e1;background:#fff;max-height:250px;overflow-y:auto;position:absolute;z-index:10;width:100%;left:0;top:45px;box-shadow:0 4px 12px #9aa8d477;border-radius:6px;display:none;}
  .autocomplete-list div {padding:10px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;}
  .autocomplete-list div:hover {background:#f3f8fe;}
  .autocomplete-list div:last-child {border-bottom:none;}
  .product-image-container {transition:all 0.3s ease;}
  .product-image {transition:opacity 0.3s ease;}
  .product-image-container:hover {border-color:#1976d2;}
  .autocomplete-list div {min-height:60px;align-items:center;}
  .autocomplete-list img {flex-shrink:0;}
  .modal-backdrop {position:fixed;top:0;left:0;width:100%;height:100%;background:#00000077;z-index:99;}
  .modal-popup {position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:12px;padding:25px;z-index:100;width:450px;max-width:90%;}
  .modal-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
  .modal-title {font-weight:600;font-size:18px;}
  .modal-actions {display:flex;justify-content:flex-end;gap:10px;margin-top:24px;}
  .icon-btn {cursor:pointer;padding:8px 16px;border:none;border-radius:6px;font-weight:600;background:#1976d2;color:#fff;transition:all 0.3s;}
  .icon-btn:hover {background:#1257a5;transform:translateY(-1px);}
  .create-btn {background:#43a047;}
  .create-btn:hover {background:#388e3c;}
  .modal-close {background:none;border:none;font-size:22px;cursor:pointer;color:#999;}
  label.label {display:block;margin-bottom:8px;font-weight:500;color:#2c3e50;font-size:14px;}
  .form-control {font-size:16px;border-radius:6px;border:1px solid #d7d8e0;padding:10px 12px;width:100%;background:#fff;}
  .form-control:focus {border-color:#226ecd;outline:none;box-shadow:0 0 0 3px rgba(41, 128, 185, 0.1);}
  .product-list-title {font-size:16px;font-weight:600;color:#2c3e50;}
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
          <div style="display:flex;align-items:flex-end;gap:10px;">
            <input type="text" id="po_number" name="po_number" placeholder="เช่น PO202410001" required style="flex:1;">
            <button type="button" class="add-row-btn" style="padding:8px 15px;font-size:12px;white-space:nowrap;" onclick="generatePONumber()" title="สร้างเลข PO อัตโนมัติ">
              <span class="material-icons" style="font-size:14px;vertical-align:middle;">refresh</span> สร้าง
            </button>
          </div>
          <div id="poStatus" style="font-size:12px;margin-top:5px;font-weight:bold;color:#666;"></div>
        </div>
        <div>
          <label class="label">วันที่สั่งซื้อ *</label>
          <input type="date" name="order_date" value="<?=date('Y-m-d')?>" required>
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

      <!-- สกุลเงิน -->
      <div class="form-grid" style="margin-top:15px;">
        <div>
          <label class="label">สกุลเงิน *</label>
          <select name="currency_id" id="currencySelect" required>
            <?php foreach($currencies as $curr): ?>
              <option value="<?=$curr['currency_id']?>" data-rate="<?=$curr['exchange_rate']?>" data-symbol="<?=$curr['symbol']?>" <?=$curr['code']=='THB'?'selected':''?>>
                <?=$curr['symbol']?> <?=$curr['name']?> (<?=$curr['code']?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label">อัตราแลกเปลี่ยน</label>
          <input type="number" id="exchangeRate" step="0.000001" readonly style="background:#f5f5f5;">
          <div class="small-label" style="color:#666; margin-top:2px;">1 หน่วยสกุลเงินที่เลือก = ? บาท</div>
        </div>
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
        <div id="totalPriceOriginal" style="font-size:18px;font-weight:bold;margin-bottom:5px;">ราคาสุทธิ: $0.00</div>
        <div id="totalPriceBase" style="font-size:14px;color:#666;">เทียบเท่า: ฿0.00</div>
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
    <label>เบอร์ติดต่อ</label>
    <input name="phone" type="text" class="form-control" placeholder="หมายเลขโทรศัพท์">
    <label>อีเมล</label>
    <input name="email" type="email" class="form-control" placeholder="อีเมลสำหรับติดต่อ">
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
        let res = await fetch('../api/supplier_add_api.php', {method:'POST', body:data});
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

/* ==================== สร้างเลข PO อัตโนมัติ ==================== */
async function generatePONumber() {
    try {
        const response = await fetch('../api/generate_po_number_api.php', {
            method: 'POST'
        });

        const result = await response.json();

        if (result.success) {
            document.getElementById('po_number').value = result.po_number;
            const statusDiv = document.getElementById('poStatus');
            statusDiv.textContent = '✓ สร้าง PO Number สำเร็จ';
            statusDiv.style.color = '#27ae60';
            setTimeout(() => {
                statusDiv.textContent = '';
            }, 2000);
        } else {
            alert('เกิดข้อผิดพลาด: ' + (result.message || 'ไม่สามารถสร้างเลข PO ได้'));
        }
    } catch (error) {
        alert('เกิดข้อผิดพลาด: ' + error.message);
    }
}

/* ==================== จัดการสกุลเงิน ==================== */
const currencySelect = document.getElementById('currencySelect');
const exchangeRateInput = document.getElementById('exchangeRate');

currencySelect.addEventListener('change', function(){
    const selectedOption = this.options[this.selectedIndex];
    const rate = parseFloat(selectedOption.dataset.rate) || 1;
    const symbol = selectedOption.dataset.symbol || '฿';
    
    exchangeRateInput.value = rate.toFixed(6);
    
    // อัปเดตสัญลักษณ์ในรายการสินค้า
    document.querySelectorAll('.currency-symbol').forEach(span => {
        span.textContent = symbol;
    });
    
    // อัปเดตการแสดงราคาในสกุลเงินฐานสำหรับรายการที่มีอยู่
    document.querySelectorAll('.price-input').forEach(priceInput => {
        updatePriceBaseDisplay(priceInput);
    });
    
    calcTotal();
});

// ตั้งค่าเริ่มต้น
currencySelect.dispatchEvent(new Event('change'));

/* ==================== รายการสินค้า ==================== */
let rowCount = 0;
const productRows = document.getElementById('productRows');

// สร้างแถวรายการสินค้าใหม่
function addProductRow(detail={}) {
    rowCount++;
    const idx = rowCount;
    
    // ดึงสัญลักษณ์สกุลเงินปัจจุบัน
    const currencySelect = document.getElementById('currencySelect');
    const selectedOption = currencySelect.options[currencySelect.selectedIndex];
    const currentSymbol = selectedOption.dataset.symbol || '฿';
    
    // สร้าง option สำหรับประเภทสินค้า
    let categoryOptions = '<option value="">-- เลือก --</option>';
    window.productCategories.forEach(cat => {
        categoryOptions += `<option value="${cat.category_name}">${cat.category_name}</option>`;
    });
    
    const row = document.createElement('div');
    row.className = "product-item-card";
    row.style.position = "relative";
    row.innerHTML = `
        <div style="font-weight:bold; margin-bottom:13px;">รายการที่ ${idx}
            <button type="button" class="remove-row-btn" title="ลบ" style="float:right;margin-top:0;">&times;</button>
        </div>
        <div class="grid-row improved-grid" style="align-items:flex-end;margin-bottom:0;">
            <div>
                <div class="small-label">รูปภาพ</div>
                <div class="product-image-container" style="width:70px;height:70px;border:1px solid #ddd;border-radius:6px;overflow:hidden;background:#f8f9fa;display:flex;align-items:center;justify-content:center;">
                    <img class="product-image" src="../images/noimg.png" alt="สินค้า" style="width:100%;height:100%;object-fit:cover;display:none;" onerror="this.style.display='none'; this.parentElement.querySelector('.no-image-text').style.display='block';">
                    <span class="no-image-text" style="font-size:10px;color:#666;text-align:center;">ไม่มี<br>รูป</span>
                </div>
            </div>
            <div>
                <div class="small-label">ชื่อสินค้า *</div>
                <div style="position:relative;">
                    <input type="text" class="product-name-input" placeholder="ค้นหาสินค้าด้วยชื่อ, SKU หรือหน่วย" autocomplete="off" required value="${detail.name||''}" style="width:100%;">
                    <div class="autocomplete-list"></div>
                </div>
            </div>
            <div>
                <div class="small-label">ประเภท</div>
                <select class="category-input" style="width:100%;padding:8px 10px;border:1px solid #d7d8e0;border-radius:6px;">
                    ${categoryOptions}
                </select>
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
                <div style="display:flex;align-items:center;">
                    <span class="currency-symbol" style="margin-right:5px;font-weight:bold;color:#1976d2;">${currentSymbol}</span>
                    <input type="number" class="price-input" min="0" step="0.01" value="${detail.price||0}" required style="width:95px;">
                </div>
                <div class="price-base-display" style="font-size:12px;color:#666;margin-top:2px;">≈ ฿0.00</div>
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
            `<div data-id="${p.product_id}" data-name="${p.name}" data-unit="${p.unit}" data-price="0" data-image="${p.image||''}" style="display:flex;align-items:center;padding:8px;">
                <img src="../${p.image ? p.image : 'images/noimg.png'}" alt="${p.name}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;margin-right:10px;border:1px solid #ddd;" onerror="this.src='../images/noimg.png'">
                <div><b>${p.name}</b><br><small style="color:#666;">${p.sku} | ${p.unit || 'ชิ้น'}</small></div>
            </div>`
        ).join('');
        autoList.style.display = "block";
    };

    // เลือกสินค้า
    autoList.onclick = function(e) {
        let t = e.target.closest('div[data-id]');
        if(!t) return;
        input.value = t.dataset.name;
        row.querySelector('.unit-input').value = t.dataset.unit;
        row.querySelector('.price-input').value = '0'; // ไม่มีราคาใน products table
        row.dataset.productId = t.dataset.id; // เก็บ product_id (แก้ไขจาก id เป็น id)
        
        // อัปเดตรูปภาพ
        const productImage = row.querySelector('.product-image');
        const noImageText = row.querySelector('.no-image-text');
        const imageData = t.dataset.image;
        
        console.log('Selected product:', {
            id: t.dataset.id,
            name: t.dataset.name,
            image: t.dataset.image
        });
        
        if (imageData && imageData !== '') {
            const testImg = new Image();
            testImg.onload = function() {
                productImage.src = '../' + imageData;
                productImage.style.display = 'block';
                noImageText.style.display = 'none';
            };
            testImg.onerror = function() {
                productImage.src = '../images/noimg.png';
                productImage.style.display = 'block';
                noImageText.style.display = 'none';
            };
            testImg.src = '../' + imageData;
        } else {
            productImage.src = '../images/noimg.png';
            productImage.style.display = 'block';
            noImageText.style.display = 'none';
        }
        
        autoList.innerHTML = "";
        autoList.style.display = "none";
        updatePriceBaseDisplay(row.querySelector('.price-input'));
        calcTotal();
    }

    // ปิด autocomplete เมื่อคลิกนอก
    document.addEventListener("mousedown", function ev(ev){
        if(!row.contains(ev.target)){
            autoList.innerHTML = "";
            autoList.style.display = "none";
        }
    });
    
    // รีเซ็ตรูปภาพเมื่อล้างชื่อสินค้า
    input.addEventListener('input', function() {
        if (this.value.trim() === '') {
            const productImage = row.querySelector('.product-image');
            const noImageText = row.querySelector('.no-image-text');
            productImage.style.display = 'none';
            noImageText.style.display = 'block';
            row.dataset.productId = '';
        }
    });

    // ปรับราคาสุทธิเมื่อเปลี่ยนจำนวนหรือราคา
    row.querySelector('.qty-input').onchange = calcTotal;
    row.querySelector('.price-input').onchange = function(){
        updatePriceBaseDisplay(this);
        calcTotal();
    };

    // ลบแถว
    row.querySelector('.remove-row-btn').onclick = ()=>{ row.remove(); calcTotal(); };

    productRows.appendChild(row);
    
    // อัปเดตการแสดงราคาในสกุลเงินฐานสำหรับแถวใหม่
    updatePriceBaseDisplay(row.querySelector('.price-input'));
    
    calcTotal();
}

// ปุ่มเพิ่มแถวสินค้า
document.getElementById('addProductRowBtn').onclick = ()=>addProductRow();

// อัปเดตการแสดงราคาในสกุลเงินฐาน
function updatePriceBaseDisplay(priceInput) {
    const row = priceInput.closest('.product-item-card');
    const priceBaseDisplay = row.querySelector('.price-base-display');
    const price = parseFloat(priceInput.value) || 0;
    const rate = parseFloat(exchangeRateInput.value) || 1;
    const priceBase = price * rate;
    priceBaseDisplay.textContent = `≈ ฿${priceBase.toFixed(2)}`;
}

// คำนวณราคาสุทธิ
function calcTotal(){
    let sumOriginal = 0;
    const rate = parseFloat(exchangeRateInput.value) || 1;
    const currencySelect = document.getElementById('currencySelect');
    const selectedOption = currencySelect.options[currencySelect.selectedIndex];
    const symbol = selectedOption.dataset.symbol || '฿';
    
    document.querySelectorAll('#productRows .product-item-card').forEach(card=>{
        let qty = parseFloat(card.querySelector('.qty-input').value)||0;
        let price = parseFloat(card.querySelector('.price-input').value)||0;
        sumOriginal += qty*price;
    });
    
    const sumBase = sumOriginal * rate;
    
    document.getElementById('totalPriceOriginal').textContent = `ราคาสุทธิ: ${symbol}${sumOriginal.toFixed(2)}`;
    document.getElementById('totalPriceBase').textContent = `เทียบเท่า: ฿${sumBase.toFixed(2)}`;
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
    const currencyId = document.getElementById('currencySelect').value;
    const exchangeRate = parseFloat(document.getElementById('exchangeRate').value) || 1;
    
    document.querySelectorAll('#productRows .product-item-card').forEach(card=>{
        const product_id = card.dataset.productId;
        const qty = parseFloat(card.querySelector('.qty-input').value) || 0;
        const unit = card.querySelector('.unit-input').value.trim();
        const priceOriginal = parseFloat(card.querySelector('.price-input').value) || 0;
        const priceBase = priceOriginal * exchangeRate;
        
        if(product_id && qty>0){
            orderItems.push({ 
                product_id, 
                qty, 
                unit, 
                price_original: priceOriginal,
                price_base: priceBase,
                currency_id: currencyId
            });
        }
    });

    if(orderItems.length === 0){
        Swal.fire('เกิดข้อผิดพลาด','กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ','error');
        return;
    }

    fd.append('orderItems', JSON.stringify(orderItems));
    fd.append('exchange_rate', exchangeRate);

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
