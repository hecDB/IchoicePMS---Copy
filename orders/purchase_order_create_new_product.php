<?php
session_start();
require_once '../config/db_connect.php';
require_once '../auth/auth_check.php';

// Check permission
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
if ($user_role !== 'admin' && $user_role !== 'manager') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access Denied - Admin or Manager role required';
    exit;
}

// Get suppliers for dropdown
$sql_suppliers = "SELECT supplier_id, name, phone, email, address FROM suppliers ORDER BY name";
$stmt_suppliers = $pdo->prepare($sql_suppliers);
$stmt_suppliers->execute();
$suppliers = $stmt_suppliers->fetchAll(PDO::FETCH_ASSOC);

// Get currencies
$sql_currencies = "SELECT currency_id, code, symbol,name , is_base, exchange_rate FROM currencies WHERE is_active = 1 ORDER BY is_base DESC, code";
$stmt_currencies = $pdo->prepare($sql_currencies);
$stmt_currencies->execute();
$currencies = $stmt_currencies->fetchAll(PDO::FETCH_ASSOC);

// Get product categories
$sql_categories = "SELECT category_id, category_name FROM product_category ORDER BY category_name";
$stmt_categories = $pdo->prepare($sql_categories);
$stmt_categories->execute();
$categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างใบ PO - สินค้าใหม่</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/purchase_order.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

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
  .modal-backdrop {position:fixed;top:0;left:0;width:100%;height:100%;background:#00000077;z-index:99;display:none;}
  .modal-backdrop.active {display:flex;align-items:center;justify-content:center;}
  .modal-popup {position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:12px;padding:25px;z-index:100;width:450px;max-width:90%;display:none;}
  .modal-popup.active {display:block;}
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

<body>
<div class="mainwrap">
    <?php include '../templates/sidebar.php'; ?>
    <div class="container">
        <h2>🆕 สร้างใบ PO - สินค้าใหม่</h2>
        <div class="desc">สำหรับการสั่งซื้อสินค้าที่ยังไม่มีในระบบ (ไม่มี SKU/Barcode)</div>
        
        <div class="success-message" id="successMsg"></div>
        <div class="error-message" id="errorMsg"></div>

        <form id="poNewProductForm" enctype="multipart/form-data">
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
                    <span class="product-list-title">📦 รายการสินค้า</span>
                    <button type="button" class="add-row-btn" id="addItemRowBtn">+ เพิ่มสินค้า</button>
                </div>
                <div id="itemsContainer" style="margin-top: 15px;"></div>
                <div style="margin-top:16px;text-align:right;border-top:1px solid #e5e6ee;padding-top:15px;">
                    <div id="subtotal" style="font-size:16px;font-weight:bold;margin-bottom:8px;">ราคาสุทธิ: ฿0.00</div>
                    <div id="total" style="font-size:18px;color:#1976d2;font-weight:bold;">ยอดรวมทั้งสิ้น: ฿0.00</div>
                </div>
            </div>

            <!-- ปุ่มส่ง -->
            <div style="margin-top:28px;display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" onclick="history.back()" class="submit-btn" style="background:#f6f7fd;color:#222;">ยกเลิก</button>
                <button type="submit" class="submit-btn">บันทึกใบ PO</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal เพิ่มผู้ขายใหม่ -->
<div class="modal-backdrop" id="supplierModalBackdrop"></div>
<div class="modal-popup" id="supplierModal">
    <div class="modal-header">
        <span class="modal-title">เพิ่มผู้ขายใหม่</span>
        <button type="button" class="modal-close" onclick="closeSupplierModal()">
            <span class="material-icons">close</span>
        </button>
    </div>
    <form id="supplierForm" autocomplete="off">
        <div style="margin-bottom:15px;">
            <label class="label">ชื่อผู้ขาย *</label>
            <input type="text" name="supplier_name" class="form-control" required placeholder="ชื่อบริษัทหรือร้านค้า">
        </div>
        <div style="margin-bottom:15px;">
            <label class="label">เบอร์ติดต่อ</label>
            <input type="text" name="supplier_phone" class="form-control" placeholder="หมายเลขโทรศัพท์">
        </div>
        <div style="margin-bottom:15px;">
            <label class="label">อีเมล</label>
            <input type="email" name="supplier_email" class="form-control" placeholder="อีเมลสำหรับติดต่อ">
        </div>
        <div style="margin-bottom:15px;">
            <label class="label">ที่อยู่</label>
            <textarea name="supplier_address" class="form-control" placeholder="ที่อยู่ของผู้ขาย" rows="3"></textarea>
        </div>
        <div id="supplierMsg" style="font-size:14px;color:#e53935;margin-bottom:15px;"></div>
        <div class="modal-actions">
            <button type="button" class="icon-btn" onclick="closeSupplierModal()">ยกเลิก</button>
            <button type="submit" class="icon-btn create-btn">เพิ่มผู้ขาย</button>
        </div>
    </form>
</div>

    <script>
        let itemCount = 0;
        const productCategories = <?php echo json_encode($categories, JSON_UNESCAPED_UNICODE); ?>;

        /* ==================== Modal ผู้ขาย ==================== */
        const modal = document.getElementById('supplierModal');
        const backdrop = document.getElementById('supplierModalBackdrop');
        const supplierSelect = document.getElementById('supplierSelect');
        const supplierForm = document.getElementById('supplierForm');
        const supplierMsg = document.getElementById('supplierMsg');

        function openSupplierModal() {
            modal.classList.add('active');
            backdrop.classList.add('active');
            supplierMsg.textContent = '';
            supplierForm.reset();
            setTimeout(() => { supplierForm.querySelector('input[name="supplier_name"]').focus(); }, 100);
        }

        function closeSupplierModal() {
            modal.classList.remove('active');
            backdrop.classList.remove('active');
            supplierMsg.textContent = '';
            supplierForm.reset();
        }

        // ปิด Modal เมื่อกด Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSupplierModal();
            }
        });

        // ปิด Modal เมื่อคลิก backdrop
        backdrop.addEventListener('click', closeSupplierModal);

        /* ==================== เพิ่มผู้ขายใหม่ (AJAX) ==================== */
        supplierForm.onsubmit = async function(e) {
            e.preventDefault();
            const data = new FormData(this);
            const supplierName = data.get('supplier_name');

            if (!supplierName.trim()) {
                supplierMsg.textContent = 'กรุณากรอกชื่อผู้ขาย';
                return;
            }

            try {
                let res = await fetch('../api/supplier_add_api.php', {method: 'POST', body: data});
                let json = await res.json();
                
                if (json.success) {
                    // เพิ่มเข้า select
                    let opt = document.createElement('option');
                    opt.value = json.supplier_id;
                    opt.textContent = supplierName;
                    supplierSelect.appendChild(opt);
                    supplierSelect.value = json.supplier_id;

                    closeSupplierModal();

                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: 'เพิ่มผู้ขายใหม่สำเร็จแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    supplierMsg.textContent = json.error || 'เกิดข้อผิดพลาด';
                }
            } catch (err) {
                supplierMsg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
                console.error(err);
            }
        };

        // ===== PO Number Generation =====
        async function generatePONumber() {
            try {
                const response = await fetch('../api/generate_po_new_number_api.php', {method: 'POST'});
                const result = await response.json();

                if (result.success) {
                    document.getElementById('po_number').value = result.po_number;
                    const statusDiv = document.getElementById('poStatus');
                    statusDiv.textContent = '✓ สร้าง PO Number สำเร็จ';
                    statusDiv.style.color = '#27ae60';
                    setTimeout(() => {statusDiv.textContent = '';}, 2000);
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', result.message || 'ไม่สามารถสร้างเลข PO ได้', 'error');
                }
            } catch (error) {
                Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
            }
        }

        // ===== Currency Handling =====
        document.getElementById('currencySelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const rate = parseFloat(selectedOption.dataset.rate) || 1;
            document.getElementById('exchangeRate').value = rate.toFixed(6);
            calculateTotal();
        });

        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('currencySelect').dispatchEvent(new Event('change'));
            addItemRow();
            calculateTotal();
        });

        // ===== Item Row Management =====
        function addItemRow() {
            itemCount++;
            const container = document.getElementById('itemsContainer');
            const imageUploadId = `imageUpload_${itemCount}`;
            const imagePreviewId = `imagePreview_${itemCount}`;

            let categoryOptions = '<option value="">-- เลือก --</option>';
            productCategories.forEach(cat => {
                categoryOptions += `<option value="${cat.category_name}">${cat.category_name}</option>`;
            });

            const itemDiv = document.createElement('div');
            itemDiv.className = 'product-item-card';
            itemDiv.id = `item-${itemCount}`;
            itemDiv.innerHTML = `
                <div style="display:flex;align-items:center;gap:15px;padding-bottom:10px;border-bottom:2px solid #e5e6ee;margin-bottom:10px;">
                    <span style="font-weight:bold;color:#495057;min-width:40px;">รายที่ ${itemCount}</span>
                    <div style="flex:1;height:70px;display:flex;align-items:center;gap:10px;border-right:1px solid #e5e6ee;padding-right:15px;">
                        <div class="product-image-container" style="width:70px;height:70px;border:1px solid #ddd;border-radius:6px;overflow:hidden;background:#f8f9fa;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <img class="product-image" id="${imagePreviewId}" src="../images/noimg.png" alt="สินค้า" style="width:100%;height:100%;object-fit:cover;display:none;">
                            <span class="no-image-text" style="font-size:10px;color:#666;text-align:center;">ไม่มี<br>รูป</span>
                        </div>
                        <input type="file" id="${imageUploadId}" name="product_image[]" accept="image/*" onchange="previewImage(this, '${imagePreviewId}')" style="display:none;">
                        <button type="button" class="add-row-btn" style="width:50px;padding:4px;font-size:11px;height:35px;" onclick="document.getElementById('${imageUploadId}').click()">
                            <span class="material-icons" style="font-size:12px;">add_photo_alternate</span>
                        </button>
                    </div>
                    <div style="flex:2;display:flex;align-items:center;gap:10px;">
                        <div style="flex:1;">
                            <label class="small-label">ชื่อสินค้า *</label>
                            <input type="text" name="product_name[]" placeholder="เช่น วิตามิน B12" required style="width:100%;">
                        </div>
                        <div style="flex:1;">
                            <label class="small-label">ประเภท *</label>
                            <select name="category[]" required style="width:100%;">
                                ${categoryOptions}
                            </select>
                        </div>
                    </div>
                    <div style="flex:1;display:flex;align-items:center;gap:10px;">
                        <div style="flex:1;">
                            <label class="small-label">หน่วยนับ *</label>
                            <input type="text" name="unit[]" value="ชิ้น" required style="width:100%;">
                        </div>
                        <div style="flex:1;">
                            <label class="small-label">จำนวน *</label>
                            <input type="number" name="quantity[]" step="0.01" required style="width:100%;" onchange="calculateTotal()">
                        </div>
                    </div>
                    <div style="flex:1;display:flex;align-items:center;gap:10px;">
                        <div style="flex:1;">
                            <label class="small-label">ราคา/หน่วย *</label>
                            <input type="number" name="unit_price[]" step="0.01" required style="width:100%;" onchange="calculateTotal()">
                        </div>
                        <button type="button" class="remove-row-btn" title="ลบ" style="margin-top:20px;font-size:24px;padding:0;width:40px;">&times;</button>
                    </div>
                </div>
            `;

            container.appendChild(itemDiv);
            itemDiv.querySelector('.remove-row-btn').onclick = () => {itemDiv.remove(); calculateTotal();};
        }

        function previewImage(input, imagePreviewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById(imagePreviewId);
                    img.src = e.target.result;
                    img.style.display = 'block';
                    img.parentElement.querySelector('.no-image-text').style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function calculateTotal() {
            let subtotal = 0;
            const quantities = document.querySelectorAll('input[name="quantity[]"]');
            const unitPrices = document.querySelectorAll('input[name="unit_price[]"]');
            const currencySymbol = document.querySelector('#currencySelect option:checked')?.getAttribute('data-symbol') || '฿';

            quantities.forEach((qty, index) => {
                const quantity = parseFloat(qty.value) || 0;
                const unitPrice = parseFloat(unitPrices[index].value) || 0;
                subtotal += quantity * unitPrice;
            });

            document.getElementById('subtotal').textContent = 'ราคาสุทธิ: ' + currencySymbol + ' ' + subtotal.toFixed(2);
            document.getElementById('total').textContent = 'ยอดรวมทั้งสิ้น: ' + currencySymbol + ' ' + subtotal.toFixed(2);
        }

        // ===== Form Submission =====
        document.getElementById('poNewProductForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const poNumber = document.getElementById('po_number').value.trim();
            if (!poNumber) {
                Swal.fire('เกิดข้อผิดพลาด', 'กรุณากรอกเลขที่ใบ PO', 'error');
                return;
            }

            const supplierId = document.getElementById('supplierSelect').value;
            if (!supplierId) {
                Swal.fire('เกิดข้อผิดพลาด', 'กรุณาเลือกซัพพลายเยอร์', 'error');
                return;
            }

            if (document.querySelectorAll('.product-item-card').length === 0) {
                Swal.fire('เกิดข้อผิดพลาด', 'กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ', 'error');
                return;
            }

            const imageInputs = document.querySelectorAll('input[name="product_image[]"]');
            const newFormData = new FormData(this);

            for (let key of newFormData.keys()) {
                if (key === 'product_image[]') {
                    newFormData.delete(key);
                }
            }

            const compressPromises = Array.from(imageInputs).map((input) => {
                return new Promise((resolve) => {
                    if (input.files && input.files[0]) {
                        const file = input.files[0];

                        if (file.size <= 500 * 1024) {
                            newFormData.append('product_image[]', file);
                            resolve();
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const img = new Image();
                            img.onload = function() {
                                const canvas = document.createElement('canvas');
                                let width = img.width;
                                let height = img.height;

                                const maxDim = 800;
                                if (width > height) {
                                    if (width > maxDim) {
                                        height = Math.round((height * maxDim) / width);
                                        width = maxDim;
                                    }
                                } else {
                                    if (height > maxDim) {
                                        width = Math.round((width * maxDim) / height);
                                        height = maxDim;
                                    }
                                }

                                canvas.width = width;
                                canvas.height = height;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(img, 0, 0, width, height);

                                canvas.toBlob((blob) => {
                                    const compressedFile = new File([blob], file.name, {
                                        type: 'image/jpeg',
                                        lastModified: file.lastModified
                                    });
                                    newFormData.append('product_image[]', compressedFile);
                                    resolve();
                                }, 'image/jpeg', 0.8);
                            };
                            img.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        newFormData.append('product_image[]', new File([], ''));
                        resolve();
                    }
                });
            });

            Promise.all(compressPromises).then(async () => {
                try {
                    const response = await fetch('../api/purchase_order_new_product_api.php', {
                        method: 'POST',
                        body: newFormData
                    });

                    const result = await response.json();

                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'บันทึกใบ PO สำเร็จ!',
                            html: 'PO Number: <b>' + result.po_number + '</b>',
                            confirmButtonText: 'ตกลง'
                        }).then(() => {
                            window.location.href = '../orders/purchase_orders.php';
                        });
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', result.message || 'ไม่สามารถบันทึกใบ PO ได้', 'error');
                    }
                } catch (error) {
                    Swal.fire('เกิดข้อผิดพลาด', 'เกิดข้อผิดพลาด: ' + error.message, 'error');
                }
            });
        });

        // Event listeners
        document.getElementById('addItemRowBtn').addEventListener('click', addItemRow);
        document.getElementById('openSupplierModal').addEventListener('click', openSupplierModal);
    </script>
</body>
</html>