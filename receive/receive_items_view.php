<?php
session_start();
require '../config/db_connect.php';

// Query รวมข้อมูลรับเข้าและสินค้าออก (เฉพาะสินค้าที่มีอยู่แล้ว - temp_product_id IS NULL และ product_id IS NOT NULL)
$sql = "
        (SELECT 
            'receive' as transaction_type,
            r.receive_id as transaction_id, 
            p.image, p.sku, p.barcode, 
            u.name AS created_by, 
            r.created_at, 
            r.receive_qty as quantity, 
            r.remark_color, r.remark_split, CONVERT(r.remark USING utf8mb4) AS remark, r.expiry_date,
            l.row_code, l.bin, l.shelf, l.description AS location_desc,
            poi.price_per_unit, poi.sale_price,
            r.po_id, r.item_id,
            p.name AS product_name,
            p.product_id,
            p.remark_weight,
            r.created_by AS created_by_id,
            po.remark AS po_remark,
            po.po_number AS po_number,
            NULL as issue_tag,
            NULL as platform
        FROM receive_items r
        LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
        LEFT JOIN products p ON poi.product_id = p.product_id
        LEFT JOIN locations l ON l.location_id = (
            SELECT pl.location_id FROM product_location pl WHERE pl.product_id = p.product_id LIMIT 1
        )
        LEFT JOIN users u ON r.created_by = u.user_id
        LEFT JOIN purchase_orders po ON r.po_id = po.po_id
        WHERE poi.product_id IS NOT NULL )

        UNION ALL

        (SELECT 
            'issue' as transaction_type,
            ii.issue_id as transaction_id,
            p.image, p.sku, p.barcode,
            u.name AS created_by,
            ii.created_at,
            ii.issue_qty as quantity,
            NULL as remark_color, NULL as remark_split, 
            COALESCE(CONVERT(ii.remark USING utf8mb4), CONVERT(so.remark USING utf8mb4)) AS remark, 
            ri.expiry_date,
            l.row_code, l.bin, l.shelf, l.description AS location_desc,
            poi.price_per_unit, ii.sale_price,
            NULL as po_id, NULL as item_id,
            p.name AS product_name,
            p.product_id,
            p.remark_weight,
            ii.issued_by AS created_by_id,
            NULL as po_remark,
            NULL as po_number,
            so.issue_tag,
            so.platform
        FROM issue_items ii
        LEFT JOIN products p ON ii.product_id = p.product_id
        LEFT JOIN receive_items ri ON ii.receive_id = ri.receive_id
        LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
        LEFT JOIN locations l ON l.location_id = (
            SELECT pl.location_id FROM product_location pl WHERE pl.product_id = p.product_id LIMIT 1
        )
        LEFT JOIN users u ON ii.issued_by = u.user_id
        LEFT JOIN sales_orders so ON ii.sale_order_id = so.sale_order_id
        WHERE poi.product_id IS NOT NULL )

        ORDER BY created_at ASC
        LIMIT 500";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize movement quantities and compute running totals per product
$issueQtyStmt = $pdo->prepare("SELECT COALESCE(SUM(issue_qty), 0) FROM issue_items WHERE receive_id = ?");
$issueQtyCache = [];

foreach ($rows as $index => &$row) {
    $row['quantity'] = isset($row['quantity']) ? (float) $row['quantity'] : 0.0;
    $transactionType = $row['transaction_type'] ?? 'receive';

    if ($transactionType === 'issue') {
        $row['quantity_change'] = -abs($row['quantity']);
    } else {
        $receiveId = (int) ($row['transaction_id'] ?? 0);
        if ($receiveId > 0) {
            if (!array_key_exists($receiveId, $issueQtyCache)) {
                $issueQtyStmt->execute([$receiveId]);
                $issueQtyCache[$receiveId] = (float) $issueQtyStmt->fetchColumn();
            }
            $row['quantity_change'] = $row['quantity'] + $issueQtyCache[$receiveId];
        } else {
            $row['quantity_change'] = $row['quantity'];
        }
    }

    $row['quantity_display'] = abs($row['quantity_change']);
    $row['quantity_direction'] = $row['quantity_change'] < 0 ? 'minus' : 'plus';
}
unset($row);

$sortedIndices = array_keys($rows);
usort($sortedIndices, function ($a, $b) use ($rows) {
    $timeA = strtotime($rows[$a]['created_at'] ?? '1970-01-01 00:00:00');
    $timeB = strtotime($rows[$b]['created_at'] ?? '1970-01-01 00:00:00');
    if ($timeA === $timeB) {
        // Ensure receives are processed before issues when timestamps match
        return strcmp($rows[$a]['transaction_type'] ?? 'receive', $rows[$b]['transaction_type'] ?? 'receive');
    }
    return $timeA <=> $timeB;
});

$runningTotals = [];
foreach ($sortedIndices as $idx) {
    $productKey = (string)($rows[$idx]['product_id'] ?? '');
    if ($productKey === '') {
        $productKey = ($rows[$idx]['sku'] ?? '') . '|' . ($rows[$idx]['barcode'] ?? '');
    }
    if ($productKey === '' || $productKey === '|') {
        $productKey = 'unknown_' . $idx;
    }
    $runningTotals[$productKey] = ($runningTotals[$productKey] ?? 0) + $rows[$idx]['quantity_change'];
    $rows[$idx]['running_qty'] = $runningTotals[$productKey];
}

unset($sortedIndices, $runningTotals);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายการรับสินค้า - IchoicePMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="../images/favicon.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/base.css">
<link rel="stylesheet" href="../assets/sidebar.css">
<link rel="stylesheet" href="../assets/components.css">
<link href="../assets/modern-table.css" rel="stylesheet">
<link href="../assets/mainwrap-modern.css" rel="stylesheet">

<style>
            body {
                font-family: 'Prompt', sans-serif;
                background-color: #f8fafc;
            }
            
            .product-image {
                width: 36px; 
                height: 36px;
                max-width: 48px; 
                max-height: 48px;
                object-fit: cover;
                border-radius: 6px;
                border: 2px solid #e5e7eb;
            }
            
            .qty-plus {
                color: #059669;
                font-weight: 700;
            }
            
            .qty-minus {
                color: #dc2626;
                font-weight: 700;
            }
            
            .breadcrumb-modern {
                background: none;
                padding: 0;
            }
            
            .breadcrumb-modern .breadcrumb-item {
                color: #6b7280;
            }
            
            .breadcrumb-modern .breadcrumb-item.active {
                color: #111827;
                font-weight: 500;
            }

            @media (max-width: 768px) {
                .product-image { width: 28px; height: 28px; }
            }
            @media (max-width: 480px) {
                .product-image { width: 20px; height: 20px; }
            }

            /* PO Selection Modal Styles */
            .po-item {
                transition: all 0.3s ease;
                border-radius: 8px;
            }
            
            .po-item:hover {
                background-color: #f8f9ff;
                border-color: #3b82f6;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            }
            
            .po-item:active {
                transform: translateY(0);
            }
            
            #selectPOModal .modal-dialog {
                max-width: 800px;
            }
            
            #po-search {
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                padding: 0.75rem;
                font-size: 1rem;
            }
            
            #po-search:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            
            .list-group-item {
                border: 1px solid #e5e7eb;
                margin-bottom: 0.5rem;
            }
            
            .badge {
                font-size: 0.75rem;
                padding: 0.35rem 0.65rem;
            }

            /* Table responsive adjustments */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
        /* Full-width table styling */
                .table-card {
                    width: 100%;          /* ทำให้เต็มความกว้าง */
                    margin: 0;            /* ล้าง margin ที่ดันเข้าด้านใน */
                    padding: 0;
                    border-radius: 0;
                }

                .table-header {
                    width: 100%;
                    margin: 0;
                    padding: 1.5rem;
                }

                .table-body {
                    width: 100%;
                    margin: 0;
                    padding: 0 1.5rem;
                }

                    
            #receive-table {
                width: 100%;
                white-space: normal;
                margin-bottom: 0;
            }
            
            #receive-table th,
            #receive-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
                vertical-align: middle;
                border-bottom: 1px solid #e5e7eb;
            }
            
            #receive-table thead {
                background-color: #f3f4f6;
                border-bottom: 2px solid #d1d5db;
            }
            
            #receive-table tbody tr {
                transition: all 0.2s ease;
            }
            
            #receive-table tbody tr:hover {
                background-color: #f9fafb;
            }
            
            /* Define column widths for new order - percentage-based layout */
            #receive-table th:nth-child(1) { flex: 0 0 6.5%; } /* Image */
            #receive-table th:nth-child(2) { flex: 0 0 9.3%; } /* Barcode */
            #receive-table th:nth-child(3) { flex: 0 0 7.5%; } /* SKU */
            #receive-table th:nth-child(4) { flex: 0 0 12%; } /* Date */
            #receive-table th:nth-child(5) { flex: 0 0 9.3%; } /* Qty Changed */
            #receive-table th:nth-child(6) { flex: 0 0 10.3%; } /* Movement Type */
            #receive-table th:nth-child(7) { flex: 0 0 8.4%; } /* Latest Qty */
            #receive-table th:nth-child(8) { flex: 0 0 10.3%; } /* Expiry Date */
            #receive-table th:nth-child(9) { flex: 0 0 11.2%; } /* PO/Tag */
            #receive-table th:nth-child(10) { flex: 0 0 10.3%; } /* Created By */
            #receive-table th:nth-child(11) { flex: 1 1 auto; } /* Remark - flexible width */
            #receive-table th:nth-child(12) { flex: 0 0 6.5%; } /* Actions */
            
            /* ปรับ text overflow สำหรับข้อความยาว */
            #receive-table td:nth-child(11) { /* Remark */
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            /* Tooltip สำหรับข้อความที่ถูกตัด */
            #receive-table td[title] {
                cursor: help;
            }
            
            /* ปรับขนาดรูปภาพให้เล็กลง */
            .product-image {
                width: 48px !important; 
                height: 48px !important;
                max-width: 48px !important; 
                max-height: 48px !important;
            }
            
            /* ปรับขนาด badge */
            .badge {
                font-size: 0.8rem !important;
                padding: 0.35rem 0.65rem !important;
            }
            
            /* ปรับปุ่มจัดการ */
            .action-btn {
                padding: 0.375rem 0.5rem;
                font-size: 0.9rem;
            }
            
            .action-btn .material-icons {
                font-size: 1.2rem;
            }
            
            /* Mobile responsive adjustments */
            @media (max-width: 768px) {
                #receive-table {
                    width: 100%;
                }
                
                #receive-table th,
                #receive-table td {
                    padding: 0.5rem 0.25rem;
                    font-size: 0.85rem;
                }
                
                .product-image {
                    width: 32px !important; 
                    height: 32px !important;
                    max-width: 32px !important; 
                    max-height: 32px !important;
                }
                
                .badge {
                    font-size: 0.7rem !important;
                    padding: 0.25rem 0.4rem !important;
                }
            }
            
            @media (max-width: 480px) {
                #receive-table {
                    width: 100%;
                }
                
                #receive-table th,
                #receive-table td {
                    padding: 0.4rem 0.15rem;
                    font-size: 0.8rem;
                }
                
                .product-image {
                    width: 24px !important; 
                    height: 24px !important;
                    max-width: 24px !important; 
                    max-height: 24px !important;
                }
            }
            
            /* DataTable pagination and search styling */
            .dataTables_wrapper {
                padding: 0;
            }
            
            .dataTables_length,
            .dataTables_filter,
            .dataTables_info,
            .dataTables_paginate {
                margin: 0.5rem 0;
            }
            
            .dataTables_filter input {
                border: 1px solid #d1d5db;
                border-radius: 0.375rem;
                padding: 0.5rem 0.75rem;
                margin-left: 0.5rem;
                width: 250px;
            }
            
            .dataTables_filter input:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                outline: none;
            }
            
            /* Styles for issue transactions */
            .table-danger {
                --bs-table-bg: rgba(220, 53, 69, 0.05);
            }
            
            .qty-minus {
                font-weight: bold;
                color: #dc3545 !important;
            }
            
            .stats-danger {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                color: white;
            }
            
            .stats-danger .stats-icon {
                color: rgba(255, 255, 255, 0.8);
            }
            
            .stats-card.filter-card {
                cursor: pointer;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }

            .stats-card.filter-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(37, 99, 235, 0.12);
            }

            .stats-card.filter-card.active {
                outline: 3px solid rgba(59, 130, 246, 0.35);
                box-shadow: 0 16px 32px rgba(37, 99, 235, 0.2);
            }

            .status-filter-pill {
                display: inline-flex;
                align-items: center;
                padding: 0.35rem 0.85rem;
                border-radius: 999px;
                font-size: 0.85rem;
                background: #eff6ff;
                color: #1d4ed8;
                font-weight: 600;
                border: 1px solid rgba(59, 130, 246, 0.2);
                white-space: nowrap;
            }

            .dataTables_length select {
                border: 1px solid #d1d5db;
                border-radius: 0.375rem;
                padding: 0.25rem 0.5rem;
                margin: 0 0.5rem;
            }
            
            .dataTables_paginate .paginate_button {
                border: 1px solid #d1d5db !important;
                background: white !important;
                color: #374151 !important;
                padding: 0.5rem 0.75rem !important;
                margin: 0 0.125rem !important;
                border-radius: 0.375rem !important;
                transition: all 0.2s ease !important;
            }
            
            .dataTables_paginate .paginate_button:hover {
                background: #f3f4f6 !important;
                border-color: #9ca3af !important;
                color: #111827 !important;
            }
            
            .dataTables_paginate .paginate_button.current {
                background: #3b82f6 !important;
                border-color: #3b82f6 !important;
                color: white !important;
            }
            
            .dataTables_paginate .paginate_button.disabled {
                opacity: 0.5 !important;
                cursor: not-allowed !important;
            }
            
            .dataTables_info {
                color: #6b7280;
                font-size: 0.875rem;
            }
            
            .dataTables_processing {
                background: rgba(255, 255, 255, 0.9) !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 0.5rem !important;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
                color: #374151 !important;
                font-size: 0.875rem !important;
                padding: 1rem !important;
            }
            
            /* Custom search box styling */
            .search-box .input-group {
                min-width: 300px;
            }
            
            .search-box .input-group-text {
                background-color: #f8f9fa;
                border-color: #d1d5db;
                color: #6b7280;
            }
            
            .search-box .form-control {
                border-color: #d1d5db;
            }
            
            .search-box .form-control:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            
            .table-actions {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            @media (max-width: 768px) {
                .search-box .input-group {
                    min-width: 250px;
                }
                
                .table-actions {
                    flex-direction: column;
                    align-items: stretch;
                }
            }
            
            /* Quantity Split Modal Styles */
            #quantitySplitModal .modal-dialog {
                max-width: 1000px;
            }
            
            .additional-po-row {
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 1rem;
                background-color: #f8f9fa;
            }
            
            .additional-po-item {
                transition: all 0.2s ease;
                cursor: pointer;
            }
            
            .additional-po-item:hover {
                background-color: #f8f9ff;
                border-color: #3b82f6;
            }
            
            .quantity-summary-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            .split-status {
                font-size: 0.9rem;
                padding: 0.5rem 1rem;
                border-radius: 1rem;
            }
            
            .split-main-po {
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            }
            
            .split-additional-po {
                background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            }
            
            @media (max-width: 768px) {
                #quantitySplitModal .modal-dialog {
                    max-width: 95%;
                    margin: 1rem;
                }
                
                .additional-po-row {
                    padding: 0.5rem;
                }
                
                .additional-po-row .col-md-4 {
                    margin-bottom: 0.5rem;
                }
            }
            
            /* Action Buttons Styling */
            .action-btn {
                padding: 0.375rem 0.5rem;
                font-size: 0.875rem;
                line-height: 1;
                border-radius: 0.375rem;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 2.25rem;
                height: 2.25rem;
            }
            
            .action-btn .material-icons {
                font-size: 1rem !important;
                line-height: 1;
            }
            
            .action-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            .action-btn:active {
                transform: translateY(0);
            }
            
            .btn-outline-primary:hover {
                background-color: #3b82f6;
                border-color: #3b82f6;
                color: white;
            }
            
            .btn-outline-danger:hover {
                background-color: #dc3545;
                border-color: #dc3545;
                color: white;
            }
            
            .btn-outline-secondary:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            
            .btn-group .action-btn:first-child {
                border-top-right-radius: 0;
                border-bottom-right-radius: 0;
                border-right: 0;
            }
            
            .btn-group .action-btn:last-child {
                border-top-left-radius: 0;
                border-bottom-left-radius: 0;
                border-left: 1px solid rgba(0, 0, 0, 0.125);
            }
            
            .btn-group .action-btn:not(:first-child):not(:last-child) {
                border-radius: 0;
                border-left: 1px solid rgba(0, 0, 0, 0.125);
                border-right: 0;
            }
            
            /* Tooltip styling */
            .tooltip {
                font-size: 0.75rem;
            }
            
            .tooltip-inner {
                background-color: #1f2937;
                color: white;
                padding: 0.375rem 0.75rem;
                border-radius: 0.375rem;
            }
            
            /* Animation for buttons */
            @keyframes buttonPulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .action-btn:focus {
                animation: buttonPulse 0.3s ease;
                outline: none;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
            }
            
            .btn-outline-danger:focus {
                box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.25);
            }
            
            /* PO Badge Styling */
            .badge.bg-primary {
                background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%) !important;
                font-weight: 500;
                letter-spacing: 0.5px;
                padding: 0.35rem 0.65rem;
                border-radius: 0.375rem;
            }

            /* Field accents for quick scanning */
            .field-accent { font-weight: 600; }
            .field-purchase { color: #0d6efd; }
            .field-weight { color: #0f766e; }
            .field-cost { color: #7c3aed; }
            .field-sale { color: #d97706; }
            .field-input-purchase { color: #0d6efd; font-weight: 600; }
            .field-input-weight { color: #0f766e; font-weight: 600; }
            .field-input-cost { color: #7c3aed; font-weight: 700; }
            .field-input-sale { color: #d97706; font-weight: 600; }
            
            .badge.bg-primary:hover {
                transform: scale(1.05);
                transition: transform 0.2s ease;
            }
            
            /* PO Column styling */
            .po-info {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
                min-height: 2rem;
                align-items: flex-start;
            }
            
            .po-remark {
                font-size: 0.7rem;
                color: #6b7280;
                font-style: italic;
                word-break: break-word;
                max-width: 120px;
                line-height: 1.2;
                padding: 0.1rem 0.3rem;
                background-color: #f8f9fa;
                border-radius: 0.25rem;
                border-left: 3px solid #dee2e6;
            }
            
            .po-badge-icon {
                font-size: 0.75rem !important;
                vertical-align: middle;
                margin-right: 0.25rem;
            }
            
            .badge.bg-primary {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
            }
            
            /* Issue tag styling for better contrast */
            .issue-tag-info {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
                align-items: flex-start;
            }
            
            .issue-tag-badge {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
                color: white;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
                padding: 0.35rem 0.65rem;
                border-radius: 0.375rem;
            }
</style>

<?php include '../templates/sidebar.php'; ?>
<div class="mainwrap">
    <div class="container-fluid py-4">
        

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #3b82f6;">swap_horiz</span>
                    ความเคลื่อนไหวสินค้า
                </h1>
                <p class="text-muted mb-0">ประวัติการรับและออกสินค้า รวมถึงการปรับปรุงสต็อกทั้งหมด</p>
            </div>
            <div>
                <button class="btn-modern btn-modern-success" onclick="window.location.href='receive_product.php'">
                    <span class="material-icons" style="font-size: 1.25rem;">add_box</span>
                    รับสินค้าใหม่
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-primary filter-card active" data-filter="all" role="button" tabindex="0" aria-pressed="true">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รายการทั้งหมด</div>
                                <div class="stats-value"><?= count($rows) ?></div>
                                <div class="stats-subtitle">ความเคลื่อนไหว</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">swap_horiz</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-success filter-card" data-filter="receive" role="button" tabindex="0" aria-pressed="false">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รายการรับ</div>
                                <div class="stats-value"><?= count(array_filter($rows, fn($r) => ($r['transaction_type'] ?? '') === 'receive')) ?></div>
                                <div class="stats-subtitle">รับเข้าสต็อก</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">add_circle</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-danger filter-card" data-filter="issue" role="button" tabindex="0" aria-pressed="false">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รายการออก</div>
                                <div class="stats-value"><?= count(array_filter($rows, fn($r) => ($r['transaction_type'] ?? '') === 'issue')) ?></div>
                                <div class="stats-subtitle">ออกจากสต็อก</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">remove_circle</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-info filter-card" data-filter="today" role="button" tabindex="0" aria-pressed="false">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">วันนี้</div>
                                <div class="stats-value"><?= count(array_filter($rows, fn($r) => date('Y-m-d', strtotime($r['created_at'] ?? '')) === date('Y-m-d'))) ?></div>
                                <div class="stats-subtitle">รายการวันนี้</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">today</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table -->
      <div class="row mb-4">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons">table_view</span>
                        รายการรับสินค้า (<?= count($rows) ?> รายการ)
                    </h5>
                    <div class="table-actions d-flex align-items-center">
                        <div class="search-box me-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <span class="material-icons" style="font-size: 1rem;">search</span>
                                </span>
                                <input type="text" class="form-control" id="custom-search" placeholder="ค้นหาในตาราง...">
                            </div>
                        </div>
                        <span id="status-filter-label" class="status-filter-pill me-3">แสดง: ทั้งหมด</span>
                        <button class="btn-modern btn-modern-secondary btn-sm refresh-table" onclick="refreshTableData()">
                            <span class="material-icons">refresh</span>
                            รีเฟรช
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-body">
             

                <div class="table-responsive">
                    <table id="receive-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 6.5%;">รูปภาพ</th>
                            <th style="width: 9.3%;">บาร์โค้ด</th>
                            <th style="width: 7.5%;">SKU</th>
                            <th style="width: 12%;">วันที่ทำรายการ</th>
                            <th style="width: 9.3%;">จำนวนที่เคลื่อนไหว</th>
                            <th style="width: 10.3%;">ประเภทการเคลื่อนไหว</th>
                            <th style="width: 8.4%;">จำนวนล่าสุด</th>
                            <th style="width: 10.3%;">วันหมดอายุ</th>
                            <th style="width: 11.2%;">PO / แท็กอ้างอิง</th>
                            <th style="width: 10.3%;">ชื่อผู้ดำเนินการ</th>
                            <th style="flex: 1 1 auto;">หมายเหตุ / การแก้ไขเพิ่มเติม</th>
                            <th class="no-sort text-center" style="width: 6.5%;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-4">
                            <div class="d-flex flex-column align-items-center">
                                <span class="material-icons mb-2" style="font-size: 3rem; color: #d1d5db;">receipt</span>
                                <h5 class="text-muted">ไม่พบข้อมูลการรับสินค้า</h5>
                                <p class="text-muted mb-0">ยังไม่มีการรับสินค้าเข้าสต็อก</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($rows as $row): ?>
                        <?php
                            $weightKg = isset($row['remark_weight']) ? (float)$row['remark_weight'] : 0.0;
                            $baseCost = isset($row['price_per_unit']) ? (float)$row['price_per_unit'] : 0.0;
                            $weightCost = $weightKg > 0 ? $weightKg * 850 : 0;
                            $trueCost = $baseCost + $weightCost;
                        ?>
                        <tr data-id="<?= $row['transaction_id'] ?>" 
                            data-quantity="<?= abs($row['quantity'] ?? 0) ?>"
                            data-quantity-type="<?= ($row['quantity_direction'] ?? 'plus') ?>"
                            data-location="<?= htmlspecialchars($row['location_desc'] ?? '') ?>"
                            data-expiry="<?= isset($row['expiry_date']) && $row['expiry_date'] ? date('Y-m-d', strtotime($row['expiry_date'])) : '' ?>"
                            data-po-number="<?= htmlspecialchars($row['po_number'] ?? '') ?>"
                            data-weight="<?= $weightKg ?>"
                            data-base-cost="<?= $baseCost ?>"
                            data-true-cost="<?= $trueCost ?>"
                            data-transaction-type="<?= htmlspecialchars($row['transaction_type']) ?>"
                            data-created-date="<?= date('Y-m-d', strtotime($row['created_at'])) ?>"
                            class="<?= ($row['quantity_direction'] ?? 'plus') === 'minus' ? 'table-danger' : '' ?>">
                            <!-- 1. Image -->
                            <td>
                                <?php $image_path = getImagePath($row['image'] ?? ''); ?>
                                <img src="<?= htmlspecialchars($image_path) ?>" 
                                     alt="<?= htmlspecialchars($row['product_name'] ?? '') ?>" 
                                     class="product-image" 
                                     onerror="this.src='../images/noimg.png';">
                            </td>

                            <!-- 2. Barcode -->
                            <td>
                                <span class="fw-bold" title="<?= htmlspecialchars($row['barcode']) ?>">
                                    <?= htmlspecialchars($row['barcode']) ?>
                                </span>
                            </td>

                            <!-- 3. SKU -->
                            <td>
                                <span class="fw-bold text-secondary" title="<?= htmlspecialchars($row['sku']) ?>">
                                    <?= htmlspecialchars($row['sku']) ?>
                                </span>
                            </td>

                            <!-- 4. Date -->
                            <td data-order="<?= strtotime($row['created_at']) ?>">
                                <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                            </td>

                            <!-- 5. Quantity Changed -->
                            <td class="text-center">
                                <?php if (($row['quantity_direction'] ?? 'plus') === 'minus'): ?>
                                    <span class="qty-minus text-danger fw-bold">-<?= formatQuantity($row['quantity_display'] ?? 0) ?></span>
                                <?php else: ?>
                                    <span class="qty-plus text-success fw-bold"><?= formatQuantity($row['quantity_display'] ?? 0) ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- 6. Movement Type -->
                            <td class="text-center">
                                <?php if (($row['quantity_direction'] ?? 'plus') === 'minus'): ?>
                                    <span class="badge bg-danger" title="ลดสต็อก">
                                        <span class="material-icons" style="font-size: 0.85rem; vertical-align: middle;">trending_down</span>
                                        ลด
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success" title="เพิ่มสต็อก">
                                        <span class="material-icons" style="font-size: 0.85rem; vertical-align: middle;">trending_up</span>
                                        เพิ่ม
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- 7. Latest Quantity -->
                            <td class="text-center">
                                <span class="fw-bold text-primary">
                                    <?= formatQuantity(max($row['running_qty'] ?? 0, 0)) ?>
                                </span>
                            </td>

                            <!-- 8. Expiry Date -->
                            <td class="text-center">
                                <?php if (isset($row['expiry_date']) && $row['expiry_date']): ?>
                                    <span class="badge bg-light text-dark" title="วันหมดอายุ">
                                        <span class="material-icons" style="font-size: 0.75rem; vertical-align: middle;">calendar_today</span>
                                        <?= date('d/m/Y', strtotime($row['expiry_date'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 9. PO / Reference Tag -->
                            <td>
                                <?php if ($row['transaction_type'] === 'issue'): ?>
                                    <?php if (!empty($row['issue_tag'])): ?>
                                        <div class="issue-tag-info">
                                            <span class="badge issue-tag-badge" title="เลขแท็กการขาย">
                                                <span class="material-icons po-badge-icon">local_offer</span>
                                                <?= htmlspecialchars($row['issue_tag']) ?>
                                            </span>
                                            <?php if (!empty($row['platform'])): ?>
                                                <span class="badge bg-secondary" title="แพลตฟอร์มการขาย" style="display: block; margin-top: 0.25rem;">
                                                    <span class="material-icons po-badge-icon">storefront</span>
                                                    <?= htmlspecialchars($row['platform']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted" title="รายการออกสินค้าไม่มีแท็ก">
                                            <span class="material-icons po-badge-icon">remove_shopping_cart</span>
                                            รายการออก
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="po-info">
                                        <?php if (!empty($row['po_number'])): ?>
                                            <span class="badge bg-primary" title="เลขใบสั่งซื้อ">
                                                <span class="material-icons po-badge-icon">description</span>
                                                <?= htmlspecialchars($row['po_number']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted">
                                                <span class="material-icons po-badge-icon">remove</span>
                                                ไม่มี PO
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- 10. Created By -->
                            <td>
                                <span title="<?= htmlspecialchars($row['created_by'] ?? 'ไม่ระบุ') ?>">
                                    <?= htmlspecialchars($row['created_by'] ?? 'ไม่ระบุ') ?>
                                </span>
                            </td>

                            <!-- 11. Remark / Additional Info -->
                            <td>
                                <?php
                                    $rawRemark = isset($row['remark']) ? trim((string)$row['remark']) : '';
                                    if ($rawRemark === '' && ($row['transaction_type'] ?? '') === 'receive' && !empty($row['po_remark'])) {
                                        $rawRemark = trim((string)$row['po_remark']);
                                    }

                                    $remarkPreview = $rawRemark;
                                    $shortened = false;
                                    if ($rawRemark !== '') {
                                        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                                            if (mb_strlen($rawRemark) > 80) {
                                                $remarkPreview = mb_substr($rawRemark, 0, 80);
                                                $shortened = true;
                                            }
                                        } else {
                                            if (strlen($rawRemark) > 80) {
                                                $remarkPreview = substr($rawRemark, 0, 80);
                                                $shortened = true;
                                            }
                                        }
                                    }
                                ?>
                                <?php if ($rawRemark !== ''): ?>
                                    <small class="text-muted d-block" title="<?= htmlspecialchars($rawRemark) ?>">
                                        <span class="material-icons" style="font-size: 0.75rem; vertical-align: middle;">
                                            <?= ($row['transaction_type'] ?? '') === 'issue' ? 'receipt_long' : 'note' ?>
                                        </span>
                                        <?= htmlspecialchars($remarkPreview) ?><?= $shortened ? '...' : '' ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 12. Actions -->
                            <td class="text-center">
                                <?php if ($row['transaction_type'] === 'receive'): ?>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-btn action-btn" 
                                                data-id="<?= $row['transaction_id'] ?>"
                                                title="แก้ไขรายการ">
                                            <span class="material-icons" style="font-size: 1rem;">edit</span>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="รายการออกสินค้า">
                                            <span class="material-icons" style="font-size: 1rem;">remove_shopping_cart</span>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div> <!-- Close table-responsive -->
            </div>
        </div>
    </div>
</div>
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="../assets/modern-table.js"></script>

<script>
// Global variable for table instance
let receiveTable;

$(document).ready(function() {
    console.log('Document ready, initializing table...');
    console.log('jQuery version:', $.fn.jquery);
    console.log('DataTable available:', typeof $.fn.DataTable);
    console.log('Found table element:', $('#receive-table').length);

    const todayString = '<?= date('Y-m-d'); ?>';
    let statusFilter = 'all';
    const statusFilterLabels = {
        all: 'แสดง: ทั้งหมด',
        receive: 'แสดง: รายการรับ',
        issue: 'แสดง: รายการออก',
        today: 'แสดง: รายการวันนี้'
    };

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (!settings.nTable || settings.nTable.id !== 'receive-table') {
            return true;
        }

        if (statusFilter === 'all') {
            return true;
        }

        const rowData = settings.aoData[dataIndex] || {};
        const rowNode = rowData.nTr || null;

        if (!rowNode) {
            return true;
        }

        const $row = $(rowNode);

        if (statusFilter === 'receive' || statusFilter === 'issue') {
            return $row.data('transaction-type') === statusFilter;
        }

        if (statusFilter === 'today') {
            return $row.data('created-date') === todayString;
        }

        return true;
    });
    
    function updateTrueCostDisplay() {
        const weightVal = parseFloat($('#edit-weight').val()) || 0;
        const baseCostVal = parseFloat($('#edit-price-cost').val()) || 0;
        const trueCost = baseCostVal + (weightVal * 850);
        // true-cost display removed; keep computation for potential future use
    }

    // Function to bind action button events (delete functionality removed)
    window.bindEditButtonEvents = function() {
        console.log('Binding action button events...');
        const editButtons = $('.edit-btn');
        console.log('Found edit buttons:', editButtons.length);
        
        // Remove any existing handlers first
        editButtons.off('click.editHandler');
        
        // Bind edit button handlers
        editButtons.on('click.editHandler', function(e){
            e.preventDefault();
            e.stopPropagation();
            console.log('Edit button clicked for ID:', $(this).data('id'));
            if (window.handleEditButtonClick) {
                window.handleEditButtonClick($(this));
            } else {
                console.error('handleEditButtonClick function not found');
            }
        });
        
        console.log('Action button events bound - Edit:', editButtons.length);
    };
    
    // Destroy existing DataTable if any before initializing
    if ($.fn.DataTable.isDataTable('#receive-table')) {
        $('#receive-table').DataTable().destroy();
    }
    
    // Initialize receive items table with DataTable directly (not ModernTable)
    receiveTable = $('#receive-table').DataTable({
        pageLength: 25,
        language: {
            "decimal": "",
            "emptyTable": "ไม่มีข้อมูลในตาราง",
            "info": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
            "infoEmpty": "แสดง 0 ถึง 0 จาก 0 รายการ",
            "infoFiltered": "(กรองจากทั้งหมด _MAX_ รายการ)",
            "lengthMenu": "แสดง _MENU_ รายการต่อหน้า",
            "loadingRecords": "กำลังโหลด...",
            "processing": "กำลังประมวลผล...",
            "search": "ค้นหา:",
            "zeroRecords": "ไม่พบรายการที่ตรงกัน",
            "paginate": {
                "first": "หน้าแรก",
                "last": "หน้าสุดท้าย", 
                "next": "ถัดไป",
                "previous": "ก่อนหน้า"
            }
        },
        columnDefs: [
            { orderable: false, targets: 'no-sort' },
            { className: "text-center", targets: 'text-center' }
        ],
        order: [[3, 'desc']], // Sort by created date (latest first)
        scrollX: true,
        scrollCollapse: true,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "ทั้งหมด"]],
        responsive: true,
        processing: true,
        drawCallback: function(settings) {
            console.log('DataTable drawCallback triggered');
            // Re-initialize tooltips after each draw
            $('[title]').tooltip();
            
            // Initialize tooltips for action buttons
            $('.action-btn[title]').tooltip({
                placement: 'top',
                trigger: 'hover'
            });
            
            // Re-bind edit button events after pagination/search
            setTimeout(function() {
                window.bindEditButtonEvents();
            }, 100);
        },
        initComplete: function() {
            console.log('DataTable initialized successfully');
            // Initial binding
            window.bindEditButtonEvents();
            
            // Initialize tooltips for action buttons
            $('.action-btn[title]').tooltip({
                placement: 'top',
                trigger: 'hover'
            });
        }
    });
    
    const $statusFilterLabel = $('#status-filter-label');

    function updateStatusFilterLabel(filterKey) {
        const label = statusFilterLabels[filterKey] || statusFilterLabels.all;
        $statusFilterLabel.text(label);
    }

    function applyStatusFilter(filterKey) {
        statusFilter = filterKey || 'all';
        $('.stats-card.filter-card').removeClass('active').attr('aria-pressed', 'false');
        const $card = $(`.stats-card.filter-card[data-filter="${statusFilter}"]`);
        if ($card.length) {
            $card.addClass('active').attr('aria-pressed', 'true');
        }
        updateStatusFilterLabel(statusFilter);
        if (receiveTable && typeof receiveTable.draw === 'function') {
            receiveTable.draw();
        }
    }

    $(document).on('click', '.stats-card.filter-card', function() {
        const filterKey = $(this).data('filter') || 'all';
        applyStatusFilter(filterKey);
    });

    $(document).on('keydown', '.stats-card.filter-card', function(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const filterKey = $(this).data('filter') || 'all';
            applyStatusFilter(filterKey);
        }
    });

    updateStatusFilterLabel(statusFilter);

    // Fallback event handler using event delegation
    $(document).on('click', '.edit-btn', function(e) {
        e.preventDefault();
        console.log('Fallback edit handler triggered for ID:', $(this).data('id'));
        if (window.handleEditButtonClick) {
            window.handleEditButtonClick($(this));
        }
    });
    
    // Initialize tooltips for truncated text and action buttons
    $('[title]').tooltip();
    $('.action-btn[title]').tooltip({
        placement: 'top',
        trigger: 'hover'
    });

    // Custom search functionality
    $('#custom-search').on('keyup', function() {
        console.log('Custom search triggered:', this.value);
        if (receiveTable && typeof receiveTable.search === 'function') {
            receiveTable.search(this.value).draw();
        } else {
            console.error('receiveTable.search is not available');
        }
    });
    
    // Also listen for input event for better responsiveness
    $('#custom-search').on('input', function() {
        if (receiveTable && typeof receiveTable.search === 'function') {
            receiveTable.search(this.value).draw();
        }
    });

    // price inputs are read-only; no need to recompute true cost on change

    // Hide default DataTable search box
    setTimeout(function() {
        $('.dataTables_filter').hide();
    }, 100);

    // Batch delete functionality removed for security reasons
    /* 
    $('#delete-selected').off('click').on('click', function(){
        // Batch delete handler removed
    });
    */

    // Function to handle edit button click (make it global)
    window.handleEditButtonClick = function($button) {
        console.log('handleEditButtonClick called with button:', $button);
        let row = $button.closest('tr'); 
        let id = $button.data('id');
        console.log('Edit button ID:', id);
        console.log('Row found:', row.length);
        
        if (!id) {
            console.error('No ID found on edit button');
            Swal.fire('ข้อผิดพลาด', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }
        
        // ✅ FIX: Use data attributes instead of .eq() to avoid DataTables column hiding issues
        let qty = row.data('quantity') || 0;
        let qtyType = row.data('quantity-type') || 'plus';
        let locationText = row.data('location') || '';
        const expiry = row.data('expiry') || '';
        let poNumber = row.data('po-number') || '';
        const weightRaw = row.data('weight');
        const weightKg = weightRaw !== undefined ? parseFloat(weightRaw) : 0;
        const baseCost = parseFloat(row.data('base-cost')) || 0;
        const trueCost = parseFloat(row.data('true-cost')) || 0;
        
        console.log('Extracted data from data attributes:', {
            id, qty, qtyType, expiry, poNumber, locationText
        });
        
        // ใส่ค่าเริ่มต้นใน modal ก่อน
        $('#edit-receive-id').val(id);
        $('#edit-qty-type').val(qtyType);
        $('#edit-receive-qty').val(qty);
        $('#edit-expiry-date').val(expiry);
        $('#edit-po-number').val(poNumber);
        // clear select และราคาก่อน
        $('#edit-row-code').val('');
        $('#edit-bin').val('');
        $('#edit-shelf').val('');
        $('#edit-price-cost').val('');
        $('#edit-price-sale').val('');
        $('#edit-weight').val(weightRaw !== undefined && weightRaw !== null ? weightRaw : '');
        $('#edit-true-cost').val(trueCost ? trueCost.toFixed(2) : '');
        updateTrueCostDisplay();
        $('#edit-remark').val('');
        
        // ล้างข้อมูลการแบ่งจำนวนก่อนหน้า
        window.currentSplitData = null;
        window.additionalPOs = [];
        if (window.splitMainPO) {
            window.splitMainPO = null;
        }
        
        // AJAX ไปหา row_code, bin, shelf, ราคา และข้อมูลอื่นๆ
        $.get('../api/receive_position_api.php', { receive_id: id }, function(resp){
            console.log('Position API response:', resp);
            if(resp && resp.success) {
                let rowCode = resp.row_code || '';
                let bin = resp.bin || '';
                let shelf = resp.shelf || '';
                let priceCost = resp.price_per_unit || '';
                let priceSale = resp.sale_price || '';
                let remarkFromAPI = resp.remark || '';
                let expiryFromAPI = resp.expiry_date || '';
                let weightFromAPI = resp.remark_weight || '';
                
                function setSelectWithDynamicOption(sel, val) {
                    val = (val || '').toString().trim();
                    if(val && sel.find('option[value="'+val+'"]').length === 0) {
                        sel.append('<option value="'+val+'">'+val+'</option>');
                    }
                    sel.val(val).trigger('change');
                }
                setSelectWithDynamicOption($('#edit-row-code'), rowCode);
                setSelectWithDynamicOption($('#edit-bin'), bin);
                setSelectWithDynamicOption($('#edit-shelf'), shelf);
                
                // ใส่ราคาและข้อมูลอื่นๆ ที่ได้จาก API
                $('#edit-price-cost').val(priceCost);
                $('#edit-price-sale').val(priceSale);
                if (weightFromAPI !== '') {
                    $('#edit-weight').val(weightFromAPI);
                }
                updateTrueCostDisplay();
                $('#edit-remark').val(remarkFromAPI);
                
                // ⚠️ ไม่อัพเดทวันหมดอายุจาก API
                // เพื่อให้กรอกข้อมูลใหม่ทุกครั้งที่รับสินค้า (ต่างล็อต)
                // ทำให้แต่ละล็อตมีวันหมดอายุเป็นของตัวเอง
                // if (expiryFromAPI) {
                //     $('#edit-expiry-date').val(expiryFromAPI);
                // }
                
                console.log('Form values set:', {
                    priceCost: $('#edit-price-cost').val(),
                    priceSale: $('#edit-price-sale').val(),
                    remark: $('#edit-remark').val(),
                    expiry: $('#edit-expiry-date').val(),
                    qty: $('#edit-receive-qty').val(),
                    qtyType: $('#edit-qty-type').val(),
                    poNumber: $('#edit-po-number').val(),
                    rowCode: $('#edit-row-code').val(),
                    bin: $('#edit-bin').val(),
                    shelf: $('#edit-shelf').val()
                });
            }
            var modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }, 'json').fail(function(xhr, status, error) {
            console.error('Position API error:', xhr, status, error);
            Swal.fire('ข้อผิดพลาด', 'ไม่สามารถโหลดข้อมูลได้', 'error');
            // แสดง modal แม้ว่าจะโหลดข้อมูลไม่ได้
            var modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        });
    };

    // ปุ่มเปลี่ยน PO
    $(document).off('click', '#change-po-btn').on('click', '#change-po-btn', function(){
        let receiveId = $('#edit-receive-id').val();
        if (!receiveId) {
            Swal.fire('ข้อผิดพลาด', 'ไม่พบ ID รายการรับสินค้า', 'error');
            return;
        }
        
        // Get product info from current receive item
        loadPOList(receiveId);
        
        var selectPOModal = new bootstrap.Modal(document.getElementById('selectPOModal'));
        selectPOModal.show();
    });

    // ค้นหา PO
    $(document).off('input', '#po-search').on('input', '#po-search', function(){
        let searchTerm = $(this).val();
        filterPOList(searchTerm);
    });

    // เลือก PO
    $(document).off('click', '.po-item').on('click', '.po-item', function(){
        let poId = $(this).data('po-id');
        let itemId = $(this).data('item-id');
        let poNumber = $(this).data('po-number');
        let unitCost = $(this).data('unit-cost');
        let remainingQty = $(this).data('remaining-qty');
        let currentReceiveQty = parseInt($('#edit-receive-qty').val()) || 0;
        
        // ตรวจสอบว่าจำนวนที่จะรับมากกว่าจำนวนที่เหลือใน PO หรือไม่
        if (Math.abs(currentReceiveQty) > remainingQty && remainingQty > 0) {
            // แสดง modal สำหรับแบ่งจำนวน
            showQuantitySplitModal(poId, itemId, poNumber, unitCost, remainingQty, Math.abs(currentReceiveQty));
        } else {
            // เปลี่ยน PO ปกติ
            updatePOSelection(poId, itemId, poNumber, unitCost);
        }
    });
    
    // ฟังก์ชันอัพเดต PO ที่เลือก
    function updatePOSelection(poId, itemId, poNumber, unitCost) {
        $('#edit-po-id').val(poId);
        $('#edit-item-id').val(itemId);
        $('#edit-po-number').val(poNumber);
        $('#edit-price-cost').val(unitCost);
        
        // Close modal
        var selectPOModal = bootstrap.Modal.getInstance(document.getElementById('selectPOModal'));
        selectPOModal.hide();
        
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'เปลี่ยน PO สำเร็จ',
            text: poNumber,
            showConfirmButton: false,
            timer: 2000
        });
    }
    
    // ฟังก์ชันแสดง modal สำหรับแบ่งจำนวน
    function showQuantitySplitModal(mainPoId, mainItemId, mainPoNumber, mainUnitCost, availableQty, totalQty) {
        // ซ่อน PO selection modal ก่อน
        var selectPOModal = bootstrap.Modal.getInstance(document.getElementById('selectPOModal'));
        selectPOModal.hide();
        
        // เก็บจำนวนเดิมไว้ก่อนทำการแบ่ง (สำคัญ!)
        window.originalQtyBeforeSplit = Math.abs(totalQty);
        
        // เตรียมข้อมูลสำหรับ split modal
        $('#split-main-po-number').text(mainPoNumber);
        $('#split-available-qty').text(availableQty);
        $('#split-total-qty').text(totalQty);
        $('#split-excess-qty').text(totalQty - availableQty);
        
        // เซ็ตค่าเริ่มต้น
        $('#split-main-qty').val(availableQty);
        $('#split-main-qty').attr('max', Math.min(availableQty, totalQty));
        
        // เก็บข้อมูล PO หลัก
        window.splitMainPO = {
            poId: mainPoId,
            itemId: mainItemId,
            poNumber: mainPoNumber,
            unitCost: mainUnitCost,
            availableQty: availableQty
        };
        
        // ล้างรายการ PO เพิ่มเติม
        $('#additional-po-list').empty();
        window.additionalPOs = [];
        
        // แสดง modal
        var splitModal = new bootstrap.Modal(document.getElementById('quantitySplitModal'));
        splitModal.show();
    }

    // บันทึกการแก้ไข (unbind existing handlers first)
    $('#save-edit').off('click').on('click', function(){
            // ป้องกันกดซ้ำ
            let $btn = $(this);
            if ($btn.prop('disabled')) return;
            $btn.prop('disabled', true);

            // Validate
            let qty = parseInt($('#edit-receive-qty').val()) || 0;
            let priceCost = parseFloat($('#edit-price-cost').val()) || 0;
            let priceSale = parseFloat($('#edit-price-sale').val()) || 0;
            let remark = $('#edit-remark').val().trim();
            if (isNaN(qty) || $('#edit-receive-qty').val() === '') {
                Swal.fire('กรุณากรอกจำนวน', '', 'warning'); $btn.prop('disabled', false); return;
            }
            // ราคาและน้ำหนักไม่ให้แก้ไขแล้ว จึงไม่ต้อง validate ช่องราคา

            // ปรับจำนวนตามประเภท
            let qtyType = $('#edit-qty-type').val();
            if(qtyType === 'minus') qty = -Math.abs(qty);
            else qty = Math.abs(qty);
            $('#edit-receive-qty').val(qty);

            // รวมตำแหน่งเป็น description ด้วย (optionally ส่งไป backend)
            let rowCode = $('#edit-row-code').val();
            let bin = $('#edit-bin').val();
            let shelf = $('#edit-shelf').val();
            if($('#edit-form input[name="location_desc"]').length === 0){
                $('#edit-form').append('<input type="hidden" name="location_desc" id="edit-location-desc">');
            }
            let locDesc = rowCode && bin && shelf ? `${rowCode}-${bin}-${shelf}` : '';
            $('#edit-location-desc').val(locDesc);

            let formData = $('#edit-form').serialize();
            
            // Debug: Log form data being sent
            console.log('=== FORM DATA BEING SENT ===');
            console.log('Form serialized data:', formData);
            console.log('Expiry date field value:', $('#edit-expiry-date').val());
            console.log('Expiry date field exists:', $('#edit-expiry-date').length > 0);
            console.log('Expiry date field name attr:', $('#edit-expiry-date').attr('name'));
            
            // เพิ่มข้อมูลการแบ่งจำนวน (ถ้ามี)
            if (window.currentSplitData) {
                formData += '&split_data=' + encodeURIComponent(JSON.stringify(window.currentSplitData));
                console.log('Split data added:', window.currentSplitData);
            }
            
            Swal.fire({
                title: 'กำลังบันทึก...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            $.post('receive_edit.php', formData, function(resp){
                $btn.prop('disabled', false);
                 if(resp.success){
                        Swal.fire({
                            icon: 'success',
                            title: 'บันทึกสำเร็จ',
                            text: 'บันทึกการแก้ไขเรียบร้อย',
                            showConfirmButton: false,
                            timer: 2000   // 2 วิ
                        }).then(() => {
                            refreshTableData();
                        });

                        var modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
                        modal.hide();
                }else{
                    Swal.fire('ผิดพลาด', resp.message || 'ไม่สามารถบันทึกได้', 'error');
                }
            },'json').fail(function(xhr){
                $btn.prop('disabled', false);
                Swal.fire('ผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
            });
        });

    // Delete functionality removed for security reasons
    /*
    $(document).on('click', '.delete-btn', function(e) {
        // Delete handler removed
    });
    */

    // Function to refresh table data without full page reload
    function refreshTableData() {
        // Since this is a server-side rendered table, we need to reload the page
        // But first, show loading indicator
        Swal.fire({
            title: 'กำลังรีเฟรชข้อมูล...',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => { Swal.showLoading(); }
        });
        
        // Use setTimeout to ensure loading shows before reload
        setTimeout(() => {
            location.reload();
        }, 300);
    }

    // ฟังก์ชันโหลดรายการ PO
    function loadPOList(receiveId) {
        $('#po-loading').show();
        $('#po-list').empty();
        $('#no-po-found').hide();
        $('#po-search').val('');
        
        $.ajax({
            url: '../api/get_po_for_product.php',
            method: 'POST',
            data: { receive_id: receiveId },
            dataType: 'json',
            success: function(response) {
                $('#po-loading').hide();
                
                if (response.success && response.data && response.data.length > 0) {
                    displayPOList(response.data);
                } else {
                    $('#no-po-found').show();
                }
            },
            error: function(xhr, status, error) {
                $('#po-loading').hide();
                console.error('Error loading PO list:', error);
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถโหลดรายการ PO ได้', 'error');
            }
        });
    }

    // ฟังก์ชันแสดงรายการ PO
    function displayPOList(poList) {
        let html = '';
        
        poList.forEach(function(po) {
            const statusBadge = getStatusBadge(po.po_status);
            const remainingQty = po.ordered_qty - po.received_qty;
            
            html += `
                <div class="list-group-item po-item" style="cursor: pointer;" 
                     data-po-id="${po.po_id}"
                     data-item-id="${po.item_id}"
                     data-po-number="${po.po_number}"
                     data-unit-cost="${po.unit_cost}"
                     data-remaining-qty="${remainingQty}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 fw-bold text-primary">${po.po_number}</h6>
                                ${statusBadge}
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">ผู้ขาย:</small>
                                <span class="fw-medium">${po.supplier_name}</span>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">วันที่สั่ง:</small>
                                    <div class="small">${formatDate(po.order_date)}</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">ราคา/หน่วย:</small>
                                    <div class="small fw-bold text-success">${parseFloat(po.unit_cost).toFixed(2)} ฿</div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-4">
                                    <small class="text-muted">สั่งซื้อ:</small>
                                    <div class="small fw-bold text-info">${po.ordered_qty}</div>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">รับแล้ว:</small>
                                    <div class="small fw-bold text-success">${po.received_qty}</div>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">คงเหลือ:</small>
                                    <div class="small fw-bold text-warning">${remainingQty}</div>
                                </div>
                            </div>
                        </div>
                        <div class="ms-2">
                            <span class="material-icons text-primary">chevron_right</span>
                        </div>
                    </div>
                </div>
            `;
        });
        
        $('#po-list').html(html);
        window.allPOList = poList; // เก็บไว้สำหรับการค้นหา
    }

    // ฟังก์ชันกรองรายการ PO
    function filterPOList(searchTerm) {
        if (!window.allPOList) return;
        
        searchTerm = searchTerm.toLowerCase().trim();
        
        if (!searchTerm) {
            displayPOList(window.allPOList);
            return;
        }
        
        const filteredList = window.allPOList.filter(po => 
            po.po_number.toLowerCase().includes(searchTerm) ||
            po.supplier_name.toLowerCase().includes(searchTerm)
        );
        
        if (filteredList.length > 0) {
            displayPOList(filteredList);
            $('#no-po-found').hide();
        } else {
            $('#po-list').empty();
            $('#no-po-found').show();
        }
    }

    // Helper functions
    function getStatusBadge(status) {
        switch(status) {
            case 'pending':
                return '<span class="badge bg-warning">รอดำเนินการ</span>';
            case 'partial':
                return '<span class="badge bg-info">รับบางส่วน</span>';
            case 'completed':
                return '<span class="badge bg-success">เสร็จสิ้น</span>';
            case 'cancelled':
                return '<span class="badge bg-danger">ยกเลิก</span>';
            default:
                return '<span class="badge bg-secondary">' + status + '</span>';
        }
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('th-TH');
    }

    // ===== Quantity Split Modal Functions =====
    
    // เพิ่ม PO เพิ่มเติม
    $(document).on('click', '#add-additional-po', function() {
        // แสดง PO selection modal สำหรับเลือก PO เพิ่มเติม
        showAdditionalPOSelection();
    });
    
    // ฟังก์ชันแสดง PO selection สำหรับเลือกเพิ่มเติม
    function showAdditionalPOSelection() {
        if (!window.allPOList) {
            Swal.fire('ข้อผิดพลาด', 'ไม่พบรายการ PO', 'error');
            return;
        }
        
        // กรองเอา PO ที่ยังไม่ได้เลือก
        const usedPOIds = [window.splitMainPO.poId, ...window.additionalPOs.map(po => po.poId)];
        const availablePOs = window.allPOList.filter(po => !usedPOIds.includes(po.po_id));
        
        if (availablePOs.length === 0) {
            Swal.fire('แจ้งเตือน', 'ไม่มี PO เพิ่มเติมให้เลือก', 'info');
            return;
        }
        
        // สร้าง HTML สำหรับเลือก PO เพิ่มเติม
        let html = '<div class="list-group" style="max-height: 300px; overflow-y: auto;">';
        availablePOs.forEach(po => {
            const remainingQty = po.ordered_qty - po.received_qty;
            const statusBadge = getStatusBadge(po.po_status);
            html += `
                <div class="list-group-item additional-po-item" style="cursor: pointer;" 
                     data-po-id="${po.po_id}"
                     data-item-id="${po.item_id}"
                     data-po-number="${po.po_number}"
                     data-unit-cost="${po.unit_cost}"
                     data-remaining-qty="${remainingQty}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${po.po_number}</h6>
                            <small class="text-muted">${po.supplier_name}</small>
                        </div>
                        <div class="text-end">
                            ${statusBadge}
                            <div><small class="text-muted">เหลือ: ${remainingQty}</small></div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        Swal.fire({
            title: 'เลือก PO เพิ่มเติม',
            html: html,
            width: '600px',
            showCancelButton: true,
            confirmButtonText: 'ยกเลิก',
            showConfirmButton: false,
            didOpen: () => {
                // Bind click events สำหรับเลือก additional PO
                $('.additional-po-item').on('click', function() {
                    const poData = {
                        poId: $(this).data('po-id'),
                        itemId: $(this).data('item-id'),
                        poNumber: $(this).data('po-number'),
                        unitCost: $(this).data('unit-cost'),
                        remainingQty: $(this).data('remaining-qty')
                    };
                    addAdditionalPO(poData);
                    Swal.close();
                });
            }
        });
    }
    
    // เพิ่ม PO เข้าไปในรายการเพิ่มเติม
    function addAdditionalPO(poData) {
        // เพิ่ม field expiry_date ให้ PO data
        poData.expiry_date = '';
        window.additionalPOs.push(poData);
        renderAdditionalPOList();
        updateQuantitySummary();
    }
    
    // แสดงรายการ PO เพิ่มเติม
    function renderAdditionalPOList() {
        const container = $('#additional-po-list');
        
        if (window.additionalPOs.length === 0) {
            $('#no-additional-pos').show();
            return;
        }
        
        $('#no-additional-pos').hide();
        
        let html = '';
        window.additionalPOs.forEach((po, index) => {
            const expiryDate = po.expiry_date || '';
            html += `
                <div class="card mb-2 additional-po-row" data-index="${index}">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">PO</label>
                                <div class="fw-bold">${po.poNumber}</div>
                                <small class="text-muted">เหลือ: ${po.remainingQty} ชิ้น</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">จำนวน</label>
                                <input type="number" class="form-control additional-qty-input" data-index="${index}" min="0" max="${po.remainingQty}" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">วันหมดอายุ</label>
                                <input type="date" class="form-control additional-expiry-input" data-index="${index}" value="${expiryDate}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-additional-po" data-index="${index}" style="width: 100%;">
                                    <span class="material-icons" style="font-size: 1rem;">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.html(html);
    }
    
    // ลบ PO เพิ่มเติม
    $(document).on('click', '.remove-additional-po', function() {
        const index = $(this).data('index');
        window.additionalPOs.splice(index, 1);
        renderAdditionalPOList();
        updateQuantitySummary();
    });
    
    // อัพเดทการคำนวณจำนวน และเก็บ expiry_date
    $(document).on('input', '#split-main-qty, .additional-qty-input', function() {
        updateQuantitySummary();
    });
    
    // เก็บ expiry_date เมื่อผู้ใช้เปลี่ยน
    $(document).on('change', '#split-main-expiry', function() {
        // จะเก็บค่านี้ใน split main PO data เมื่อยืนยัน
    });
    
    $(document).on('change', '.additional-expiry-input', function() {
        const index = $(this).data('index');
        const expiryDate = $(this).val();
        if (window.additionalPOs[index]) {
            window.additionalPOs[index].expiry_date = expiryDate;
        }
    });
    
    // ฟังก์ชันอัพเดทสรุปจำนวน
    function updateQuantitySummary() {
        const totalQty = parseInt($('#split-total-qty').text()) || 0;
        const mainQty = parseInt($('#split-main-qty').val()) || 0;
        
        let additionalTotal = 0;
        $('.additional-qty-input').each(function() {
            additionalTotal += parseInt($(this).val()) || 0;
        });
        
        const allocatedQty = mainQty + additionalTotal;
        const remainingQty = totalQty - allocatedQty;
        
        $('#summary-total').text(totalQty);
        $('#summary-allocated').text(allocatedQty);
        $('#summary-remaining').text(remainingQty);
        
        // อัพเดทสถานะ
        const statusElement = $('#summary-status');
        const confirmButton = $('#confirm-split');
        
        if (remainingQty === 0 && allocatedQty === totalQty) {
            statusElement.html('<span class="badge bg-success">สมดุลแล้ว</span>');
            confirmButton.prop('disabled', false);
        } else if (remainingQty > 0) {
            statusElement.html('<span class="badge bg-warning">ยังไม่ครบ</span>');
            confirmButton.prop('disabled', true);
        } else {
            statusElement.html('<span class="badge bg-danger">เกินจำนวน</span>');
            confirmButton.prop('disabled', true);
        }
    }
    
    // ยืนยันการแบ่งจำนวน
    $(document).on('click', '#confirm-split', function() {
        const splits = [];
        
        // เพิ่มข้อมูล PO หลัก
        const mainQty = parseInt($('#split-main-qty').val()) || 0;
        const mainExpiryDate = $('#split-main-expiry').val();
        console.log('Main split - qty:', mainQty, 'expiry:', mainExpiryDate);
        
        if (mainQty > 0) {
            splits.push({
                poId: window.splitMainPO.poId,
                itemId: window.splitMainPO.itemId,
                poNumber: window.splitMainPO.poNumber,
                unitCost: window.splitMainPO.unitCost,
                quantity: mainQty,
                expiry_date: mainExpiryDate
            });
        }
        
        // เพิ่มข้อมูล PO เพิ่มเติม
        $('.additional-qty-input').each(function() {
            const index = $(this).data('index');
            const qty = parseInt($(this).val()) || 0;
            const expiryInput = $(`.additional-expiry-input[data-index="${index}"]`);
            const expiryDate = expiryInput.val();
            console.log('Additional split', index, '- qty:', qty, 'expiry:', expiryDate);
            
            if (qty > 0) {
                const po = window.additionalPOs[index];
                splits.push({
                    poId: po.poId,
                    itemId: po.itemId,
                    poNumber: po.poNumber,
                    unitCost: po.unitCost,
                    quantity: qty,
                    expiry_date: expiryDate
                });
            }
        });
        
        console.log('Total splits collected:', splits.length);
        console.log('All splits:', splits);
        
        if (splits.length === 0) {
            Swal.fire('ข้อผิดพลาด', 'กรุณาระบุจำนวนอย่างน้อย 1 PO', 'error');
            return;
        }
        
        // บันทึกข้อมูลการแบ่ง
        saveSplitQuantities(splits);
    });
    
    // บันทึกการแบ่งจำนวน
    function saveSplitQuantities(splits) {
        if (splits.length === 0) return;
        
        // แยก PO หลักกับ PO เพิ่มเติม
        const mainSplit = splits[0];
        const additionalSplits = splits.slice(1);
        
        // คำนวณจำนวนรวมทั้งหมด
        let totalQtyForSplit = mainSplit.quantity;
        additionalSplits.forEach(split => {
            totalQtyForSplit += split.quantity;
        });
        
        // ตรวจสอบว่าจำนวนรวมตรงกับจำนวนเดิมที่บันทึกไว้ (ไม่ใช่จากฟอร์มที่อาจมีการแก้ไข)
        if (totalQtyForSplit !== window.originalQtyBeforeSplit) {
            Swal.fire('ข้อผิดพลาด', `จำนวนรวมไม่ตรงกัน (ต้องมี ${window.originalQtyBeforeSplit} ชิ้น แต่ระบุ ${totalQtyForSplit} ชิ้น)`, 'error');
            console.log('Split quantity validation failed:', { original: window.originalQtyBeforeSplit, split: totalQtyForSplit });
            return;
        }
        
        // เตรียมข้อมูลการแบ่งสำหรับส่งไปยัง backend
        const splitData = {
            mainPoId: mainSplit.poId,
            mainItemId: mainSplit.itemId,
            mainQty: mainSplit.quantity,
            mainExpiryDate: mainSplit.expiry_date,
            additionalPOs: additionalSplits.map(split => ({
                poId: split.poId,
                itemId: split.itemId,
                poNumber: split.poNumber,
                unitCost: split.unitCost,
                qty: split.quantity,
                expiry_date: split.expiry_date
            }))
        };
        
        // เตรียม form data สำหรับส่ง
        let formData = $('#edit-form').serialize();
        formData += '&split_data=' + encodeURIComponent(JSON.stringify(splitData));
        
        // อัพเดทฟอร์มหลักด้วยข้อมูล PO หลัก (เพื่อให้ map ถูกต้อง)
        $('#edit-po-id').val(mainSplit.poId);
        $('#edit-item-id').val(mainSplit.itemId);
        $('#edit-po-number').val(mainSplit.poNumber);
        $('#edit-price-cost').val(mainSplit.unitCost);
        // อัพเดท receive_qty ให้เป็นจำนวน main split เท่านั้น (backend จะสร้างรายการใหม่สำหรับ additional)
        $('#edit-receive-qty').val(mainSplit.quantity);
        
        // อัพเดทวันหมดอายุจาก split data
        if (mainSplit.expiry_date) {
            $('#edit-expiry-date').val(mainSplit.expiry_date);
        }
        
        console.log('Sending split data:', { splitData, originalQty: window.originalQtyBeforeSplit, totalSplit: totalQtyForSplit });
        
        // ปิด modal ก่อน
        var splitModal = bootstrap.Modal.getInstance(document.getElementById('quantitySplitModal'));
        splitModal.hide();
        
        // แสดง loading indicator
        Swal.fire({
            title: 'กำลังบันทึกการแบ่งจำนวน...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        // ตั้ง flag ว่าเป็นการส่งข้อมูลการแบ่ง
        window.isSendingSplitData = true;
        
        // ส่งข้อมูลไปยัง backend ทันที
        $.post('receive_edit.php', formData, function(resp) {
            if (resp.success) {
                // ปิด modal แก้ไข
                var editModal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
                editModal.hide();
                
                // สร้าง HTML สำหรับแสดงรายละเอียดการแบ่ง
                let htmlDetail = '';
                
                // ถ้าเป็นการแบ่งจำนวนแสดงรายละเอียดจาก backend
                if (resp.is_split && resp.splits && resp.splits.length > 0) {
                    htmlDetail = `<div style="text-align: left; max-height: 400px; overflow-y: auto; border-radius: 8px;">`;
                    resp.splits.forEach((split, idx) => {
                        const bgColor = idx === 0 ? '#dbeafe' : '#f0fdf4';
                        const borderColor = idx === 0 ? '#0284c7' : '#16a34a';
                        htmlDetail += `
                            <div style="margin-bottom: 10px; padding: 12px; background: ${bgColor}; border-left: 4px solid ${borderColor}; border-radius: 4px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <strong style="font-size: 1rem;">PO ${idx + 1}</strong>
                                    <span style="background: ${borderColor}; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.8rem;">
                                        ${split.poNumber}
                                    </span>
                                </div>
                                <div style="font-size: 0.95rem; line-height: 1.6; color: #374151;">
                                    <span>จำนวน: <strong>${split.quantity} ชิ้น</strong></span><br>
                                    <span>หมดอายุ: <strong>${split.expiry_date}</strong></span>
                                </div>
                            </div>
                        `;
                    });
                    htmlDetail += `</div>`;
                } else {
                    // แสดงจาก splits array เดิม
                    htmlDetail = `<div style="text-align: left; max-height: 400px; overflow-y: auto;">` +
                          splits.map((s, idx) => 
                            `<div style="margin-bottom: 8px; padding: 8px; background: #f0f9ff; border-left: 3px solid #3b82f6; border-radius: 4px;">
                                <strong>PO ${idx + 1}:</strong> ${s.poNumber}<br>
                                <span style="font-size: 0.9rem;">จำนวน: <strong>${s.quantity}</strong> ชิ้น</span><br>
                                <span style="font-size: 0.9rem;">หมดอายุ: <strong>${s.expiry_date || 'ไม่ระบุ'}</strong></span>
                            </div>`
                          ).join('') +
                          `</div>`;
                }
                
                // แสดงข้อความยืนยันการบันทึก
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกการแบ่งจำนวนสำเร็จ',
                    html: `<div style="margin-bottom: 12px;">
                              ✓ แบ่งจำนวนไปยัง <strong>${(resp.splits ? resp.splits.length : splits.length)} PO</strong>
                           </div>` + htmlDetail,
                    allowOutsideClick: false,
                    confirmButtonText: 'ตกลง',
                    width: '600px'
                }).then(() => {
                    // รีเฟรชข้อมูลตาราง
                    refreshTableData();
                });
                
                console.log('Split quantities saved successfully', resp);
            } else {
                Swal.fire('ข้อผิดพลาด', resp.message || 'ไม่สามารถบันทึกการแบ่งจำนวน', 'error');
                console.error('Error saving split:', resp);
                
                // เปิด modal แก้ไขอีกครั้ง
                var editModal = new bootstrap.Modal(document.getElementById('editModal'));
                editModal.show();
            }
        }, 'json').fail(function(xhr, status, error) {
            Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์: ' + error, 'error');
            console.error('AJAX error:', xhr, status, error);
            
            // เปิด modal แก้ไขอีกครั้ง
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        });
    }
    
    // เริ่มต้น modal แบ่งจำนวน
    $('#quantitySplitModal').on('show.bs.modal', function() {
        // เซ็ตข้อมูลเริ่มต้น
        if (window.splitMainPO) {
            $('#main-po-display').text(window.splitMainPO.poNumber);
            $('#main-po-remaining').text(window.splitMainPO.availableQty);
            $('#split-main-qty').attr('max', window.splitMainPO.availableQty);
            
            // เซ็ตวันหมดอายุเดิม (ถ้ามี) ให้ PO หลัก
            const currentExpiry = $('#edit-expiry-date').val();
            if (currentExpiry) {
                $('#split-main-expiry').val(currentExpiry);
            }
        }
        
        // รีเซ็ต additional POs
        if (!window.additionalPOs) {
            window.additionalPOs = [];
        }
        
        renderAdditionalPOList();
        updateQuantitySummary();
    });
});
</script>

<!-- Modal แก้ไข -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">แก้ไขรายการรับสินค้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-form">
                    <input type="hidden" name="receive_id" id="edit-receive-id">
                    <div class="mb-2">
                        <label for="edit-remark" class="form-label">หมายเหตุ</label>
                        <input type="text" class="form-control" name="remark" id="edit-remark">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">ตำแหน่ง</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <select class="form-select" name="row_code" id="edit-row-code">
                                    <option value="">แถว</option>
                                    <?php
                                    foreach (range('A','X') as $c) {
                                        echo '<option value="'.$c.'">'.$c.'</option>';
                                    }
                                    echo '<option value="T">T(ตู้)</option>';
                                    echo '<option value="sale(บน)">sale(บน)</option>';
                                    echo '<option value="sale(ล่าง)">sale(ล่าง)</option>';
                                    ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <select class="form-select" name="bin" id="edit-bin">
                                    <option value="">ล๊อค</option>
                                    <?php for($i=1;$i<=10;$i++) echo '<option value="'.$i.'">'.$i.'</option>'; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <select class="form-select" name="shelf" id="edit-shelf">
                                    <option value="">ชั้น</option>
                                    <?php for($i=1;$i<=10;$i++) echo '<option value="'.$i.'">'.$i.'</option>'; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="edit-price-cost" class="form-label field-accent field-purchase">ราคาซื้อ</label>
                            <input type="number" step="0.01" class="form-control field-input-purchase" name="price_per_unit" id="edit-price-cost" readonly>
                        </div>
                        <div class="col-6">
                            <label for="edit-weight" class="form-label field-accent field-weight">น้ำหนัก (กก.)</label>
                            <input type="number" step="0.01" min="0" class="form-control field-input-weight" id="edit-weight" placeholder="-" readonly>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="edit-price-sale" class="form-label field-accent field-sale">ราคาขาย</label>
                        <input type="number" step="0.01" class="form-control field-input-sale" name="sale_price" id="edit-price-sale" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">ประเภทการเปลี่ยนแปลง</label>
                        <select class="form-select" id="edit-qty-type">
                            <option value="plus">เพิ่ม</option>
                            <option value="minus">ลด</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="edit-receive-qty" class="form-label">จำนวน</label>
                        <input type="number" class="form-control" name="receive_qty" id="edit-receive-qty">
                    </div>
                    <div class="mb-2">
                        <label for="edit-po-number" class="form-label">เลข PO</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="po_number" id="edit-po-number" readonly>
                            <button type="button" class="btn btn-outline-secondary" id="change-po-btn">
                                <span class="material-icons" style="font-size: 1rem;">search</span>
                                เปลี่ยน
                            </button>
                        </div>
                        <input type="hidden" name="po_id" id="edit-po-id">
                        <input type="hidden" name="item_id" id="edit-item-id">
                    </div>
                    <div class="mb-2">
                        <label for="edit-expiry-date" class="form-label">วันหมดอายุ</label>
                        <input type="date" class="form-control" name="expiry_date" id="edit-expiry-date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" id="save-edit">บันทึก</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal เลือก PO -->
<div class="modal fade" id="selectPOModal" tabindex="-1" aria-labelledby="selectPOModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="selectPOModalLabel">
                    <span class="material-icons align-middle me-2">search</span>
                    เลือกใบสั่งซื้อ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="po-search" class="form-label">ค้นหาเลข PO</label>
                    <input type="text" class="form-control" id="po-search" placeholder="พิมพ์เลข PO หรือชื่อผู้ขาย...">
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">รายการใบสั่งซื้อที่มีสินค้านี้</h6>
                        <div id="po-loading" class="spinner-border spinner-border-sm" role="status" style="display: none;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                
                <div id="po-list" class="list-group" style="max-height: 400px; overflow-y: auto;">
                    <!-- PO items will be loaded here -->
                </div>
                
                <div id="no-po-found" class="text-center py-4" style="display: none;">
                    <span class="material-icons mb-2" style="font-size: 3rem; color: #d1d5db;">receipt</span>
                    <p class="text-muted">ไม่พบใบสั่งซื้อ</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal แบ่งจำนวนสินค้า -->
<div class="modal fade" id="quantitySplitModal" tabindex="-1" aria-labelledby="quantitySplitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quantitySplitModalLabel">
                    <span class="material-icons align-middle me-2">call_split</span>
                    แบ่งจำนวนสินค้าไปยังหลาย PO
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <h6><span class="material-icons align-middle me-2">warning</span>ตรวจพบปัญหา</h6>
                    <p class="mb-0">
                        จำนวนที่จะรับ (<span id="split-total-qty" class="fw-bold"></span> ชิ้น) 
                        มากกว่าจำนวนที่เหลือใน PO <span id="split-main-po-number" class="fw-bold"></span> 
                        (<span id="split-available-qty" class="fw-bold"></span> ชิ้น)
                        <br>
                        ต้องแบ่งจำนวนเกินส่วน (<span id="split-excess-qty" class="fw-bold text-danger"></span> ชิ้น) ไปยัง PO อื่น
                    </p>
                </div>



                   <!-- สรุปจำนวน -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">สรุปการแบ่งจำนวน</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">จำนวนรวม</label>
                                <div class="form-control-plaintext fw-bold" id="summary-total">0</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">จำนวนที่แบ่งแล้ว</label>
                                <div class="form-control-plaintext fw-bold" id="summary-allocated">0</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">จำนวนที่เหลือ</label>
                                <div class="form-control-plaintext fw-bold" id="summary-remaining">0</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">สถานะ</label>
                                <div class="form-control-plaintext fw-bold" id="summary-status">
                                    <span class="badge bg-warning">ยังไม่สมดุล</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                
                <!-- PO หลัก -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">PO หลัก</h6>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <strong id="main-po-display"></strong>
                                <small class="d-block text-muted mt-2">จำนวนที่เหลือใน PO: <span id="main-po-remaining"></span></small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">จำนวนที่จะรับ</label>
                                <input type="number" class="form-control" id="split-main-qty" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">วันหมดอายุ</label>
                                <input type="date" class="form-control" id="split-main-expiry">
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">ตั้งเฉพาะสำหรับ PO นี้</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PO เพิ่มเติม -->
                <div class="card mb-3">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">PO เพิ่มเติม</h6>
                        <button type="button" class="btn btn-sm btn-outline-light" id="add-additional-po">
                            <span class="material-icons" style="font-size: 1rem;">add</span>
                            เพิ่ม PO
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="additional-po-list">
                            <!-- Additional POs will be added here -->
                        </div>
                        <div id="no-additional-pos" class="text-center text-muted py-3">
                            <p class="mb-0">ยังไม่มี PO เพิ่มเติม กดปุ่ม "เพิ่ม PO" เพื่อเลือก PO อื่น</p>
                        </div>
                    </div>
                </div>

             
            </div>


            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" id="confirm-split" disabled>ยืนยันการแบ่ง</button>
            </div>
        </div>
    </div>
</div>

<?php
// Helper ฟังก์ชัน (ควรย้ายไปไฟล์แยกถ้า production)

function getImagePath($imageName) {
    if (empty($imageName)) {
        return '../images/noimg.png';
    }
    
    // รายการ path ที่เป็นไปได้
    $possible_paths = [
        '../images/' . $imageName,
        '../' . $imageName,
        $imageName
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // หากไม่พบไฟล์ใดๆ ใช้ noimg.png
    return '../images/noimg.png';
}

function formatQuantity($value) {
    $value = (float) $value;
    $rounded = round($value);
    $decimals = (abs($value - $rounded) < 0.00001) ? 0 : 2;
    return number_format($value, $decimals);
}

function getPrevQty($sku, $barcode, $current_created_at, $pdo) {
    // คำนวณจำนวนคงเหลือก่อนหน้ารายการนี้
    $sql = "
        SELECT COALESCE(
            (SELECT SUM(ri.receive_qty) FROM receive_items ri 
             LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id 
             LEFT JOIN products p ON poi.product_id = p.product_id 
             WHERE p.sku = ? AND p.barcode = ? AND ri.created_at < ?), 0
        ) - COALESCE(
            (SELECT SUM(ii.issue_qty) FROM issue_items ii 
             LEFT JOIN products p ON ii.product_id = p.product_id 
             WHERE p.sku = ? AND p.barcode = ? AND ii.created_at < ?), 0
        ) as total_qty
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sku, $barcode, $current_created_at, $sku, $barcode, $current_created_at]);
    $result = (int)$stmt->fetchColumn();
    return max(0, $result); // ไม่ให้ติดลบ
}

function getCurrentQty($sku, $barcode, $current_created_at, $pdo) {
    // คำนวณจำนวนคงเหลือหลังจากรายการนี้
    $sql = "
        SELECT COALESCE(
            (SELECT SUM(ri.receive_qty) FROM receive_items ri 
             LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id 
             LEFT JOIN products p ON poi.product_id = p.product_id 
             WHERE p.sku = ? AND p.barcode = ? AND ri.created_at <= ?), 0
        ) - COALESCE(
            (SELECT SUM(ii.issue_qty) FROM issue_items ii 
             LEFT JOIN products p ON ii.product_id = p.product_id 
             WHERE p.sku = ? AND p.barcode = ? AND ii.created_at <= ?), 0
        ) as total_qty
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sku, $barcode, $current_created_at, $sku, $barcode, $current_created_at]);
    $result = (int)$stmt->fetchColumn();
    return max(0, $result); // ไม่ให้ติดลบ
}
function qtyChange($qty) {
    if($qty > 0) return '<span class="qty-plus" style="color:#22bb33;font-weight:bold;">+'.(int)$qty.'</span>';
    if($qty < 0) return '<span class="qty-minus" style="color:#e74c3c;font-weight:bold;">'.(int)$qty.'</span>';
    return $qty;
}
function getTypeLabel($remark) {
    if(stripos($remark, 'excel') !== false) return '<span class="badge bg-info">Excel</span>';
    return '<span class="badge bg-secondary">Manual</span>';
}
?>
</body>
</html>
