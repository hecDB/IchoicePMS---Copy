<?php

//  ทดสอบ
session_start();
include 'sidebar.php';
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
    <link rel="stylesheet" href="assets/base.css">
    <link rel="stylesheet" href="assets/sidebar.css">
    <link rel="stylesheet" href="assets/components.css">
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
            width: 80%;
            max-width: 900px;
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
            width: 85%;
            max-width: 1000px;
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
    </style>
</head>
<body>
    <div class="mainwrap">
        <div class="topbar">
            ใบสั่งซื้อ (Purchase Orders)
        </div>
        <div class="main">
            <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
                <button class="btn-create" onclick="window.location.href='purchase_order_create.php'">
                    <span class="material-icons">add</span> สร้างใบสั่งซื้อใหม่
                </button>
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

                fetch('purchase_order_delete.php', {
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

        fetch('purchase_order_api.php?id=' + po_id)
            .then(res => res.json())
            .then(data => {
                renderPoView(data);
            })
            .catch(err => {
                console.error('Error loading PO:', err);
                content.innerHTML = `
                    <div style="padding:30px;text-align:center;color:#dc3545;">
                        <span class="material-icons" style="font-size:48px;color:#dc3545;margin-bottom:15px;">error_outline</span>
                        <h3 style="margin-top:0;color:inherit;">เกิดข้อผิดพลาด</h3>
                        <p>ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง</p>
                        <button onclick="closePoView()" class="btn btn-secondary" style="margin-top:15px;">ปิด</button>
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

        const formattedDate = new Date(data.order.order_date).toLocaleDateString('th-TH', {
            year: 'numeric', month: 'long', day: 'numeric', weekday: 'long'
        });

        const formatCurrency = (amount) => parseFloat(amount || 0).toLocaleString('th-TH', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });

        let itemsHtml = data.items && data.items.length > 0 ? data.items.map((item, index) => `
            <tr>
                <td style="text-align:center;">${index + 1}</td>
                <td>${item.product_name || '-'}</td>
                <td>${item.sku || '-'}</td>
                <td style="text-align:right;">${parseFloat(item.qty || 0).toLocaleString()}</td>
                <td>${item.unit || 'ชิ้น'}</td>
                <td style="text-align:right;">${formatCurrency(item.price_per_unit)}</td>
                <td style="text-align:right;">${formatCurrency(item.total)}</td>
            </tr>
        `).join('') : `<tr><td colspan="7" style="text-align:center;padding:20px;color:#6c757d;">ไม่พบรายการสินค้า</td></tr>`;

        const subtotal = data.items ? data.items.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0) : 0;

        content.innerHTML = `
            <div class="po-header">
                <h3>รายละเอียดใบสั่งซื้อ</h3>
                <div class="po-meta">
                    <div class="po-meta-item"><span class="meta-label">เลขที่ใบสั่งซื้อ</span><span class="meta-value">${data.order.po_number || '-'}</span></div>
                    <div class="po-meta-item"><span class="meta-label">วันที่สั่งซื้อ</span><span class="meta-value">${formattedDate}</span></div>
                    <div class="po-meta-item"><span class="meta-label">สถานะ</span><span class="status-badge ${data.order.status || 'pending'}">${getStatusText(data.order.status || 'pending')}</span></div>
                </div>
            </div>
            <div class="po-sections">
                <div class="po-section">
                    <h4><i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:5px;">person</i> ข้อมูลผู้สั่งซื้อ</h4>
                    <div class="section-content"><p><strong>${data.user.name || '-'}</strong></p></div>
                </div>
                <div class="po-section">
                    <h4><i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:5px;">store</i> ข้อมูลผู้จำหน่าย</h4>
                    <div class="section-content">
                        <p><strong>${data.supplier.name || '-'}</strong></p>
                        <p>${data.supplier.contact_person ? 'ติดต่อ: ' + data.supplier.contact_person : ''}</p>
                        <p>${data.supplier.phone ? 'โทร: ' + data.supplier.phone : ''} ${data.supplier.email ? '| อีเมล: ' + data.supplier.email : ''}</p>
                        <p>${data.supplier.address || ''}</p>
                        <p>${data.supplier.tax_id ? 'เลขประจำตัวผู้เสียภาษี: ' + data.supplier.tax_id : ''}</p>
                    </div>
                </div>
            </div>
            <div class="po-section">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                    <h4 style="margin:0;"><i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:5px;">shopping_cart</i> รายการสินค้า</h4>
                    <div><button onclick="openPoEdit(event, this)" data-po="${data.order.po_id}" class="btn btn-primary btn-sm"><i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;">edit</i> แก้ไข</button></div>
                </div>
                <div class="table-responsive">
                    <table class="po-items-table">
                        <thead>
                            <tr>
                                <th width="50px" style="text-align:center;">ลำดับ</th>
                                <th>ชื่อสินค้า</th>
                                <th>SKU</th>
                                <th width="100px" style="text-align:right;">จำนวน</th>
                                <th width="80px">หน่วย</th>
                                <th width="120px" style="text-align:right;">ราคา/หน่วย</th>
                                <th width="120px" style="text-align:right;">รวม</th>
                            </tr>
                        </thead>
                        <tbody>${itemsHtml}</tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" style="text-align:right;font-weight:bold;border-top:1px solid #dee2e6;">รวมทั้งสิ้น</td>
                                <td style="text-align:right;font-weight:bold;border-top:1px solid #dee2e6;">${formatCurrency(subtotal)} บาท</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            ${data.order.remark ? `<div class="po-section"><h4><i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:5px;">notes</i> หมายเหตุ</h4><div class="section-content" style="background:#f8f9fa;padding:15px;border-radius:6px;">${data.order.remark.replace(/\n/g, '<br>')}</div></div>` : ''}
            <div class="po-footer">
                <div>
                    <small class="text-muted">สร้างเมื่อ: ${new Date(data.order.created_at).toLocaleString('th-TH')}</small>
                    ${data.order.updated_at && data.order.updated_at !== data.order.created_at ? `<br><small class="text-muted">แก้ไขล่าสุด: ${new Date(data.order.updated_at).toLocaleString('th-TH')}</small>` : ''}
                </div>
                <div>
                    <button onclick="window.print()" class="btn btn-secondary"><i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;">print</i> พิมพ์</button>
                    <button onclick="closePoView()" class="btn btn-secondary"><i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;">close</i> ปิด</button>
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
                .po-items-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                .po-items-table th { background: #f1f3f5; padding: 10px; text-align: left; font-weight: 500; }
                .po-items-table td { padding: 12px 10px; border-bottom: 1px solid #e9ecef; }
                .po-items-table tbody tr:hover { background-color: #f8f9fa; }
                .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: 500; text-transform: capitalize; }
                .status-badge.pending { background: #fff3cd; color: #856404; }
                .status-badge.partial { background: #d4edda; color: #155724; }
                .status-badge.completed { background: #d1ecf1; color: #0c5460; }
                .status-badge.cancel { background: #f8d7da; color: #721c24; }
                .po-footer { margin-top:30px;padding-top:15px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center; }
                .btn-sm { padding: 6px 12px; font-size: 13px; }
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

        fetch('purchase_order_api.php?id=' + po_id)
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

    function initProductSearch(element) {
        const inputs = element ? [element] : document.querySelectorAll('.product-search:not(.initialized)');
        
        inputs.forEach(input => {
            input.classList.add('initialized');
            const resultsBox = input.parentElement.querySelector('.autocomplete-results');

            input.oninput = function() {
                let query = this.value.trim();
                if (query.length < 1) {
                    resultsBox.style.display = 'none';
                    return;
                }
                resultsBox.innerHTML = '<div>กำลังค้นหา...</div>';
                resultsBox.style.display = 'block';

                fetch('product_search_api.php?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        resultsBox.innerHTML = data.length ? data.map(p => `
                            <div class="result-item" data-id="${p.product_id}" data-price="${p.price_per_unit}" data-name="${p.name}">
                                ${p.name} (SKU: ${p.sku || 'N/A'})
                            </div>`).join('') : '<div>ไม่พบสินค้า</div>';
                    });
            };

            resultsBox.onclick = function(e) {
                if (e.target.classList.contains('result-item')) {
                    const row = this.closest('tr');
                    row.querySelector('.product-search').value = e.target.dataset.name;
                    row.querySelector('input[name="product_id[]"]').value = e.target.dataset.id;
                    row.querySelector('input[name="price_per_unit[]"]').value = e.target.dataset.price;
                    this.style.display = 'none';
                }
            };
        });

        document.addEventListener('click', function(e) {
            document.querySelectorAll('.autocomplete-results').forEach(box => {
                if (!box.parentElement.contains(e.target)) {
                    box.style.display = 'none';
                }
            });
        });
    }

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

        fetch('purchase_order_update.php', {
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
    </script>
</body>
</html>
