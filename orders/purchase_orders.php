<?php

//  ทดสอบ
session_start();
include '../templates/sidebar.php';
// --- ดึงใบสั่งซื้อกับชื่อ supplier ---
$sql = "SELECT po.po_id, po.po_number, po.order_date, po.total_amount, po.status,
            s.name
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        ORDER BY po.po_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบสั่งซื้อ (Purchase Orders)</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        /* Popup Background */
        .popup-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
            display: none; /* Hidden by default */
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .popup-bg.active {
            display: flex;
            opacity: 1;
        }
        
        /* Popup Inner */
        .popup-inner {
            background: #fff;
            padding: 30px;
            width: 95%;
            max-width: 1400px;
            max-height: 90vh;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            position: relative;
            transform: translateY(20px);
            transition: transform 0.3s ease;
            overflow-y: auto;
        }
        
        .popup-bg.active .popup-inner {
            transform: translateY(0);
        }
        
        /* Close Button */
        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            background: #f5f5f5;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            color: #666;
            transition: all 0.2s ease;
        }
        
        .close-btn:hover {
            background: #e0e0e0;
            color: #333;
        }
        
        /* Edit Popup Specific */
        #poEditPopup .popup-content {
            background: #fff;
            padding: 30px;
            width: 95%;
            max-width: 1400px;
            max-height: 90vh;
            margin: 30px auto;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            position: relative;
            transform: translateY(20px);
            transition: transform 0.3s ease;
            overflow-y: auto;
        }
        
        #poEditPopup.active .popup-content {
            transform: translateY(0);
        }
        
        /* Global SweetAlert2 z-index override */
        .swal2-container {
            z-index: 99999 !important;
        }
        .swal2-backdrop-show {
            z-index: 99998 !important;
        }
        
        /* Ensure SweetAlert2 is always on top of our popups */
        .swal2-shown > .swal2-container {
            z-index: 100000 !important;
        }
        
        /* Autocomplete Styles */
        .autocomplete-container {
            position: relative;
            width: 100%;
        }
        
        .autocomplete-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            background-color: #fff;
        }
        
        .autocomplete-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 10001;
            display: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .autocomplete-dropdown.show {
            display: block;
        }
        
        .autocomplete-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f1f1;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        .autocomplete-item:hover,
        .autocomplete-item.selected {
            background-color: #f8f9fa;
        }
        
        .autocomplete-item-name {
            font-weight: 500;
            color: #333;
        }
        
        .autocomplete-item-details {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        
        .autocomplete-loading {
            padding: 10px 12px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        
        .autocomplete-no-results {
            padding: 10px 12px;
            text-align: center;
            color: #999;
            font-style: italic;
        }
        
        /* Inline edit mode autocomplete adjustments */
        .item-edit-mode .autocomplete-container {
            position: relative;
            width: 100%;
        }
        
        .item-edit-mode .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 10005; /* Higher than table z-index */
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 150px;
            overflow-y: auto;
        }
        
        /* Make sure table cells don't clip the dropdown */
        .item-edit-mode .po-items-table td {
            position: relative;
            overflow: visible;
        }
    </style>
</head>
<body>
    <div class="mainwrap">
        <div class="topbar">
            ใบสั่งซื้อ (Purchase Orders)
        </div>
        <div class="main">
            <div class="action-bar" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; color: #333;">รายการใบสั่งซื้อทั้งหมด</h2>
                <!-- <div style="display: flex; gap: 10px;">
                    <a href="../orders/purchase_order_create.php" class="btn btn-primary" style="text-decoration: none;">
                        <span class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:5px;">add</span>
                        สร้าง PO - สินค้าเดิม
                    </a>
                    <a href="../orders/purchase_order_create_new_product.php" class="btn btn-primary" style="text-decoration: none; background-color: #27ae60;">
                        <span class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:5px;">add_circle</span>
                        สร้าง PO - สินค้าใหม่
                    </a>
                </div> -->
            </div>

            <div class="card table-card mt-3">
                <div class="table-responsive">
                    <table id="po-table" class="table-po table table-bordered table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>เลขที่ใบสั่งซื้อ</th>
                                <th>ผู้จำหน่าย</th>
                                <th>วันที่สั่งซื้อ</th>
                                <th>จำนวนเงิน</th>
                                <th>สถานะ</th>
                                <th>การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($rows as $r): ?>
                            <tr>
                                <td><?=htmlspecialchars($r['po_number'])?></td>
                                <td><?=htmlspecialchars($r['name'])?></td>
                                <td><?=date('d/m/Y', strtotime($r['order_date']))?></td>
                                <td><?=number_format($r['total_amount'],2)?></td>
                                <td>
                                    <?php
                                        switch($r['status']) {
                                        case 'pending':   echo '<span class="status-chip status-pending">รอดำเนินการ</span>'; break;
                                        case 'partial':   echo '<span class="status-chip status-approved">รับของบางส่วน</span>'; break;
                                        case 'completed': echo '<span class="status-chip status-received">เสร็จสิ้น</span>'; break;
                                        case 'cancel':    echo '<span class="status-chip status-cancel">ยกเลิก</span>'; break;
                                        }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a class="icon-btn view" href="#" data-po="<?=$r['po_id']?>" onclick="openPoView(event, this)">
                                            <span class="material-icons">visibility</span> ดู
                                        </a>
                                        <a class="icon-btn delete" href="#" onclick="deletePo(event, <?= $r['po_id'] ?>)">
                                            <span class="material-icons">delete</span> ลบ
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Popup -->
    <div id="poViewPopup" class="popup-bg">
        <div class="popup-inner" onclick="event.stopPropagation();">
            <button class="close-btn" onclick="closePoView(event)" title="ปิด">
                <span class="material-icons" style="font-size:18px;">close</span>
            </button>
            <div id="poViewContent">Loading...</div>
        </div>
    </div>

    <!-- Edit Popup -->
    <div id="poEditPopup" class="popup-bg">
        <div class="popup-inner" onclick="event.stopPropagation();">
            <button class="close-btn" onclick="closePoEdit(event)" title="ปิด">
                <span class="material-icons" style="font-size:18px;">close</span>
            </button>
            <div id="poEditContent">Loading...</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
    $(function(){
        $('#po-table').DataTable({
            "order": [[0, "desc"]],
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" },
            "pageLength": 25
        });
    });

    function resolveItemImage(imageValue) {
        if (!imageValue || typeof imageValue !== 'string') {
            return { src: null, needsFallback: false };
        }

        const trimmed = imageValue.trim();
        if (!trimmed) {
            return { src: null, needsFallback: false };
        }

        if (trimmed.startsWith('data:')) {
            return { src: trimmed, needsFallback: false };
        }

        const sanitized = trimmed.replace(/\s+/g, '');
        const base64Pattern = /^[A-Za-z0-9+/]+={0,2}$/;
        if (sanitized.length >= 60 && sanitized.length % 4 === 0 && base64Pattern.test(sanitized)) {
            return { src: `data:image/jpeg;base64,${sanitized}`, needsFallback: false };
        }

        if (/^https?:\/\//i.test(trimmed) || trimmed.startsWith('../') || trimmed.startsWith('/')) {
            return { src: trimmed, needsFallback: true };
        }

        if (trimmed.startsWith('images/')) {
            return { src: `../${trimmed}`, needsFallback: true };
        }

        if (!trimmed.includes('/')) {
            return { src: `../images/${trimmed}`, needsFallback: true };
        }

        return { src: `../${trimmed}`, needsFallback: true };
    }

    function deletePo(e, po_id) {
        e.preventDefault();
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: `คุณต้องการลบใบสั่งซื้อ ID: ${po_id} ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'กำลังลบ...',
                    text: 'กรุณารอสักครู่',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch('../orders/purchase_order_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ po_id: po_id })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('ลบแล้ว!', 'ใบสั่งซื้อถูกลบเรียบร้อย', 'success').then(() => {
                            const row = $(`a[data-po="${po_id}"]`).closest('tr');
                            if(row.length) {
                                $('#po-table').DataTable().row(row).remove().draw();
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถลบใบสั่งซื้อได้: ' + (data.message || 'ข้อผิดพลาดที่ไม่รู้จัก'), 'error');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    Swal.fire('เกิดข้อผิดพลาด!', 'เกิดข้อผิดพลาดในการสื่อสารกับเซิร์ฟเวอร์', 'error');
                });
            }
        });
    }

    // ===================== Popup Handling =====================
    let poViewKeyDownHandler = null;
    let poEditKeyDownHandler = null;

    function closePoView(e) {
        if (e) e.preventDefault();
        const popup = document.getElementById('poViewPopup');
        if (!popup) return;
        
        popup.classList.remove('active');
        document.body.style.overflow = 'auto';
        
        if (poViewKeyDownHandler) {
            document.removeEventListener('keydown', poViewKeyDownHandler);
            poViewKeyDownHandler = null;
        }
    }

    function closePoEdit(e) {
        if (e) e.preventDefault();
        const popup = document.getElementById('poEditPopup');
        if (!popup) return;
        
        popup.classList.remove('active');
        document.body.style.overflow = 'auto';
        
        if (poEditKeyDownHandler) {
            document.removeEventListener('keydown', poEditKeyDownHandler);
            poEditKeyDownHandler = null;
        }
        
        // Refresh the page to show updated data
        setTimeout(() => {
            location.reload();
        }, 300);
    }

    // ===================== View PO =====================
    function openPoView(e, el) {
        e.preventDefault();
        const po_id = el.getAttribute('data-po');
        const popup = document.getElementById('poViewPopup');
        const content = document.getElementById('poViewContent');
        
        content.innerHTML = `
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:200px;">
                <div class="spinner" style="width:40px;height:40px;border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:15px;"></div>
                <div>กำลังโหลดข้อมูล...</div>
            </div>
            <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
        `;
        
        popup.classList.add('active');
        document.body.style.overflow = 'hidden';

        poViewKeyDownHandler = (evt) => {
            if (evt.key === 'Escape') closePoView();
        };
        document.addEventListener('keydown', poViewKeyDownHandler);

        popup.addEventListener('click', function(event) {
            if (event.target === popup) {
                closePoView();
            }
        }, { once: true });

        fetch('../api/purchase_order_api.php?id=' + po_id)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                renderPoView(data);
            })
            .catch(err => {
                console.error('Error loading PO:', err);
                content.innerHTML = `
                    <div style="padding:30px;text-align:center;color:#dc3545;">
                        <span class="material-icons" style="font-size:48px;color:#dc3545;margin-bottom:15px;">error_outline</span>
                        <h3 style="margin-top:0;color:inherit;">เกิดข้อผิดพลาด</h3>
                        <p>ไม่สามารถโหลดข้อมูลได้: ${err.message}</p>
                        <div style="margin-top:20px;">
                            <button onclick="openPoView(event, document.querySelector('[data-po=\\'${po_id}\\']'))" class="btn btn-primary" style="margin-right:10px;">ลองใหม่</button>
                            <button onclick="closePoView()" class="btn btn-secondary">ปิด</button>
                        </div>
                    </div>
                `;
            });
    }

    function renderPoView(data) {
        const content = document.getElementById('poViewContent');
        
        if (!data || !data.order) {
            content.innerHTML = `
                <div style="padding:30px;text-align:center;color:#6c757d;">
                    <span class="material-icons" style="font-size:48px;margin-bottom:15px;">info_outline</span>
                    <h3 style="margin-top:0;color:inherit;">ไม่พบข้อมูล</h3>
                    <p>ไม่พบข้อมูลใบสั่งซื้อที่ระบุ</p>
                    <button onclick="closePoView()" class="btn btn-secondary" style="margin-top:15px;">ปิด</button>
                </div>
            `;
            return;
        }

        // Reset edit mode flag when rendering new view
        isEditingItems = false;

        // Store current data globally for editing functions
        currentPoData = data;

        const formattedDate = new Date(data.order.order_date).toLocaleDateString('th-TH', {
            year: 'numeric', month: 'long', day: 'numeric', weekday: 'long'
        });

        // Currency formatting based on order currency
        const currencySymbol = data.order.currency_symbol || '฿';
        const exchangeRate = parseFloat(data.order.exchange_rate || 1);
        
        const formatCurrency = (amount, isOriginal = false) => {
            const value = parseFloat(amount || 0);
            if (isOriginal && data.order.currency_code !== 'THB') {
                return value.toLocaleString('th-TH', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
            }
            return value.toLocaleString('th-TH', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        };

        let itemsHtml = data.items && data.items.length > 0 ? data.items.map((item, index) => {
            const qty = parseFloat(item.qty || 0);
            // ใช้ price_original ถ้ามีค่า (รวมทั้ง 0) ถ้าไม่มี ใช้ price_per_unit
            const priceOriginal = (item.price_original !== null && item.price_original !== undefined) ? parseFloat(item.price_original) : parseFloat(item.price_per_unit || 0);
            const priceBase = (item.price_base !== null && item.price_base !== undefined) ? parseFloat(item.price_base) : parseFloat(item.price_per_unit || 0);
            const total = parseFloat(item.total || (qty * priceBase));
            // If stored price equals total (common when unit price saved as total), derive unit price from total/qty
            const displayPriceBase = (qty > 0 && total > 0 && Math.abs(priceBase - total) < 0.0001) ? (total / qty) : priceBase;
            const displayPriceOriginal = (qty > 0 && total > 0 && Math.abs(priceOriginal - total) < 0.0001) ? (total / qty) : priceOriginal;
            const itemCurrencySymbol = item.item_currency_symbol || currencySymbol;
            
            // Debug: แสดงค่าที่ดึงมา
            console.log(`Item ${index + 1}: qty=${qty}, price_original=${item.price_original}, priceOriginal=${priceOriginal}, price_base=${item.price_base}, priceBase=${priceBase}, total=${total}`);
            
            // Handle image display - check if it's Base64 (temp_products) or file path (regular products)
            let imageHtml = '';
            const imageInfo = resolveItemImage(item.image);
            if (imageInfo.src) {
                const errorAttr = imageInfo.needsFallback ? " onerror=\"this.src='../images/noimg.png'\"" : '';
                imageHtml = `<img src="${imageInfo.src}" alt="${item.product_name || 'Product'}" style="width:50px;height:50px;object-fit:cover;border-radius:4px;border:1px solid #ddd;"${errorAttr}>`;
            } else {
                imageHtml = `<div style="width:50px;height:50px;background:#f8f9fa;border:1px solid #ddd;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#666;font-size:10px;">ไม่มี<br>รูป</div>`;
            }
            
            return `
            <tr>
                <td style="text-align:center;">${index + 1}</td>
                <td style="text-align:center;">
                    ${imageHtml}
                </td>
                <td>
                    ${item.product_name || 'ไม่ระบุชื่อสินค้า'}
                </td>
                <td>${item.sku || '-'}</td>
                <td style="text-align:right;">${qty.toLocaleString()}</td>
                <td>${item.unit || 'ชิ้น'}</td>
                <td style="text-align:right;">
                    ${data.order.currency_code !== 'THB' ? 
                        `<div>${itemCurrencySymbol}${formatCurrency(displayPriceOriginal, true)}</div><div style="font-size:11px;color:#666;">≈ ฿${formatCurrency(displayPriceBase)}</div>` : 
                        `฿${formatCurrency(displayPriceBase)}`
                    }
                </td>
                <td style="text-align:right;">
                    ${data.order.currency_code !== 'THB' ? 
                        `<div>${itemCurrencySymbol}${formatCurrency(qty * displayPriceOriginal, true)}</div><div style="font-size:11px;color:#666;">≈ ฿${formatCurrency(total)}</div>` : 
                        `฿${formatCurrency(total)}`
                    }
                </td>
            </tr>`;
        }).join('') : `<tr><td colspan="8" style="text-align:center;padding:20px;color:#6c757d;">ไม่พบรายการสินค้า</td></tr>`;

        const subtotalOriginal = data.items ? data.items.reduce((sum, item) => {
            const qty = parseFloat(item.qty || 0);
            const priceOriginal = (item.price_original !== null && item.price_original !== undefined) ? parseFloat(item.price_original) : parseFloat(item.price_per_unit || 0);
            const total = parseFloat(item.total || 0);
            const unit = (qty > 0 && total > 0 && Math.abs(priceOriginal - total) < 0.0001) ? (total / qty) : priceOriginal;
            return sum + (qty * unit);
        }, 0) : 0;
        
        const subtotalBase = data.items ? data.items.reduce((sum, item) => {
            const qty = parseFloat(item.qty || 0);
            const priceBase = (item.price_base !== null && item.price_base !== undefined) ? parseFloat(item.price_base) : parseFloat(item.price_per_unit || 0);
            const total = parseFloat(item.total || (qty * priceBase));
            const unit = (qty > 0 && total > 0 && Math.abs(priceBase - total) < 0.0001) ? (total / qty) : priceBase;
            return sum + (qty * unit);
        }, 0) : 0;

        content.innerHTML = `
            <div class="po-header">
                <h3>รายละเอียดใบสั่งซื้อ</h3>
                <div class="po-meta">
                    <div class="po-meta-item"><span class="meta-label">เลขที่ใบสั่งซื้อ</span><span class="meta-value">${data.order.po_number || '-'}</span></div>
                    <div class="po-meta-item"><span class="meta-label">วันที่สั่งซื้อ</span><span class="meta-value">${formattedDate}</span></div>
                    <div class="po-meta-item"><span class="meta-label">สถานะ</span><span class="status-badge ${data.order.status || 'pending'}">${getStatusText(data.order.status || 'pending')}</span></div>
                    <div class="po-meta-item"><span class="meta-label">สกุลเงิน</span><span class="meta-value">${currencySymbol} ${data.order.currency_name || 'Thai Baht'}</span></div>
                    ${exchangeRate !== 1 ? `<div class="po-meta-item"><span class="meta-label">อัตราแลกเปลี่ยน</span><span class="meta-value">1 ${data.order.currency_code} = ${exchangeRate.toFixed(6)} ฿</span></div>` : ''}
                </div>
            </div>
            <div class="po-sections">
                <div class="po-section">
                    <div class="section-header">
                        <h4><i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:5px;">person</i> ข้อมูลผู้สั่งซื้อ</h4>
                        <button class="btn-edit-section btn-sm" onclick="editUserSection(${data.order.po_id})" title="แก้ไขข้อมูลผู้สั่งซื้อ">
                            <i class="material-icons" style="font-size:14px;">edit</i>
                        </button>
                    </div>
                    <div class="section-content" id="user-section">
                        <p><strong>${data.user.name || '-'}</strong></p>
                        ${data.user.department ? `<p>แผนก: ${data.user.department}</p>` : ''}
                    </div>
                </div>
                <div class="po-section">
                    <div class="section-header">
                        <h4><i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:5px;">store</i> ข้อมูลผู้จำหน่าย</h4>
                        <button class="btn-edit-section btn-sm" onclick="editSupplierSection(${data.order.po_id})" title="แก้ไขข้อมูลผู้จำหน่าย">
                            <i class="material-icons" style="font-size:14px;">edit</i>
                        </button>
                    </div>
                    <div class="section-content" id="supplier-section">
                        <p><strong>${data.supplier.name || '-'}</strong></p>
                        ${data.supplier.phone || data.supplier.email ? `<p>${data.supplier.phone ? 'โทร: ' + data.supplier.phone : ''} ${data.supplier.phone && data.supplier.email ? ' | ' : ''}${data.supplier.email ? 'อีเมล: ' + data.supplier.email : ''}</p>` : ''}
                        ${data.supplier.address ? `<p>ที่อยู่: ${data.supplier.address}</p>` : ''}
                    </div>
                </div>
            </div>
            <div class="po-section">
                <div class="section-header">
                    <h4 style="margin:0;"><i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:5px;">shopping_cart</i> รายการสินค้า</h4>
                    <div class="section-actions">
                        <button onclick="editItemsInline(${data.order.po_id})" class="btn-edit-section btn-sm" title="แก้ไขรายการสินค้า">
                            <i class="material-icons" style="font-size:14px;">edit</i> แก้ไขรายการ
                        </button>
                        <button onclick="addNewItem(${data.order.po_id})" class="btn-add-item btn-sm" title="เพิ่มสินค้าใหม่">
                            <i class="material-icons" style="font-size:14px;">add</i> เพิ่มสินค้า
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="po-items-table">
                        <thead>
                            <tr>
                                <th width="50px" style="text-align:center;">ลำดับ</th>
                                <th width="80px" style="text-align:center;">รูปภาพ</th>
                                <th>ชื่อสินค้า</th>
                                <th width="100px">SKU</th>
                                <th width="100px" style="text-align:right;">จำนวน</th>
                                <th width="80px">หน่วย</th>
                                <th width="120px" style="text-align:right;">ราคา/หน่วย</th>
                                <th width="120px" style="text-align:right;">รวม</th>
                            </tr>
                        </thead>
                        <tbody>${itemsHtml}</tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7" style="text-align:right;font-weight:bold;border-top:1px solid #dee2e6;">รวมทั้งสิ้น</td>
                                <td style="text-align:right;font-weight:bold;border-top:1px solid #dee2e6;">
                                    ${data.order.currency_code !== 'THB' ? 
                                        `<div>${currencySymbol}${formatCurrency(subtotalOriginal, true)}</div><div style="font-size:12px;color:#666;font-weight:normal;">≈ ฿${formatCurrency(subtotalBase)} บาท</div>` : 
                                        `฿${formatCurrency(subtotalBase)} บาท`
                                    }
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            ${data.order.remark ? `<div class="po-section"><h4><i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:5px;">notes</i> หมายเหตุ</h4><div class="section-content" style="background:#f8f9fa;padding:15px;border-radius:6px;">${data.order.remark.replace(/\n/g, '<br>')}</div></div>` : ''}
            <div class="po-footer">
                <div>
                    ${data.order.created_at ? `<small class="text-muted">สร้างเมื่อ: ${new Date(data.order.created_at).toLocaleString('th-TH', {
                        year: 'numeric', month: 'long', day: 'numeric', 
                        hour: '2-digit', minute: '2-digit'
                    })}</small>` : ''}
                    ${data.order.updated_at && data.order.updated_at !== data.order.created_at ? `<br><small class="text-muted">แก้ไขล่าสุด: ${new Date(data.order.updated_at).toLocaleString('th-TH', {
                        year: 'numeric', month: 'long', day: 'numeric', 
                        hour: '2-digit', minute: '2-digit'
                    })}</small>` : ''}
                </div>
                <div>
                    <button onclick="window.open('../orders/purchase_order_create.php', '_blank')" class="btn btn-primary">
                        <span class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;">add</span> สร้างใบสั่งซื้อใหม่
                    </button>
                    <button onclick="window.print()" class="btn btn-secondary">
                        <span class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;">print</span> พิมพ์
                    </button>
                    <button onclick="closePoView()" class="btn btn-secondary">
                        <span class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;">close</span> ปิด
                    </button>
                </div>
            </div>
            <style>
                .po-header, .po-section { margin-bottom: 20px; }
                .po-meta { display: flex; flex-wrap: wrap; gap: 20px; margin: 15px 0; }
                .po-meta-item { flex: 1; min-width: 200px; }
                .meta-label { display: block; color: #6c757d; font-size: 0.85em; }
                .meta-value { font-weight: 500; }
                .po-sections { display: flex; flex-wrap: wrap; gap: 20px; }
                .section-content { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 10px; }
                .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
                .section-actions { display: flex; gap: 8px; }
                .btn-edit-section { background: #007bff; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; }
                .btn-add-item { background: #007bff; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; }
                .btn-edit-section:hover, .btn-add-item:hover { opacity: 0.8; }
                .edit-form { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin: 10px 0; }
                .form-row { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
                .form-group { flex: 1; min-width: 200px; }
                .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: #495057; }
                .form-input { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; }
                .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px; }
                .btn-save { background: #28a745; color: white; }
                .btn-cancel { background: #6c757d; color: white; }
                .po-items-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                .po-items-table th { background: #f1f3f5; padding: 10px; text-align: left; font-weight: 500; }
                .po-items-table td { padding: 12px 10px; border-bottom: 1px solid #e9ecef; }
                .po-items-table tbody tr:hover { background-color: #f8f9fa; }
                .item-edit-mode .po-items-table td { padding: 8px; }
                .item-edit-input { width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 3px; font-size: 13px; }
                .item-actions { white-space: nowrap; }
                .btn-item-action { padding: 4px 8px; margin: 0 2px; border: none; border-radius: 3px; cursor: pointer; font-size: 11px; }
                .btn-save-item { background: #28a745; color: white; }
                .btn-cancel-item { background: #6c757d; color: white; }
                .btn-delete-item { background: #dc3545; color: white; }
                .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: 500; text-transform: capitalize; }
                .status-badge.pending { background: #fff3cd; color: #856404; }
                .status-badge.partial { background: #d4edda; color: #155724; }
                .status-badge.completed { background: #d1ecf1; color: #0c5460; }
                .status-badge.cancel { background: #f8d7da; color: #721c24; }
                .po-footer { margin-top:30px;padding-top:15px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center; }
                .btn-sm { padding: 6px 12px; font-size: 13px; }
                .btn { display: inline-block; padding: 8px 16px; margin: 0 4px; border: none; border-radius: 4px; text-decoration: none; cursor: pointer; font-size: 14px; }
                .btn-primary { background: #007bff; color: white; }
                .btn-secondary { background: #6c757d; color: white; }
                .btn:hover { opacity: 0.8; }
                .text-muted { color: #6c757d; }
                .table-responsive { overflow-x: auto; }
                
                /* SweetAlert2 z-index override for popup dialogs */
                .swal2-top-z-index {
                    z-index: 99999 !important;
                }
                .swal2-top-z-index .swal2-container {
                    z-index: 99999 !important;
                }
                
                /* Global SweetAlert2 z-index fix for all instances */
                .swal2-container {
                    z-index: 99999 !important;
                }
                .swal2-backdrop-show {
                    z-index: 99998 !important;
                }
                
                /* Improved Add Item Popup Styles */
                .swal2-popup-styled {
                    border-radius: 12px !important;
                    padding: 30px !important;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15) !important;
                }
                
                .swal2-popup-styled .swal2-title {
                    font-size: 24px !important;
                    color: #2c3e50 !important;
                    margin-bottom: 25px !important;
                    font-weight: 600 !important;
                }
                
                .swal2-popup-styled .swal2-html-container {
                    font-size: 14px !important;
                    color: #555 !important;
                    padding: 0 !important;
                }
                
                .swal2-btn-success {
                    background: linear-gradient(135deg, #27ae60 0%, #229954 100%) !important;
                    border-radius: 6px !important;
                    padding: 10px 24px !important;
                    font-weight: 600 !important;
                    min-width: 120px !important;
                    transition: all 0.3s ease !important;
                    box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3) !important;
                }
                
                .swal2-btn-success:hover {
                    transform: translateY(-2px) !important;
                    box-shadow: 0 6px 16px rgba(39, 174, 96, 0.4) !important;
                }
                
                .swal2-btn-cancel {
                    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%) !important;
                    border-radius: 6px !important;
                    padding: 10px 24px !important;
                    font-weight: 600 !important;
                    min-width: 120px !important;
                    transition: all 0.3s ease !important;
                }
                
                .swal2-btn-cancel:hover {
                    transform: translateY(-2px) !important;
                }
                
                @media print {
                    body { padding: 20px; }
                    .mainwrap, .topbar, .sidebar, .action-btns, .po-footer, button { display: none !important; }
                    .popup-bg { position: relative; background: none; display: block !important; opacity: 1 !important; }
                    .popup-inner { box-shadow: none; max-width: 100%; padding: 0; border: none; }
                }
            </style>
        `;
    }

    // ===================== Edit PO =====================
    function openPoEdit(e, el) {
        e.preventDefault();
        const po_id = el.getAttribute('data-po');
        const popup = document.getElementById('poEditPopup');
        const content = document.getElementById('poEditContent');
        
        content.innerHTML = `<div>Loading...</div>`;
        
        popup.classList.add('active');
        document.body.style.overflow = 'hidden';

        poEditKeyDownHandler = (evt) => {
            if (evt.key === 'Escape') closePoEdit();
        };
        document.addEventListener('keydown', poEditKeyDownHandler);
        popup.addEventListener('click', function(event) {
            if (event.target === popup) {
                closePoEdit();
            }
        }, { once: true });

        fetch('../api/purchase_order_api.php?id=' + po_id)
            .then(res => res.json())
            .then(data => {
                renderPoEdit(data);
            })
            .catch(err => {
                console.error('Error loading PO for edit:', err);
                content.innerHTML = `<div>Error loading data.</div>`;
            });
    }

    function renderPoEdit(data) {
        if(!data || !data.order){
            document.getElementById('poEditContent').innerHTML = '<div style="color:red;">ไม่พบข้อมูล</div>';
            return;
        }

        let orderDate = new Date(data.order.order_date).toISOString().split('T')[0];

        let itemsHtml = data.items.map((item) => `
            <tr data-item-id="${item.item_id || ''}">
                <td>
                    <input type="hidden" name="item_id[]" value="${item.item_id || ''}">
                    <input type="text" class="product-search" value="${item.product_name || ''}" placeholder="พิมพ์ชื่อสินค้า">
                    <input type="hidden" name="product_id[]" value="${item.product_id || ''}">
                    <div class="autocomplete-results"></div>
                </td>
                <td><input type="number" name="qty[]" value="${item.qty || 1}" min="1" class="form-control"></td>
                <td><input type="number" step="0.01" name="price_per_unit[]" value="${item.price_per_unit || 0}" class="form-control"></td>
                <td><button type="button" class="btn-remove-item" onclick="removePoItem(this)">ลบ</button></td>
            </tr>
        `).join('');

        document.getElementById('poEditContent').innerHTML = `
            <h3>แก้ไขใบสั่งซื้อ</h3>
            <form id="poEditForm">
                <input type="hidden" name="po_id" value="${data.order.po_id}">
                <div class="form-group"><label>เลขที่ใบสั่งซื้อ:</label><input type="text" name="po_number" value="${data.order.po_number}" class="form-control"></div>
                <div class="form-group"><label>วันที่สั่งซื้อ:</label><input type="date" name="order_date" value="${orderDate}" class="form-control"></div>
                <div class="form-group"><label>หมายเหตุ:</label><textarea name="remark" class="form-control">${data.order.remark ?? ''}</textarea></div>
                <hr>
                <h4>รายการสินค้า</h4>
                <table class="table-edit-items">
                    <thead><tr><th>สินค้า</th><th>จำนวน</th><th>ราคา/หน่วย</th><th></th></tr></thead>
                    <tbody id="poItemsTable">${itemsHtml}</tbody>
                </table>
                <button type="button" onclick="addPoItem()">+ เพิ่มสินค้า</button>
                <div style="margin-top:15px;text-align:right;">
                    <button type="button" class="btn btn-secondary" onclick="closePoEdit()">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" onclick="submitPoEdit()">บันทึก</button>
                </div>
            </form>
            <style>
                .form-group { margin-bottom: 15px; }
                .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
                .table-edit-items { width: 100%; }
                .btn-remove-item { background: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer; }
                .autocomplete-results { position: absolute; background: white; border: 1px solid #ddd; z-index: 10001; max-height: 200px; overflow-y: auto; }
                .result-item { padding: 8px; cursor: pointer; }
                .result-item:hover { background: #f1f1f1; }
            </style>
        `;

        initProductSearch();
    }

    function addPoItem(){
        let tbody = document.getElementById('poItemsTable');
        let newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td>
                <input type="hidden" name="item_id[]" value="">
                <input type="text" class="product-search" placeholder="พิมพ์ชื่อสินค้า">
                <input type="hidden" name="product_id[]" value="">
                <div class="autocomplete-results"></div>
            </td>
            <td><input type="number" name="qty[]" value="1" min="1" class="form-control"></td>
            <td><input type="number" step="0.01" name="price_per_unit[]" value="0.00" class="form-control"></td>
            <td><button type="button" class="btn-remove-item" onclick="removePoItem(this)">ลบ</button></td>
        `;
        tbody.appendChild(newRow);
        initProductSearch(newRow.querySelector('.product-search'));
    }

    function removePoItem(btn){
        btn.closest('tr').remove();
    }

    // Legacy product search function replaced by ProductAutocomplete class

    function getStatusText(status) {
        const statusMap = {
            'pending': 'รอดำเนินการ', 'partial': 'รับสินค้าบางส่วน',
            'completed': 'เสร็จสิ้น', 'cancel': 'ยกเลิก'
        };
        return statusMap[status] || status;
    }

    function submitPoEdit() {
        let form = document.getElementById('poEditForm');
        let formData = new FormData(form);

        // Show a loading indicator
        Swal.fire({
            title: 'กำลังบันทึก...',
            text: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            // Ensure this alert is on top
            customClass: {
                container: 'swal2-on-top'
            }
        });

        fetch('../orders/purchase_order_update.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closePoEdit(); // Close the edit popup first
                Swal.fire({
                    title: 'บันทึกแล้ว',
                    text: 'ข้อมูลใบสั่งซื้อถูกอัปเดตแล้ว',
                    icon: 'success',
                    timer: 2000, // Auto-close after 2 seconds
                    showConfirmButton: false
                }).then(() => {
                    location.reload(); // Then reload the page
                });
            } else {
                Swal.fire({
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถบันทึกได้: ' + (data.message || 'Unknown error'),
                    icon: 'error',
                    // Ensure this alert is on top
                    customClass: {
                        container: 'swal2-on-top'
                    }
                });
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({
                title: 'เกิดข้อผิดพลาด',
                text: 'การสื่อสารกับเซิร์ฟเวอร์ล้มเหลว',
                icon: 'error',
                // Ensure this alert is on top
                customClass: {
                    container: 'swal2-on-top'
                }
            });
        });
    }

    // ===================== Section-wise Editing Functions =====================
    
    // Global variable to store current PO data
    let currentPoData = null;

    // ===================== Enhanced Product Autocomplete System =====================
    
    class ProductAutocomplete {
        constructor(inputElement, options = {}) {
            this.input = inputElement;
            this.container = inputElement.parentElement;
            this.options = {
                minLength: 1,
                delay: 300,
                maxResults: 10,
                onSelect: options.onSelect || (() => {}),
                ...options
            };
            
            this.dropdown = null;
            this.searchTimeout = null;
            this.selectedIndex = -1;
            this.results = [];
            this.isLoading = false;
            
            this.init();
        }
        
        init() {
            // Make sure container has relative positioning
            if (getComputedStyle(this.container).position === 'static') {
                this.container.style.position = 'relative';
            }
            
            // Create dropdown element
            this.createDropdown();
            
            // Bind events
            this.bindEvents();
        }
        
        createDropdown() {
            this.dropdown = document.createElement('div');
            this.dropdown.className = 'autocomplete-dropdown';
            this.container.appendChild(this.dropdown);
        }
        
        bindEvents() {
            // Input events
            this.input.addEventListener('input', (e) => this.handleInput(e));
            this.input.addEventListener('keydown', (e) => this.handleKeydown(e));
            this.input.addEventListener('focus', (e) => this.handleFocus(e));
            this.input.addEventListener('blur', (e) => this.handleBlur(e));
            
            // Dropdown events
            this.dropdown.addEventListener('mousedown', (e) => this.handleDropdownClick(e));
        }
        
        handleInput(e) {
            const query = e.target.value.trim();
            
            // Clear previous timeout
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }
            
            if (query.length < this.options.minLength) {
                this.hideDropdown();
                return;
            }
            
            // Debounce search
            this.searchTimeout = setTimeout(() => {
                this.search(query);
            }, this.options.delay);
        }
        
        handleKeydown(e) {
            if (!this.dropdown.classList.contains('show')) return;
            
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.selectNext();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.selectPrevious();
                    break;
                case 'Enter':
                    e.preventDefault();
                    if (this.selectedIndex >= 0) {
                        this.selectItem(this.results[this.selectedIndex]);
                    }
                    break;
                case 'Escape':
                    e.preventDefault();
                    this.hideDropdown();
                    break;
            }
        }
        
        handleFocus(e) {
            const query = e.target.value.trim();
            if (query.length >= this.options.minLength && this.results.length > 0) {
                this.showDropdown();
            }
        }
        
        handleBlur(e) {
            // Delay hiding to allow dropdown clicks
            setTimeout(() => {
                this.hideDropdown();
            }, 150);
        }
        
        handleDropdownClick(e) {
            const item = e.target.closest('.autocomplete-item');
            if (item) {
                const index = parseInt(item.dataset.index);
                if (this.results[index]) {
                    this.selectItem(this.results[index]);
                }
            }
        }
        
        async search(query) {
            this.isLoading = true;
            this.showLoading();
            
            try {
                console.log('Searching for:', query); // Debug log
                const response = await fetch(`../api/product_search_api.php?q=${encodeURIComponent(query)}&limit=${this.options.maxResults}`);
                
                console.log('Search response status:', response.status, response.statusText); // Debug log
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                // Get response text first to debug
                const responseText = await response.text();
                console.log('Raw response:', responseText); // Debug log
                
                if (!responseText.trim()) {
                    throw new Error('Empty response from server');
                }
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('JSON parse error:', jsonError);
                    console.error('Response text was:', responseText);
                    throw new Error(`Invalid JSON response: ${jsonError.message}`);
                }
                
                console.log('Parsed search results:', data); // Debug log
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                this.results = data || [];
                this.renderResults();
                
                if (this.results.length > 0) {
                    this.showDropdown();
                } else {
                    this.showNoResults();
                }
                
            } catch (error) {
                console.error('Autocomplete search error:', error);
                this.dropdown.innerHTML = `<div class="autocomplete-no-results">เกิดข้อผิดพลาด: ${error.message}</div>`;
                this.showDropdown();
            } finally {
                this.isLoading = false;
            }
        }
        
        showLoading() {
            this.dropdown.innerHTML = '<div class="autocomplete-loading">กำลังค้นหา...</div>';
            this.showDropdown();
        }
        
        showNoResults() {
            this.dropdown.innerHTML = '<div class="autocomplete-no-results">ไม่พบสินค้าที่ค้นหา</div>';
            this.showDropdown();
        }
        
        showError() {
            this.dropdown.innerHTML = '<div class="autocomplete-no-results">เกิดข้อผิดพลาดในการค้นหา</div>';
            this.showDropdown();
        }
        
        renderResults() {
            if (this.results.length === 0) {
                this.showNoResults();
                return;
            }
            
            this.dropdown.innerHTML = this.results.map((item, index) => `
                <div class="autocomplete-item" data-index="${index}" style="display: flex; align-items: center; padding: 8px 12px;">
                    <div style="margin-right: 10px; flex-shrink: 0;">
                        ${item.image ? `<img src="../${item.image}" alt="${this.escapeHtml(item.name || 'Product')}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:1px solid #ddd;" onerror="this.src='../images/noimg.png'">` : `<div style="width:40px;height:40px;background:#f8f9fa;border:1px solid #ddd;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#666;font-size:8px;">ไม่มี<br>รูป</div>`}
                    </div>
                    <div style="flex: 1;">
                        <div class="autocomplete-item-name">${this.escapeHtml(item.name || 'ไม่ระบุชื่อ')}</div>
                        <div class="autocomplete-item-details">
                            SKU: ${this.escapeHtml(item.sku || 'N/A')} | 
                            ราคา: ฿${parseFloat(item.price_per_unit || 0).toLocaleString('th-TH', {minimumFractionDigits: 2})} | 
                            คงเหลือ: ${parseInt(item.stock_qty || 0).toLocaleString()} ${item.unit || 'ชิ้น'}
                        </div>
                    </div>
                </div>
            `).join('');
            
            this.selectedIndex = -1;
            this.updateSelection();
        }
        
        selectNext() {
            if (this.selectedIndex < this.results.length - 1) {
                this.selectedIndex++;
                this.updateSelection();
            }
        }
        
        selectPrevious() {
            if (this.selectedIndex > 0) {
                this.selectedIndex--;
                this.updateSelection();
            }
        }
        
        updateSelection() {
            const items = this.dropdown.querySelectorAll('.autocomplete-item');
            items.forEach((item, index) => {
                item.classList.toggle('selected', index === this.selectedIndex);
            });
        }
        
        selectItem(item) {
            this.input.value = item.name || '';
            this.hideDropdown();
            
            // Call the onSelect callback
            this.options.onSelect(item, this.input);
        }
        
        showDropdown() {
            this.dropdown.classList.add('show');
        }
        
        hideDropdown() {
            this.dropdown.classList.remove('show');
            this.selectedIndex = -1;
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        destroy() {
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }
            
            if (this.dropdown) {
                this.dropdown.remove();
            }
        }
    }
    
    // Initialize autocomplete for product inputs
    function initProductAutocomplete(input, options = {}) {
        // Prevent double initialization
        if (input._autocomplete) {
            input._autocomplete.destroy();
        }
        
        input._autocomplete = new ProductAutocomplete(input, {
            onSelect: (product, inputElement) => {
                // Find the row this input belongs to
                const row = inputElement.closest('tr') || inputElement.closest('.form-group').parentElement;
                
                if (row) {
                    // Update hidden product_id field if exists
                    const productIdInput = row.querySelector('input[name="product_id[]"], input[name="product_id"], input[type="hidden"][data-field="product_id"]');
                    if (productIdInput) {
                        productIdInput.value = product.product_id || '';
                    }
                    
                    // Update price field if exists
                    const priceInput = row.querySelector('input[name="price_per_unit[]"], input[name="price_per_unit"], input[data-field="price_per_unit"]');
                    if (priceInput) {
                        priceInput.value = product.price_per_unit || '0';
                    }
                    
                    // Update SKU field if exists
                    const skuInput = row.querySelector('input[name="sku"], input[data-field="sku"]');
                    if (skuInput) {
                        skuInput.value = product.sku || '';
                    }
                    
                    // Update unit field if exists
                    const unitInput = row.querySelector('input[name="unit"], input[data-field="unit"]');
                    if (unitInput) {
                        unitInput.value = product.unit || 'ชิ้น';
                    }
                }
                
                // Call custom onSelect if provided
                if (options.onSelect) {
                    options.onSelect(product, inputElement);
                }
            },
            ...options
        });
        
        return input._autocomplete;
    }

    // Helper function for SweetAlert2 with proper z-index
    function showSwal(options) {
        return Swal.fire({
            ...options,
            customClass: {
                container: 'swal2-top-z-index',
                ...(options.customClass || {})
            }
        });
    }

    // Edit User Section
    function editUserSection(poId) {
        const section = document.getElementById('user-section');
        const user = currentPoData.user;
        
        section.innerHTML = `
            <div class="edit-form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ผู้สั่งซื้อ:</label>
                        <select class="form-input" id="edit-user-id">
                            <option value="${user.user_id || ''}">${user.name || 'เลือกผู้ใช้'}</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-save btn-sm" onclick="saveUserSection(${poId})">บันทึก</button>
                    <button class="btn btn-cancel btn-sm" onclick="cancelUserEdit()">ยกเลิก</button>
                </div>
            </div>
        `;
        
        // Load users for dropdown
        loadUsersDropdown();
    }

    function loadUsersDropdown() {
        fetch('../api/users_api.php')
            .then(res => res.json())
            .then(users => {
                const select = document.getElementById('edit-user-id');
                const currentUserId = currentPoData.user.user_id;
                
                select.innerHTML = users.map(u => 
                    `<option value="${u.user_id}" ${u.user_id == currentUserId ? 'selected' : ''}>${u.name} - ${u.department || 'ไม่ระบุแผนก'}</option>`
                ).join('');
            })
            .catch(() => {
                // Fallback if API fails
                const select = document.getElementById('edit-user-id');
                select.innerHTML = `<option value="${currentPoData.user.user_id || ''}">${currentPoData.user.name || 'ไม่ระบุ'}</option>`;
            });
    }

    function saveUserSection(poId) {
        const userId = document.getElementById('edit-user-id').value;
        
        const formData = new FormData();
        formData.append('po_id', poId);
        formData.append('ordered_by', userId);
        formData.append('update_type', 'user');

        fetch('../api/update_po_section.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Refresh the entire view with all data
                fetch('../api/purchase_order_api.php?id=' + poId)
                    .then(res => {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .then(updatedData => {
                        if (updatedData.error) throw new Error(updatedData.error);
                        currentPoData = updatedData;
                        renderPoView(updatedData);
                        Swal.fire({
                            title: 'บันทึกแล้ว!', 
                            text: 'ข้อมูลผู้สั่งซื้อถูกอัปเดตแล้ว', 
                            icon: 'success',
                            customClass: { container: 'swal2-top-z-index' }
                        });
                    })
                    .catch(err => {
                        console.error('Error refreshing data:', err);
                        Swal.fire('เตือน', 'ข้อมูลอาจไม่ปรากฏให้เห็นทั้งหมด กรุณาปิด popup และเปิดใหม่', 'warning');
                    });
            } else {
                Swal.fire('เกิดข้อผิดพลาด!', data.message || 'ไม่สามารถบันทึกได้', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('เกิดข้อผิดพลาด!', 'การสื่อสารกับเซิร์ฟเวอร์ล้มเหลว', 'error');
        });
    }

    function cancelUserEdit() {
        renderUserSection();
    }

    function renderUserSection() {
        const section = document.getElementById('user-section');
        const user = currentPoData.user;
        section.innerHTML = `
            <p><strong>${user.name || '-'}</strong></p>
            ${user.department ? `<p>แผนก: ${user.department}</p>` : ''}
        `;
    }

    // Edit Supplier Section
    function editSupplierSection(poId) {
        const section = document.getElementById('supplier-section');
        const supplier = currentPoData.supplier;
        
        section.innerHTML = `
            <div class="edit-form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ผู้จำหน่าย:</label>
                        <select class="form-input" id="edit-supplier-id">
                            <option value="${supplier.supplier_id || ''}">${supplier.name || 'เลือกผู้จำหน่าย'}</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-save btn-sm" onclick="saveSupplierSection(${poId})">บันทึก</button>
                    <button class="btn btn-cancel btn-sm" onclick="cancelSupplierEdit()">ยกเลิก</button>
                </div>
            </div>
        `;
        
        // Load suppliers for dropdown
        loadSuppliersDropdown();
    }

    function loadSuppliersDropdown() {
        fetch('../api/suppliers_api.php')
            .then(res => res.json())
            .then(suppliers => {
                const select = document.getElementById('edit-supplier-id');
                const currentSupplierId = currentPoData.supplier.supplier_id;
                
                select.innerHTML = suppliers.map(s => 
                    `<option value="${s.supplier_id}" ${s.supplier_id == currentSupplierId ? 'selected' : ''}>${s.name}</option>`
                ).join('');
            })
            .catch(() => {
                // Fallback if API fails
                const select = document.getElementById('edit-supplier-id');
                select.innerHTML = `<option value="${currentPoData.supplier.supplier_id || ''}">${currentPoData.supplier.name || 'ไม่ระบุ'}</option>`;
            });
    }

    function saveSupplierSection(poId) {
        const supplierId = document.getElementById('edit-supplier-id').value;
        
        const formData = new FormData();
        formData.append('po_id', poId);
        formData.append('supplier_id', supplierId);
        formData.append('update_type', 'supplier');

        fetch('../api/update_po_section.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Refresh the entire view with all data
                fetch('../api/purchase_order_api.php?id=' + poId)
                    .then(res => {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .then(refreshedData => {
                        if (refreshedData.error) throw new Error(refreshedData.error);
                        currentPoData = refreshedData;
                        renderPoView(refreshedData);
                        Swal.fire({
                            title: 'บันทึกแล้ว!', 
                            text: 'ข้อมูลผู้จำหน่ายถูกอัปเดตแล้ว', 
                            icon: 'success',
                            customClass: { container: 'swal2-top-z-index' }
                        });
                    })
                    .catch(err => {
                        console.error('Error refreshing data:', err);
                        Swal.fire('เตือน', 'ข้อมูลอาจไม่ปรากฏให้เห็นทั้งหมด กรุณาปิด popup และเปิดใหม่', 'warning');
                    });
            } else {
                Swal.fire('เกิดข้อผิดพลาด!', data.message || 'ไม่สามารถบันทึกได้', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('เกิดข้อผิดพลาด!', 'การสื่อสารกับเซิร์ฟเวอร์ล้มเหลว', 'error');
        });
    }

    function cancelSupplierEdit() {
        renderSupplierSection();
    }

    function renderSupplierSection() {
        const section = document.getElementById('supplier-section');
        const supplier = currentPoData.supplier;
        section.innerHTML = `
            <p><strong>${supplier.name || '-'}</strong></p>
            ${supplier.phone || supplier.email ? `<p>${supplier.phone ? 'โทร: ' + supplier.phone : ''} ${supplier.phone && supplier.email ? ' | ' : ''}${supplier.email ? 'อีเมล: ' + supplier.email : ''}</p>` : ''}
            ${supplier.address ? `<p>ที่อยู่: ${supplier.address}</p>` : ''}
        `;
    }

    // Edit Items Inline
    let isEditingItems = false;

    function editItemsInline(poId) {
        if (isEditingItems) return;
        
        isEditingItems = true;
        const tableBody = document.querySelector('.po-items-table tbody');
        const rows = tableBody.querySelectorAll('tr');
        
        // Add edit mode class
        document.querySelector('.po-items-table').classList.add('item-edit-mode');
        
        rows.forEach((row, index) => {
            const item = currentPoData.items[index];
            if (!item) return;
            
            const cells = row.querySelectorAll('td');
            if (cells.length >= 8) {
                // Keep image cell as is (index 1)
                
                // Product name (make it editable with autocomplete) - now index 2
                cells[2].innerHTML = `
                    <div class="autocomplete-container" style="position: relative;">
                        <input type="text" class="item-edit-input autocomplete-input" value="${item.product_name || ''}" data-field="product_name" placeholder="พิมพ์ชื่อสินค้าเพื่อค้นหา">
                        <input type="hidden" data-field="product_id" value="${item.product_id || ''}">
                        <input type="hidden" data-field="image" value="${item.image || ''}">
                    </div>
                `;
                
                // Quantity - now index 4
                cells[4].innerHTML = `<input type="number" class="item-edit-input" value="${item.qty || 0}" data-field="qty" min="1" step="1">`;
                
                // Price per unit - now index 6
                // Use price_original (ราคาต้นฉบับในสกุลเงินที่เลือก) instead of price_per_unit (ราคาเป็นบาท)
                const displayPrice = (item.price_original !== null && item.price_original !== undefined) ? item.price_original : item.price_per_unit || 0;
                const currencySymbol = currentPoData.order.currency_symbol || '฿';
                const exchangeRate = parseFloat(currentPoData.order.exchange_rate || 1);
                const convertedPrice = (displayPrice * exchangeRate).toFixed(2);
                cells[6].innerHTML = `
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <input type="number" class="item-edit-input" value="${displayPrice}" data-field="price_per_unit" min="0" step="0.01" style="font-weight: bold;">
                        <div style="font-size: 11px; color: #666; padding: 2px 0;">
                            ${currencySymbol}${displayPrice} ≈ ฿${convertedPrice}
                        </div>
                    </div>
                `;
                
                // Add action buttons - now index 7
                cells[7].innerHTML = `
                    <div class="item-actions">
                        <button class="btn-item-action btn-save-item" onclick="saveItemRow(${poId}, ${item.item_id || 0}, ${index})">บันทึก</button>
                        <button class="btn-item-action btn-delete-item" onclick="deleteItemRow(${poId}, ${item.item_id || 0}, ${index})">ลบ</button>
                    </div>
                `;
            }
        });
        
        // Initialize autocomplete for all product name inputs
        setTimeout(() => {
            const productInputs = document.querySelectorAll('.item-edit-input.autocomplete-input');
            console.log('Found', productInputs.length, 'product inputs to initialize'); // Debug log
            
            productInputs.forEach((input, index) => {
                console.log('Initializing autocomplete for input', index); // Debug log
                
                // Ensure the parent container has autocomplete-container class
                if (!input.parentElement.classList.contains('autocomplete-container')) {
                    const container = document.createElement('div');
                    container.className = 'autocomplete-container';
                    container.style.position = 'relative';
                    input.parentElement.insertBefore(container, input);
                    container.appendChild(input);
                }
                
                initProductAutocomplete(input, {
                    onSelect: (product, inputElement) => {
                        console.log('Product selected:', product); // Debug log
                        const row = inputElement.closest('tr');
                        if (row) {
                            // Update hidden product_id
                            const productIdInput = row.querySelector('input[data-field="product_id"]');
                            if (productIdInput) {
                                productIdInput.value = product.product_id || '';
                                console.log('Updated product_id to:', product.product_id);
                            }
                            
                            // Update price
                            const priceInput = row.querySelector('input[data-field="price_per_unit"]');
                            if (priceInput) {
                                priceInput.value = product.price_per_unit || '0';
                                console.log('Updated price to:', product.price_per_unit);
                            }
                            
                            // Update image
                            const imageInput = row.querySelector('input[data-field="image"]');
                            if (imageInput) {
                                imageInput.value = product.image || '';
                                console.log('Updated image to:', product.image);
                                
                                // Update the image display in the table
                                const imageCell = row.cells[1]; // Image column
                                if (imageCell && product.image) {
                                    imageCell.innerHTML = `<img src="../${product.image}" alt="${product.name || 'Product'}" style="width:50px;height:50px;object-fit:cover;border-radius:4px;border:1px solid #ddd;" onerror="this.src='../images/noimg.png'">`;
                                }
                            }
                        }
                    }
                });
            });
        }, 200);
        
        // Add exit edit mode button
        const sectionActions = document.querySelector('.section-actions');
        sectionActions.innerHTML = `
            <button onclick="exitItemEditMode()" class="btn-cancel btn-sm">
                <i class="material-icons" style="font-size:14px;">close</i> เสร็จสิ้น
            </button>
        `;
    }

    function exitItemEditMode() {
        isEditingItems = false;
        document.querySelector('.po-items-table').classList.remove('item-edit-mode');
        
        // Re-render the items table
        renderItemsTable();
        
        // Restore section actions
        const sectionActions = document.querySelector('.section-actions');
        sectionActions.innerHTML = `
            <button onclick="editItemsInline(${currentPoData.order.po_id})" class="btn-edit-section btn-sm" title="แก้ไขรายการสินค้า">
                <i class="material-icons" style="font-size:14px;">edit</i> แก้ไขรายการ
            </button>
            <button onclick="addNewItem(${currentPoData.order.po_id})" class="btn-add-item btn-sm" title="เพิ่มสินค้าใหม่">
                <i class="material-icons" style="font-size:14px;">add</i> เพิ่มสินค้า
            </button>
        `;
    }

    function saveItemRow(poId, itemId, index) {
        // Reset edit mode to ensure proper state
        isEditingItems = false;
        
        const row = document.querySelectorAll('.po-items-table tbody tr')[index];
        const inputs = row.querySelectorAll('.item-edit-input, input[type="hidden"][data-field]');
        
        console.log('Saving item row:', { poId, itemId, index }); // Debug log
        console.log('Found inputs:', inputs.length); // Debug log
        
        const updateData = {};
        inputs.forEach((input, inputIndex) => {
            if (input.dataset.field) {
                updateData[input.dataset.field] = input.value;
                console.log(`Input ${inputIndex} (${input.dataset.field}):`, input.value); // Debug log
            }
        });
        
        console.log('Update data collected:', updateData); // Debug log
        
        // Get currency information from current order
        const currencyId = currentPoData.order.currency_id || 1;
        const exchangeRate = parseFloat(currentPoData.order.exchange_rate || 1);
        
        // price_per_unit from form is the ORIGINAL CURRENCY price (ราคาต้นฉบับ)
        const priceOriginal = parseFloat(updateData.price_per_unit || 0);
        
        // Convert to Thai Baht for storage in price_per_unit
        const pricePerUnitInBaht = priceOriginal * exchangeRate;
        
        const formData = new FormData();
        formData.append('po_id', poId);
        formData.append('item_id', itemId);
        formData.append('product_id', updateData.product_id || '');
        formData.append('product_name', updateData.product_name || '');
        formData.append('qty', updateData.qty || 1);
        formData.append('price_per_unit', pricePerUnitInBaht);  // Store converted price in THB
        formData.append('currency_id', currencyId);
        formData.append('price_original', priceOriginal);  // Store original currency price
        formData.append('exchange_rate', exchangeRate);
        formData.append('update_type', 'item');

        console.log('Saving item row:', { poId, itemId, index, priceOriginal, pricePerUnitInBaht, exchangeRate }); // Debug log
        
        fetch('../api/update_po_section.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Save response status:', response.status); // Debug log
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Save response data:', data); // Debug log
            
            if (data.success) {
                // Refresh the PO data from server immediately
                fetch('../api/purchase_order_api.php?id=' + poId)
                    .then(res => {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .then(refreshedData => {
                        if (refreshedData.error) throw new Error(refreshedData.error);
                        currentPoData = refreshedData;
                        renderPoView(refreshedData);
                        
                        // Show success message after re-render
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'บันทึกแล้ว',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    })
                    .catch(err => {
                        console.error('Error refreshing data:', err);
                        Swal.fire('เตือน', 'ข้อมูลอาจไม่ปรากฏให้เห็นทั้งหมด กรุณาปิด popup และเปิดใหม่', 'warning');
                    });
            } else {
                Swal.fire({
                    title: 'เกิดข้อผิดพลาด!', 
                    text: data.message || 'ไม่สามารถบันทึกได้', 
                    icon: 'error',
                    customClass: { container: 'swal2-top-z-index' }
                });
            }
        })
        .catch(err => {
            console.error('Save error:', err);
            Swal.fire({
                title: 'เกิดข้อผิดพลาด!', 
                text: `การสื่อสารกับเซิร์ฟเวอร์ล้มเหลว: ${err.message}`, 
                icon: 'error',
                customClass: { container: 'swal2-top-z-index' }
            });
        });
    }

    function deleteItemRow(poId, itemId, index) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: 'คุณต้องการลบรายการสินค้านี้ใช่หรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก',
            customClass: {
                container: 'swal2-top-z-index'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('po_id', poId);
                formData.append('item_id', itemId);
                formData.append('update_type', 'delete_item');

                console.log('Deleting item:', { poId, itemId, index }); // Debug log
                
                fetch('../api/update_po_section.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Delete response status:', response.status); // Debug log
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    return response.json();
                })
                .then(data => {
                    console.log('Delete response data:', data); // Debug log
                    
                    if (data.success) {
                        // Remove from current data
                        currentPoData.items.splice(index, 1);
                        
                        // Show success message
                        Swal.fire({
                            title: 'ลบแล้ว!', 
                            text: 'รายการสินค้าถูกลบเรียบร้อย', 
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false,
                            customClass: { container: 'swal2-top-z-index' }
                        });
                        
                        // Exit edit mode and refresh data
                        exitItemEditMode();
                        
                        // Refresh the PO data from server
                        setTimeout(() => {
                            fetch('../api/purchase_order_api.php?id=' + poId)
                                .then(res => res.json())
                                .then(refreshedData => {
                                    currentPoData = refreshedData;
                                    renderPoView(refreshedData);
                                })
                                .catch(err => console.error('Error refreshing data:', err));
                        }, 500);
                    } else {
                        Swal.fire({
                            title: 'เกิดข้อผิดพลาด!', 
                            text: data.message || 'ไม่สามารถลบได้', 
                            icon: 'error',
                            customClass: { container: 'swal2-top-z-index' }
                        });
                    }
                })
                .catch(err => {
                    console.error('Delete error:', err);
                    Swal.fire({
                        title: 'เกิดข้อผิดพลาด!', 
                        text: `การสื่อสารกับเซิร์ฟเวอร์ล้มเหลว: ${err.message}`, 
                        icon: 'error',
                        customClass: { container: 'swal2-top-z-index' }
                    });
                });
            }
        });
    }

    function addNewItem(poId) {
        // Get current order currency info
        const orderCurrency = currentPoData.order.currency_code || 'THB';
        const currencySymbol = currentPoData.order.currency_symbol || '฿';
        const exchangeRate = parseFloat(currentPoData.order.exchange_rate || 1);
        
        // Show a form to add new item with autocomplete
        Swal.fire({
            title: '➕ เพิ่มสินค้าใหม่',
            html: `
                <div style="text-align: left; max-width: 400px; margin: 0 auto;">
                    <!-- Product Name Field -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; font-size: 14px;">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 5px;">inventory_2</i>ชื่อสินค้า
                        </label>
                        <div class="autocomplete-container" style="position: relative;">
                            <input type="text" id="new-product-name" class="autocomplete-input" placeholder="🔍 พิมพ์ชื่อสินค้าเพื่อค้นหา" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; box-sizing: border-box;">
                            <input type="hidden" id="new-product-id" value="">
                        </div>
                    </div>

                    <!-- Quantity Field -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; font-size: 14px;">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 5px;">app_registration</i>จำนวน
                        </label>
                        <input type="number" id="new-qty" value="1" min="1" step="1" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;" onchange="updateNewItemBasePrice()" oninput="updateNewItemBasePrice()">
                    </div>

                    <!-- Currency Field -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; font-size: 14px;">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 5px;">currency_exchange</i>สกุลเงิน
                        </label>
                        <select id="new-currency" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; background-color: white; box-sizing: border-box; cursor: pointer;" onchange="updateNewItemCurrency()">
                            ${currentPoData.currencies ? currentPoData.currencies.map(c => 
                                `<option value="${c.currency_id}" data-rate="${c.exchange_rate}" data-symbol="${c.symbol}" ${c.code === orderCurrency ? 'selected' : ''}>${c.symbol} ${c.name} (${c.code})</option>`
                            ).join('') : `<option value="1" data-rate="1" data-symbol="฿">฿ Thai Baht (THB)</option>`}
                        </select>
                    </div>

                    <!-- Price Field -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; font-size: 14px;">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 5px;">local_offer</i>ราคาต่อหน่วย
                        </label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span id="new-currency-symbol" style="font-weight: bold; color: #1976d2; font-size: 18px; min-width: 30px; text-align: center;">${currencySymbol}</span>
                            <input type="number" id="new-price" value="0" min="0" step="0.01" style="flex: 1; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;" onchange="updateNewItemBasePrice()" oninput="updateNewItemBasePrice()">
                        </div>
                        <div id="new-price-base" style="font-size: 13px; color: #27ae60; margin-top: 6px; font-weight: 500;">≈ ฿0.00</div>
                    </div>

                    <!-- Unit Field -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; font-size: 14px;">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 5px;">straighten</i>หน่วย
                        </label>
                        <input type="text" id="new-unit" value="ชิ้น" placeholder="เช่น ชิ้น, กล่อง, ม้วน" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>

                    <!-- Summary Box -->
                    <div style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 15px; border-radius: 8px; margin-top: 25px;">
                        <div style="font-size: 13px; color: #555; margin-bottom: 8px;">
                            <strong>สรุป:</strong> <span id="summary-qty">1</span> × <span id="summary-unit">ชิ้น</span> @ <span id="summary-price">฿0.00</span>
                        </div>
                        <div style="font-size: 13px; color: #666;">
                            รวม: <span id="summary-total">฿0.00</span>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '✅ เพิ่มสินค้า',
            cancelButtonText: '❌ ยกเลิก',
            confirmButtonColor: '#27ae60',
            cancelButtonColor: '#95a5a6',
            customClass: {
                container: 'swal2-top-z-index',
                popup: 'swal2-popup-styled',
                confirmButton: 'swal2-btn-success',
                cancelButton: 'swal2-btn-cancel'
            },
            didOpen: () => {
                // Add focus styling
                const inputs = document.querySelectorAll('#new-product-name, #new-qty, #new-currency, #new-price, #new-unit');
                inputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        this.style.borderColor = '#3498db';
                        this.style.boxShadow = '0 0 0 3px rgba(52, 152, 219, 0.1)';
                    });
                    input.addEventListener('blur', function() {
                        this.style.borderColor = '#e0e0e0';
                        this.style.boxShadow = 'none';
                    });
                });
                
                // Initialize autocomplete for the product input
                const productInput = document.getElementById('new-product-name');
                if (productInput) {
                    initProductAutocomplete(productInput, {
                        onSelect: (product, inputElement) => {
                            // Update hidden fields
                            document.getElementById('new-product-id').value = product.product_id || '';
                            document.getElementById('new-price').value = product.price_per_unit || '0';
                            document.getElementById('new-unit').value = product.unit || 'ชิ้น';
                            updateNewItemBasePrice(); // Update base price when product is selected
                            updateSummary();
                        }
                    });
                }
                
                // Initialize displays
                updateNewItemBasePrice();
                updateSummary();
            },
            didOpen: () => {
                // Add focus styling
                const inputs = document.querySelectorAll('#new-product-name, #new-qty, #new-currency, #new-price, #new-unit');
                inputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        this.style.borderColor = '#3498db';
                        this.style.boxShadow = '0 0 0 3px rgba(52, 152, 219, 0.1)';
                    });
                    input.addEventListener('blur', function() {
                        this.style.borderColor = '#e0e0e0';
                        this.style.boxShadow = 'none';
                    });
                    // Update summary on input change
                    input.addEventListener('change', updateSummary);
                    input.addEventListener('input', updateSummary);
                });
                
                // Initialize autocomplete for the product input
                const productInput = document.getElementById('new-product-name');
                if (productInput) {
                    initProductAutocomplete(productInput, {
                        onSelect: (product, inputElement) => {
                            // Update hidden fields
                            document.getElementById('new-product-id').value = product.product_id || '';
                            document.getElementById('new-price').value = product.price_per_unit || '0';
                            document.getElementById('new-unit').value = product.unit || 'ชิ้น';
                            updateNewItemBasePrice(); // Update base price when product is selected
                            updateSummary();
                        }
                    });
                }
                
                // Initialize displays
                updateNewItemBasePrice();
                updateSummary();
            },
            preConfirm: () => {
                const productName = document.getElementById('new-product-name').value;
                const productId = document.getElementById('new-product-id').value;
                const qty = document.getElementById('new-qty').value;
                const price = document.getElementById('new-price').value;
                const unit = document.getElementById('new-unit').value;
                const currencySelect = document.getElementById('new-currency');
                const currencyId = currencySelect.value;
                const exchangeRate = parseFloat(currencySelect.options[currencySelect.selectedIndex].dataset.rate);
                
                if (!productName.trim()) {
                    Swal.showValidationMessage('กรุณาระบุชื่อสินค้า');
                    return false;
                }
                
                return { productName, productId, qty, price, unit, currencyId, exchangeRate };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const { productName, productId, qty, price, unit, currencyId, exchangeRate } = result.value;
                
                const formData = new FormData();
                formData.append('po_id', poId);
                formData.append('product_id', productId || '');
                formData.append('product_name', productName);
                formData.append('qty', qty);
                formData.append('price_per_unit', price);
                formData.append('unit', unit || 'ชิ้น');
                formData.append('currency_id', currencyId);
                formData.append('price_original', price);
                formData.append('exchange_rate', exchangeRate);
                formData.append('update_type', 'add_item');

                fetch('../api/update_po_section.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Refresh the PO data from server immediately, then show success
                        fetch('../api/purchase_order_api.php?id=' + poId)
                            .then(res => {
                                if (!res.ok) throw new Error('HTTP ' + res.status);
                                return res.json();
                            })
                            .then(refreshedData => {
                                if (refreshedData.error) throw new Error(refreshedData.error);
                                currentPoData = refreshedData;
                                renderPoView(refreshedData);
                                
                                // Show success message after data is rendered
                                Swal.fire({
                                    title: 'เพิ่มแล้ว!', 
                                    text: 'รายการสินค้าใหม่ถูกเพิ่มเรียบร้อย', 
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false,
                                    customClass: { container: 'swal2-top-z-index' }
                                });
                            })
                            .catch(err => {
                                console.error('Error refreshing data:', err);
                                Swal.fire('เตือน', 'ข้อมูลอาจไม่ปรากฏให้เห็นทั้งหมด กรุณาปิด popup และเปิดใหม่', 'warning');
                            });
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด!', data.message || 'ไม่สามารถเพิ่มได้', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('เกิดข้อผิดพลาด!', 'การสื่อสารกับเซิร์ฟเวอร์ล้มเหลว', 'error');
                });
            }
        });
    }

    function renderItemsTable() {
        // Reset edit mode flag to prevent stuck state
        isEditingItems = false;
        
        const formatCurrency = (amount) => parseFloat(amount || 0).toLocaleString('th-TH', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });

        console.log('Rendering items table with data:', currentPoData.items); // Debug log

        let itemsHtml = currentPoData.items && currentPoData.items.length > 0 ? currentPoData.items.map((item, index) => {
            const qty = parseFloat(item.qty || 0);
            const price = parseFloat(item.price_per_unit || item.price_base || 0);
            const total = parseFloat(item.total || (qty * price));
            
            console.log(`Item ${index + 1}:`, { qty, price, total }); // Debug log
            
            // Handle image display - check if it's Base64 (temp_products) or file path (regular products)
            let imageHtml = '';
            const imageInfo = resolveItemImage(item.image);
            if (imageInfo.src) {
                const errorAttr = imageInfo.needsFallback ? " onerror=\"this.src='../images/noimg.png'\"" : '';
                imageHtml = `<img src="${imageInfo.src}" alt="${item.product_name || 'Product'}" style="width:50px;height:50px;object-fit:cover;border-radius:4px;border:1px solid #ddd;"${errorAttr}>`;
            } else {
                imageHtml = `<div style="width:50px;height:50px;background:#f8f9fa;border:1px solid #ddd;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#666;font-size:10px;">ไม่มี<br>รูป</div>`;
            }
            
            return `
            <tr>
                <td style="text-align:center;">${index + 1}</td>
                <td style="text-align:center;">
                    ${imageHtml}
                </td>
                <td>
                    ${item.product_name || 'ไม่ระบุชื่อสินค้า'}
                </td>
                <td>${item.sku || '-'}</td>
                <td style="text-align:right;">${qty.toLocaleString()}</td>
                <td>${item.unit || 'ชิ้น'}</td>
                <td style="text-align:right;">฿${formatCurrency(price)}</td>
                <td style="text-align:right;">฿${formatCurrency(total)}</td>
            </tr>`;
        }).join('') : `<tr><td colspan="8" style="text-align:center;padding:20px;color:#6c757d;">ไม่พบรายการสินค้า</td></tr>`;

        // Update the table content
        const tbody = document.querySelector('.po-items-table tbody');
        if (tbody) {
            tbody.innerHTML = itemsHtml;
            console.log('Table updated successfully'); // Debug log
        } else {
            console.error('Table tbody not found'); // Debug log
        }
        
        updateItemsTotal();
    }

    function updateItemsTotal() {
        if (!currentPoData || !currentPoData.items || currentPoData.items.length === 0) {
            console.log('No items to calculate total for'); // Debug log
            return;
        }

        console.log('Calculating total for items:', currentPoData.items); // Debug log

        const subtotalOriginal = currentPoData.items.reduce((sum, item) => {
            const qty = parseFloat(item.qty || 0);
            const priceOriginal = parseFloat(item.price_original || item.price_per_unit || item.price_base || 0);
            const itemTotal = qty * priceOriginal;
            console.log(`Item total: ${qty} × ${priceOriginal} = ${itemTotal}`); // Debug log
            return sum + itemTotal;
        }, 0);
        
        const subtotalBase = currentPoData.items.reduce((sum, item) => {
            const qty = parseFloat(item.qty || 0);
            const priceBase = parseFloat(item.price_base || item.price_per_unit || 0);
            const total = parseFloat(item.total || (qty * priceBase));
            return sum + total;
        }, 0);

        console.log('Calculated totals:', { subtotalOriginal, subtotalBase }); // Debug log

        const formatCurrency = (amount) => parseFloat(amount || 0).toLocaleString('th-TH', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });

        const currencySymbol = currentPoData.order.currency_symbol || '฿';
        const currencyCode = currentPoData.order.currency_code || 'THB';
        
        const totalHtml = currencyCode !== 'THB' ? 
            `<div>${currencySymbol}${formatCurrency(subtotalOriginal)}</div><div style="font-size:12px;color:#666;font-weight:normal;">≈ ฿${formatCurrency(subtotalBase)} บาท</div>` : 
            `฿${formatCurrency(subtotalBase)} บาท`;
            
        // Try multiple selectors to find the total cell
        let totalCell = document.querySelector('.po-items-table tfoot td:last-child');
        if (!totalCell) {
            totalCell = document.querySelector('.po-items-table tfoot tr td:nth-child(8)');
        }
        if (!totalCell) {
            totalCell = document.querySelector('table tfoot td:last-child');
        }
        
        if (totalCell) {
            totalCell.innerHTML = totalHtml;
            console.log('Total updated to:', totalHtml); // Debug log
        } else {
            console.error('Total cell not found in table footer, available selectors:'); // Debug log
            console.log('tfoot:', document.querySelectorAll('tfoot'));
            console.log('tfoot td:', document.querySelectorAll('tfoot td'));
            console.log('table:', document.querySelectorAll('table'));
            
            // If no footer cell found, try to create/update it
            const table = document.querySelector('.po-items-table');
            if (table) {
                let tfoot = table.querySelector('tfoot');
                if (!tfoot) {
                    tfoot = document.createElement('tfoot');
                    table.appendChild(tfoot);
                }
                tfoot.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align:right;font-weight:bold;border-top:1px solid #dee2e6;">รวมทั้งสิ้น</td>
                        <td style="text-align:right;font-weight:bold;border-top:1px solid #dee2e6;">
                            ${totalHtml}
                        </td>
                    </tr>
                `;
                console.log('Created new tfoot with total'); // Debug log
            }
        }
    }
    
    // Helper functions for currency handling in forms
    function updateSummary() {
        const qty = parseFloat(document.getElementById('new-qty')?.value || 1);
        const price = parseFloat(document.getElementById('new-price')?.value || 0);
        const unit = document.getElementById('new-unit')?.value || 'ชิ้น';
        const currencySymbol = document.getElementById('new-currency-symbol')?.textContent || '฿';
        const exchangeRate = parseFloat(document.getElementById('new-currency')?.options[document.getElementById('new-currency').selectedIndex]?.dataset.rate || 1);
        
        const basePrice = price * exchangeRate;
        const total = qty * basePrice;
        
        // Update summary
        document.getElementById('summary-qty').textContent = qty.toLocaleString();
        document.getElementById('summary-unit').textContent = unit;
        document.getElementById('summary-price').textContent = currencySymbol + price.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('summary-total').textContent = '฿' + total.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    function updateNewItemCurrency() {
        const currencySelect = document.getElementById('new-currency');
        const symbolSpan = document.getElementById('new-currency-symbol');
        const selectedOption = currencySelect.options[currencySelect.selectedIndex];
        
        if (symbolSpan && selectedOption) {
            symbolSpan.textContent = selectedOption.dataset.symbol;
        }
        
        updateNewItemBasePrice();
        updateSummary();
    }
    
    function updateNewItemBasePrice() {
        const priceInput = document.getElementById('new-price');
        const currencySelect = document.getElementById('new-currency');
        const basePriceDiv = document.getElementById('new-price-base');
        
        if (!priceInput || !currencySelect || !basePriceDiv) return;
        
        const price = parseFloat(priceInput.value || 0);
        const selectedOption = currencySelect.options[currencySelect.selectedIndex];
        const rate = parseFloat(selectedOption.dataset.rate || 1);
        const basePrice = price * rate;
        
        basePriceDiv.textContent = `≈ ฿${basePrice.toFixed(2)}`;
        updateSummary();
    }
    
    </script>
</body>
</html>
