<?php
require '../config/db_connect.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจสอบข้อมูล receive_items</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .data-table { font-size: 0.85rem; }
        .test-result { background: #f8f9fa; padding: 1rem; border-radius: 4px; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">ตรวจสอบข้อมูล receive_items</h1>
        
        <?php
        try {
            $sql = "SELECT r.receive_id, r.receive_qty, r.remark, r.expiry_date, 
                           poi.price_per_unit, poi.sale_price, 
                           p.name as product_name, po.po_number 
                    FROM receive_items r 
                    LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id 
                    LEFT JOIN products p ON poi.product_id = p.product_id
                    LEFT JOIN purchase_orders po ON r.po_id = po.po_id
                    ORDER BY r.receive_id DESC LIMIT 5";
            $stmt = $pdo->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($results)) {
                echo '<div class="alert alert-warning">ไม่พบข้อมูล receive_items</div>';
            } else {
                echo '<div class="test-result">';
                echo '<h3>ข้อมูล receive_items ล่าสุด 5 รายการ:</h3>';
                echo '<div class="table-responsive">';
                echo '<table class="table table-striped data-table">';
                echo '<thead class="table-dark">';
                echo '<tr>';
                echo '<th>ID</th>';
                echo '<th>สินค้า</th>';
                echo '<th>จำนวน</th>';
                echo '<th>ราคาต้นทุน</th>';
                echo '<th>ราคาขาย</th>';
                echo '<th>PO Number</th>';
                echo '<th>หมายเหตุ</th>';
                echo '<th>วันหมดอายุ</th>';
                echo '<th>ทดสอบ API</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($results as $row) {
                    echo '<tr>';
                    echo '<td>' . $row['receive_id'] . '</td>';
                    echo '<td>' . htmlspecialchars($row['product_name'] ?? 'N/A') . '</td>';
                    echo '<td>' . number_format($row['receive_qty']) . '</td>';
                    echo '<td>' . number_format($row['price_per_unit'] ?? 0, 2) . ' บาท</td>';
                    echo '<td>' . number_format($row['sale_price'] ?? 0, 2) . ' บาท</td>';
                    echo '<td>' . htmlspecialchars($row['po_number'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars(mb_substr($row['remark'] ?? '', 0, 20)) . '</td>';
                    echo '<td>' . ($row['expiry_date'] ?? 'N/A') . '</td>';
                    echo '<td><button class="btn btn-sm btn-primary test-api" data-id="' . $row['receive_id'] . '">ทดสอบ</button></td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }
        ?>
        
        <div class="test-result">
            <h3>ทดสอบการเรียก API:</h3>
            <div id="api-test-result"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.test-api').click(function() {
                const receiveId = $(this).data('id');
                $('#api-test-result').html('<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>');
                
                $.get('../api/receive_position_api.php', { receive_id: receiveId })
                    .done(function(response) {
                        console.log('API Response for ID ' + receiveId + ':', response);
                        
                        let html = '<div class="card">';
                        html += '<div class="card-header"><h5>ผลการทดสอบ API สำหรับ receive_id: ' + receiveId + '</h5></div>';
                        html += '<div class="card-body">';
                        
                        if (response.success) {
                            html += '<div class="alert alert-success">API ทำงานสำเร็จ</div>';
                            html += '<div class="row">';
                            html += '<div class="col-md-6">';
                            html += '<h6>ข้อมูลตำแหน่ง:</h6>';
                            html += '<ul>';
                            html += '<li>แถว: ' + (response.row_code || 'ไม่ระบุ') + '</li>';
                            html += '<li>ล๊อค: ' + (response.bin || 'ไม่ระบุ') + '</li>';
                            html += '<li>ชั้น: ' + (response.shelf || 'ไม่ระบุ') + '</li>';
                            html += '</ul>';
                            html += '</div>';
                            html += '<div class="col-md-6">';
                            html += '<h6>ข้อมูลราคาและอื่นๆ:</h6>';
                            html += '<ul>';
                            html += '<li>ราคาต้นทุน: ' + (response.price_per_unit || '0') + ' บาท</li>';
                            html += '<li>ราคาขาย: ' + (response.sale_price || '0') + ' บาท</li>';
                            html += '<li>หมายเหตุ: ' + (response.remark || 'ไม่มี') + '</li>';
                            html += '<li>วันหมดอายุ: ' + (response.expiry_date || 'ไม่ระบุ') + '</li>';
                            html += '</ul>';
                            html += '</div>';
                            html += '</div>';
                        } else {
                            html += '<div class="alert alert-danger">API ทำงานไม่สำเร็จ: ' + (response.msg || 'ไม่ทราบสาเหตุ') + '</div>';
                        }
                        
                        html += '<details class="mt-3">';
                        html += '<summary>Raw API Response</summary>';
                        html += '<pre class="bg-light p-2 mt-2">' + JSON.stringify(response, null, 2) + '</pre>';
                        html += '</details>';
                        html += '</div>';
                        html += '</div>';
                        
                        $('#api-test-result').html(html);
                    })
                    .fail(function(xhr, status, error) {
                        console.error('API Error:', xhr, status, error);
                        $('#api-test-result').html('<div class="alert alert-danger">ข้อผิดพลาดในการเรียก API: ' + error + '</div>');
                    });
            });
        });
    </script>
</body>
</html>