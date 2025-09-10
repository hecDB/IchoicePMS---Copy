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

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="mainwrap">
    <div class="topbar">
        ใบสั่งซื้อ (Purchase Orders)
    </div>
    <div class="main">
        <div class="top-bar">
            <div>
                <div class="main-title">ใบสั่งซื้อ</div>
                <div class="sub-title">จัดการใบสั่งซื้อทั้งหมด</div>
            </div>
          <button class="btn-create" onclick="window.location.href='purchase_order_create.php'">
            <span class="material-icons">add</span> สร้างใบสั่งซื้อใหม่
            </button>
        </div>
        <div class="box">
            <div class="box-title"><span class="material-icons">description</span> รายการใบสั่งซื้อ</div>
            <div class="search-bar">
                <input type="text" placeholder="ค้นหาเลขใบสั่งซื้อ, ผู้จำหน่าย, หรือรายการสินค้า...">
            </div>
            <table id="po-table" class="display" style="width:100%">
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
                                    case 'pending': echo '<span class="status-chip status-pending">รอดำเนินการ</span>'; break;
                                    case 'partial': echo '<span class="status-chip status-approved">รับของบางส่วน</span>'; break;
                                    case 'completed': echo '<span class="status-chip status-received">เสร็จสิ้น</span>'; break;
                                    case 'cancel': echo '<span class="status-chip status-cancel">ยกเลิก</span>'; break;
                                }
                                ?>
                            </td>
                            <td>
                              <div class="action-btns">
                                    <a class="icon-btn view" href="#" data-po="<?=$r['po_id']?>" onclick="openPoView(event, this)">
                                                <span class="material-icons">visibility</span> ดู
                                            </a>
                                     
                                          <a class="icon-btn edit" href="#" data-po="<?=$r['po_id']?>" onclick="openPoEdit(event, this)">
                                                <span class="material-icons">edit</span> แก้ไข
                                            </a>


                                    <a class="icon-btn delete" href="purchase_order_delete.php?id=<?=$r['po_id']?>" 
                                    onclick="return confirm('ยืนยันลบ?');">
                                        <span class="material-icons">delete</span> ลบ
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach;?>
                </tbody>
            </table>
</body>
</html>
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(function(){
    $('#po-table').DataTable({
        "order": [[0, "asc"]],
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" },
        "pageLength": 25
    });
});
</script>
         <!-- Popup view -->
            <!-- View Popup -->
            <div id="poViewPopup">
                <div class="popup-bg" onclick="closePoView(event)">
                    <div class="popup-inner" onclick="event.stopPropagation();">
                        <button class="close-btn" onclick="closePoView(event)" title="ปิด">
                            <span class="material-icons" style="font-size:18px;">close</span>
                        </button>
                        <div id="poViewContent">Loading...</div>
                    </div>
                </div>
            </div>

            <!-- Edit Popup -->
            <div id="poEditPopup">
                <div class="popup-bg" onclick="closePoEdit(event)">
                    <div class="popup-content">
                        <button class="close-btn" onclick="closePoEdit(event)" title="ปิด">
                            <span class="material-icons" style="font-size:18px;">close</span>
                        </button>
                        <div id="poEditContent">Loading...</div>
                    </div>
                </div>
            </div>

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
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 9999;
                        opacity: 0;
                        visibility: hidden;
                        transition: all 0.3s ease;
                    }
                    
                    .popup-bg.active {
                        opacity: 1;
                        visibility: visible;
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
                    
                    /* Popup Content */
                    #poViewContent h3 {
                        color: #2c3e50;
                        margin-top: 0;
                        padding-bottom: 15px;
                        border-bottom: 1px solid #eee;
                        margin-bottom: 20px;
                    }
                    
                    /* Table Styles */
                    #poViewContent table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 20px 0;
                    }
                    
                    #poViewContent table th {
                        background-color: #f8f9fa;
                        padding: 12px 15px;
                        text-align: left;
                        font-weight: 600;
                        color: #495057;
                        border-bottom: 2px solid #dee2e6;
                    }
                    
                    #poViewContent table td {
                        padding: 12px 15px;
                        border-bottom: 1px solid #eee;
                        vertical-align: middle;
                    }
                    
                    #poViewContent table tr:last-child td {
                        border-bottom: none;
                    }
                    
                    #poViewContent table tr:hover td {
                        background-color: #f8f9fa;
                    }
                    
                    /* Edit Popup Specific */
                    #poEditPopup {
                        display: none;
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.5);
                        backdrop-filter: blur(3px);
                        z-index: 10000;
                        opacity: 0;
                        visibility: hidden;
                        transition: all 0.3s ease;
                    }
                    
                    #poEditPopup.active {
                        opacity: 1;
                        visibility: visible;
                    }
                    
                    .popup-content {
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
                    
                    /* Form Elements */
                    #poEditContent input[type="text"],
                    #poEditContent input[type="number"],
                    #poEditContent textarea,
                    #poEditContent select {
                        width: 100%;
                        padding: 10px 15px;
                        border: 1px solid #ddd;
                        border-radius: 6px;
                        font-size: 14px;
                        margin-bottom: 15px;
                        transition: border-color 0.3s;
                    }
                    
                    #poEditContent input[type="text"]:focus,
                    #poEditContent input[type="number"]:focus,
                    #poEditContent textarea:focus {
                        border-color: #4a90e2;
                        outline: none;
                        box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
                    }
                    
                    /* Buttons */
                    .btn {
                        display: inline-block;
                        padding: 10px 20px;
                        border: none;
                        border-radius: 6px;
                        font-size: 14px;
                        font-weight: 500;
                        cursor: pointer;
                        transition: all 0.3s;
                    }
                    
                    .btn-primary {
                        background-color: #4a90e2;
                        color: white;
                    }
                    
                    .btn-primary:hover {
                        background-color: #3a7bc8;
                    }
                    
                    .btn-secondary {
                        background-color: #6c757d;
                        color: white;
                        margin-right: 10px;
                    }
                    
                    .btn-secondary:hover {
                        background-color: #5a6268;
                    }
                    
                    /* Responsive Adjustments */
                    @media (max-width: 768px) {
                        .popup-inner,
                        .popup-content {
                            width: 95%;
                            padding: 20px 15px;
                        }
                        
                        #poViewContent table th,
                        #poViewContent table td {
                            padding: 8px 10px;
                            font-size: 14px;
                        }
                    }
                </style>
            </div>


        </div>
    </div>
      </div>
        <!-- Edit Popup -->
        <div id="poEditPopup" style="display:none;">
            <div class="popup-bg" onclick="closePoEdit(event)">
                <div class="popup-inner" style="max-width: 800px;" onclick="event.stopPropagation();">
                    <button class="close-btn" onclick="closePoEdit(event)" title="ปิด">
                        <span class="material-icons" style="font-size:18px;">close</span>
                    </button>
                    <div id="poEditContent" style="max-height:80vh;overflow-y:auto;"></div>
                </div>
            </div>
        </div>
    </body>
</html>

<script>
// ===================== View PO =====================
function openPoView(e, el) {
    e.preventDefault();
    const po_id = el.getAttribute('data-po');
    console.log('Loading PO:', po_id);

    const popup = document.getElementById('poViewPopup');
    const popupBg = popup.querySelector('.popup-bg');
    const content = document.getElementById('poViewContent');
    
    // Show loading state
    content.innerHTML = `
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:200px;">
            <div class="spinner" style="width:40px;height:40px;border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:15px;"></div>
            <div>กำลังโหลดข้อมูล...</div>
        </div>
    `;
    
    // Show popup with animation
    popup.style.display = 'block';
    setTimeout(() => {
        popupBg.classList.add('active');
        document.body.style.overflow = 'hidden';
    }, 10);

    // Add keyboard event listener for ESC key
    if (poViewKeyDownHandler) {
        document.removeEventListener('keydown', poViewKeyDownHandler);
    }
    poViewKeyDownHandler = (e) => e.key === 'Escape' && closePoView();
    document.addEventListener('keydown', poViewKeyDownHandler);

    fetch('purchase_order_api.php?id=' + po_id)
        .then(res => res.json())
        .then(data => {
            console.log('PO data loaded:', data);
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

    // Format date
    const orderDate = new Date(data.order.order_date);
    const formattedDate = orderDate.toLocaleDateString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'long'
    });

    // Format currency
    const formatCurrency = (amount) => {
        return parseFloat(amount || 0).toLocaleString('th-TH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    // Generate items HTML
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
    `).join('') : `
        <tr>
            <td colspan="7" style="text-align:center;padding:20px;color:#6c757d;">ไม่พบรายการสินค้า</td>
        </tr>
    `;

    // Calculate totals
    const subtotal = data.items ? data.items.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0) : 0;
    const vat = subtotal * 0.07; // 7% VAT
    const grandTotal = subtotal + vat;

    // Generate the HTML
    content.innerHTML = `
        <div class="po-header">
            <h3>รายละเอียดใบสั่งซื้อ</h3>
            <div class="po-meta">
                <div class="po-meta-item">
                    <span class="meta-label">เลขที่ใบสั่งซื้อ</span>
                    <span class="meta-value">${data.order.po_number || '-'}</span>
                </div>
                <div class="po-meta-item">
                    <span class="meta-label">วันที่สั่งซื้อ</span>
                    <span class="meta-value">${formattedDate}</span>
                </div>
                <div class="po-meta-item">
                    <span class="meta-label">สถานะ</span>
                    <span class="status-badge ${data.order.status || 'pending'}">
                        ${getStatusText(data.order.status || 'pending')}
                    </span>
                </div>
            </div>
        </div>

        <div class="po-sections">
            <div class="po-section">
                <h4><i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:5px;">person</i> ข้อมูลผู้สั่งซื้อ</h4>
                <div class="section-content">
                    <p><strong>${data.user.name || '-'}</strong></p>
                </div>
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
                <div>
                    <button onclick="openPoEdit(event, this)" data-po="${data.order.po_id}" class="btn btn-primary" style="padding:6px 12px;font-size:13px;">
                        <i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;">edit</i> แก้ไข
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="po-items-table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th width="50px" style="padding:10px;background:#f1f3f5;text-align:center;">ลำดับ</th>
                            <th style="padding:10px;background:#f1f3f5;">ชื่อสินค้า</th>
                            <th style="padding:10px;background:#f1f3f5;">SKU</th>
                            <th width="100px" style="padding:10px;background:#f1f3f5;text-align:right;">จำนวน</th>
                            <th width="80px" style="padding:10px;background:#f1f3f5;">หน่วย</th>
                            <th width="120px" style="padding:10px;background:#f1f3f5;text-align:right;">ราคาต่อหน่วย</th>
                            <th width="120px" style="padding:10px;background:#f1f3f5;text-align:right;">รวม</th>
                        </tr>
                    </thead>
                    <tbody>${itemsHtml}</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align:right;padding:10px;font-size:1.1em;border-top:1px solid #dee2e6;"><strong>รวมทั้งสิ้น</strong></td>
                            <td style="text-align:right;padding:10px;font-size:1.1em;border-top:1px solid #dee2e6;" colspan="2"><strong>${formatCurrency(subtotal)} บาท</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        ${data.order.remark ? `
        <div class="po-section">
            <h4><i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:5px;">notes</i> หมายเหตุ</h4>
            <div class="section-content" style="background:#f8f9fa;padding:15px;border-radius:6px;border-right:4px solid #e9ecef;">
                ${data.order.remark.replace(/\n/g, '<br>')}
            </div>
        </div>` : ''}

        <div class="po-footer" style="margin-top:30px;padding-top:15px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <small class="text-muted">สร้างเมื่อ: ${new Date(data.order.created_at).toLocaleString('th-TH')}</small>
                ${data.order.updated_at && data.order.updated_at !== data.order.created_at ? 
                    `<br><small class="text-muted">แก้ไขล่าสุด: ${new Date(data.order.updated_at).toLocaleString('th-TH')}</small>` : ''}
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-secondary" style="margin-right:10px;">
                    <i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;">print</i> พิมพ์
                </button>
                <button onclick="closePoView()" class="btn btn-secondary">
                    <i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;">close</i> ปิด
                </button>
            </div>
        </div>

        <style>
            .po-header { margin-bottom: 20px; }
            .po-meta { display: flex; flex-wrap: wrap; gap: 20px; margin: 15px 0; }
            .po-meta-item { flex: 1; min-width: 200px; }
            .meta-label { display: block; color: #6c757d; font-size: 0.85em; }
            .meta-value { font-weight: 500; }
            .po-sections { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
            .po-section { flex: 1; min-width: 300px; margin-bottom: 20px; }
            .section-content { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 10px; }
            .po-items-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .po-items-table th { background: #f1f3f5; padding: 10px; text-align: left; font-weight: 500; }
            .po-items-table td { padding: 12px 10px; border-bottom: 1px solid #e9ecef; }
            .po-items-table tbody tr:hover { background-color: #f8f9fa; }
            .status-badge { 
                display: inline-block; 
                padding: 4px 8px; 
                border-radius: 4px; 
                font-size: 0.8em; 
                font-weight: 500; 
                text-transform: capitalize;
            }
            .status-badge.pending { background: #fff3cd; color: #856404; }
            .status-badge.approved { background: #d4edda; color: #155724; }
            .status-badge.rejected { background: #f8d7da; color: #721c24; }
            .status-badge.completed { background: #d1ecf1; color: #0c5460; }
            @media print {
                .po-footer, button { display: none !important; }
                body { padding: 20px; }
                .popup-bg { position: relative; background: none; }
                .popup-inner { box-shadow: none; max-width: 100%; padding: 0; }
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `;
}

// ===================== Edit PO =====================
let poEditKeyDownHandler = null;

function openPoEdit(e, el) {
    e.preventDefault();
    const po_id = el.getAttribute('data-po');
    console.log('Opening PO for edit:', po_id);

    const popup = document.getElementById('poEditPopup');
    const popupBg = popup.querySelector('.popup-bg');
    const content = document.getElementById('poEditContent');
    
    // Show loading state
    content.innerHTML = `
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:200px;">
            <div class="spinner" style="width:40px;height:40px;border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:15px;"></div>
            <div>กำลังโหลดข้อมูล...</div>
        </div>
    `;
    
    // Show popup with animation
    popup.style.display = 'block';
    setTimeout(() => {
        popupBg.classList.add('active');
        document.body.style.overflow = 'hidden';
    }, 10);

    // Add keyboard event listener for ESC key
    if (poEditKeyDownHandler) {
        document.removeEventListener('keydown', poEditKeyDownHandler);
    }
    poEditKeyDownHandler = (e) => e.key === 'Escape' && closePoEdit();
    document.addEventListener('keydown', poEditKeyDownHandler);

    fetch('purchase_order_api.php?id=' + po_id)
        .then(res => res.json())
        .then(data => {
            console.log('PO data loaded for edit:', data);
            renderPoEdit(data);
        })
        .catch(err => {
            console.error('Error loading PO for edit:', err);
            content.innerHTML = `
                <div style="padding:30px;text-align:center;color:#dc3545;">
                    <span class="material-icons" style="font-size:48px;color:#dc3545;margin-bottom:15px;">error_outline</span>
                    <h3 style="margin-top:0;color:inherit;">เกิดข้อผิดพลาด</h3>
                    <p>ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง</p>
                    <button onclick="closePoEdit()" class="btn btn-secondary" style="margin-top:15px;">ปิด</button>
                </div>
            `;
        });
}

function renderPoEdit(data) {
    if(!data){
        document.getElementById('poEditContent').innerHTML = '<div style="color:red;">ไม่พบข้อมูล</div>';
        return;
    }

    // แปลงวันที่ให้อยู่ในรูปแบบ yyyy-MM-dd
    let orderDate = '';
    const dateStr = data.order.order_date;
    
    // ตรวจสอบรูปแบบวันที่
    if (dateStr.includes('-')) {
        // ถ้าวันที่อยู่ในรูปแบบ yyyy-MM-dd อยู่แล้ว
        orderDate = dateStr.split('T')[0]; // ตัดเวลา T00:00:00.000Z ออกถ้ามี
    } else if (dateStr.includes('/')) {
        // ถ้าวันที่อยู่ในรูปแบบ dd/MM/yyyy
        const dateParts = dateStr.split('/');
        if (dateParts.length === 3) {
            orderDate = `${dateParts[2]}-${dateParts[1].padStart(2, '0')}-${dateParts[0].padStart(2, '0')}`;
        }
    }
    
    // ถ้าแปลงไม่ได้ ให้ใช้ค่าวันที่ปัจจุบัน
    if (!orderDate) {
        const today = new Date();
        orderDate = today.toISOString().split('T')[0];
    }

    let itemsHtml = data.items.map((item, index) => `
        <tr>
            <td>
                <input type="hidden" name="item_id[]" value="${item.item_id || ''}">
                <input type="text" class="product-search" value="${item.product_name || ''}" placeholder="พิมพ์ชื่อสินค้า">
                <input type="hidden" name="product_id[]" value="${item.product_id || ''}">
                <div class="autocomplete-results"></div>
            </td>
            <td><input type="number" name="qty[]" value="${item.qty || 1}" min="1"></td>
            <td><input type="number" step="0.01" name="price_per_unit[]" value="${item.price_per_unit || 0}"></td>
            <td><button type="button" onclick="removePoItem(this)">ลบ</button></td>
        </tr>
    `).join('');

    document.getElementById('poEditContent').innerHTML = `
        <h3>แก้ไขใบสั่งซื้อ</h3>
        <form id="poEditForm">
            <input type="hidden" name="po_id" value="${data.order.po_id}">
            <p><b>เลขที่ใบสั่งซื้อ:</b> <input type="text" name="po_number" value="${data.order.po_number}"></p>
            <p><b>วันที่สั่งซื้อ:</b> <input type="date" name="order_date" value="${orderDate}"></p>
            <p><b>หมายเหตุ:</b> <textarea name="remark">${data.order.remark ?? ''}</textarea></p>
            <hr>
            <h4>รายการสินค้า</h4>
            <table border="1" width="100%">
                <thead>
                    <tr>
                        <th>สินค้า</th><th>จำนวน</th><th>ราคา/หน่วย</th><th>ลบ</th>
                    </tr>
                </thead>
                <tbody id="poItemsTable">
                    ${itemsHtml}
                </tbody>
            </table>
            <div style="margin-top:10px;">
                <button type="button" onclick="addPoItem()">+ เพิ่มสินค้า</button>
            </div>
            <div style="margin-top:15px;text-align:right;">
                <button type="button" onclick="submitPoEdit()">บันทึก</button>
            </div>
        </form>
    `;

    initProductSearch(); // เรียก autocomplete
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
        <td><input type="number" name="qty[]" value="1" min="1"></td>
        <td><input type="number" step="0.01" name="price_per_unit[]" value="0.00"></td>
        <td><button type="button" onclick="removePoItem(this)">ลบ</button></td>
    `;
    tbody.appendChild(newRow);
    initProductSearch();
}

function removePoItem(btn){
    btn.closest('tr').remove();
}

// Auto-complete search
function initProductSearch() {
    document.querySelectorAll('.product-search').forEach(input => {
        // Create results container if not exists
        if (!input.nextElementSibling || !input.nextElementSibling.classList.contains('autocomplete-results')) {
            const resultsBox = document.createElement('div');
            resultsBox.className = 'autocomplete-results';
            input.parentNode.insertBefore(resultsBox, input.nextSibling);
        }

        input.oninput = function() {
            let query = this.value.trim();
            let row = this.closest('tr');
            let resultsBox = this.nextElementSibling;
            
            if (!resultsBox || !resultsBox.classList.contains('autocomplete-results')) {
                resultsBox = document.createElement('div');
                resultsBox.className = 'autocomplete-results';
                this.parentNode.insertBefore(resultsBox, this.nextSibling);
            }

            if (query.length < 1) {
                resultsBox.innerHTML = '';
                resultsBox.style.display = 'none';
                return;
            }

            // Show loading
            resultsBox.innerHTML = '<div class="result-item">กำลังค้นหา...</div>';
            resultsBox.style.display = 'block';

            fetch('product_search_api.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) {
                        resultsBox.innerHTML = '<div class="result-item">ไม่พบสินค้า</div>';
                        return;
                    }
                    
                    resultsBox.innerHTML = data.map(p => {
                        let displayText = `${p.name}`;
                        if (p.sku) displayText += ` (SKU: ${p.sku})`;
                        if (p.barcode) displayText += ` [${p.barcode}]`;
                        
                        return `<div class="result-item" 
                                    data-id="${p.product_id}" 
                                    data-price="${p.price_per_unit}"
                                    data-name="${p.name}"
                                    data-sku="${p.sku || ''}"
                                    data-barcode="${p.barcode || ''}">
                                    ${displayText}
                                </div>`;
                    }).join('');

                    resultsBox.querySelectorAll('.result-item').forEach(itemDiv => {
                        itemDiv.onclick = (e) => {
                            e.stopPropagation();
                            input.value = itemDiv.dataset.name;
                            row.querySelector('input[name="product_id[]"]').value = itemDiv.dataset.id;
                            row.querySelector('input[name="price_per_unit[]"]').value = itemDiv.dataset.price;
                            resultsBox.style.display = 'none';
                        };
                    });
                })
                .catch(err => {
                    console.error('Search error:', err);
                    resultsBox.innerHTML = '<div class="result-item">เกิดข้อผิดพลาดในการค้นหา</div>';
                });
        };

        // Hide results when clicking outside
        document.addEventListener('click', function clickOutside(e) {
            if (!input.contains(e.target) && input.nextElementSibling && 
                input.nextElementSibling.classList.contains('autocomplete-results')) {
                input.nextElementSibling.style.display = 'none';
            }
        });
    });
}


// Helper function to get status text in Thai
function getStatusText(status) {
    const statusMap = {
        'pending': 'รอดำเนินการ',
        'partial': 'รับสินค้าบางส่วน',
        'completed': 'เสร็จสิ้น',
        'cancel': 'ยกเลิก'
    };
    return statusMap[status] || status;
}

// ===================== Close popup =====================
let poViewKeyDownHandler = null;

function closePoView(e) {
    if (e) e.stopPropagation();
    const popup = document.getElementById('poViewPopup');
    if (!popup) return;
    
    const popupBg = popup.querySelector('.popup-bg');
    popupBg.classList.remove('active');
    document.body.style.overflow = 'auto';
    
    // Remove keyboard event listener
    if (poViewKeyDownHandler) {
        document.removeEventListener('keydown', poViewKeyDownHandler);
        poViewKeyDownHandler = null;
    }
    
    // Remove after animation completes
    setTimeout(() => {
        popup.style.display = 'none';
    }, 300);
}

function closePoEdit(e) {
    if (e) e.stopPropagation();
    const popup = document.getElementById('poEditPopup');
    if (!popup) return;
    
    const popupBg = popup.querySelector('.popup-bg');
    popupBg.classList.remove('active');
    document.body.style.overflow = 'auto';
    
    // Remove keyboard event listener
    if (poEditKeyDownHandler) {
        document.removeEventListener('keydown', poEditKeyDownHandler);
        poEditKeyDownHandler = null;
    }
    
    // Remove after animation completes
    setTimeout(() => {
        popup.style.display = 'none';
    }, 300);
}

// ===================== Submit Edit =====================
function submitPoEdit() {
    let form = document.getElementById('poEditForm');
    let formData = new FormData(form);

    fetch('purchase_order_update.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('บันทึกเรียบร้อย');
                closePoEdit();
                location.reload();
            } else {
                alert('บันทึกไม่สำเร็จ: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('เกิดข้อผิดพลาดในการบันทึก');
        });
}

</script>
