<?php
/**
 * ทดสอบข้อมูลสินค้าชำรุดสำหรับ PO ID = 7 โดยเฉพาะ
 */

require 'config/db_connect.php';

$po_id = 7;

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>ทดสอบ PO ID 7 - Damaged Items</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h1 { color: #333; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; }
    h2 { color: #555; margin-top: 30px; }
    table { border-collapse: collapse; width: 100%; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #3b82f6; color: white; font-weight: bold; }
    tr:nth-child(even) { background-color: #f8f9fa; }
    .info { background: #e3f2fd; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3; border-radius: 4px; }
    .warning { background: #fff3e0; padding: 15px; margin: 15px 0; border-left: 4px solid #ff9800; border-radius: 4px; }
    .error { background: #ffebee; padding: 15px; margin: 15px 0; border-left: 4px solid #f44336; border-radius: 4px; }
    .success { background: #e8f5e9; padding: 15px; margin: 15px 0; border-left: 4px solid #4CAF50; border-radius: 4px; }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.875rem; font-weight: 600; }
    .badge-danger { background: #fee2e2; color: #dc2626; }
    .badge-success { background: #dcfce7; color: #16a34a; }
    .badge-warning { background: #fef3c7; color: #d97706; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
    .highlight { background: #fef08a; padding: 2px 4px; font-weight: bold; }
    .api-test { background: #f8fafc; border: 2px solid #cbd5e1; padding: 15px; border-radius: 8px; margin: 15px 0; }
    pre { background: #1e293b; color: #f1f5f9; padding: 15px; border-radius: 8px; overflow-x: auto; }
</style>
<script src='https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js'></script>
</head><body>";

echo "<div class='container'>";
echo "<h1>🔍 ทดสอบข้อมูลสินค้าชำรุด - PO ID: {$po_id}</h1>";

try {
    // 1. ตรวจสอบข้อมูล PO
    echo "<div class='info'><h2>ขั้นตอนที่ 1: ข้อมูล Purchase Order</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            po.po_id,
            po.po_number,
            po.status,
            s.name as supplier_name,
            po.order_date,
            po.remark
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        WHERE po.po_id = :po_id
    ");
    $stmt->execute([':po_id' => $po_id]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$po) {
        echo "<div class='error'>❌ ไม่พบ PO ID: {$po_id}</div>";
        echo "</div></div></body></html>";
        exit;
    }
    
    echo "<table>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    foreach ($po as $key => $value) {
        echo "<tr><td><strong>{$key}</strong></td><td>{$value}</td></tr>";
    }
    echo "</table>";
    echo "</div>";
    
    // 2. ตรวจสอบรายการสินค้าทั้งหมดใน PO
    echo "<div class='info'><h2>ขั้นตอนที่ 2: รายการสินค้าใน PO นี้</h2>";
    
    // ตรวจสอบ columns ที่มีจริงใน purchase_order_items
    $stmt = $pdo->prepare("
        SELECT 
            poi.*,
            COALESCE(SUM(ri.receive_qty), 0) as received_qty
        FROM purchase_order_items poi
        LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
        WHERE poi.po_id = :po_id
        GROUP BY poi.item_id
    ");
    $stmt->execute([':po_id' => $po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>📦 จำนวนรายการสินค้า: <strong>" . count($items) . "</strong></p>";
    
    if (!empty($items)) {
        // แสดง columns ทั้งหมดที่มีใน purchase_order_items
        echo "<div class='warning'><h3>📋 Columns ที่มีใน purchase_order_items:</h3>";
        if (!empty($items)) {
            echo "<p><code>" . implode(', ', array_keys($items[0])) . "</code></p>";
        }
        echo "</div>";
        
        echo "<table>";
        echo "<tr>";
        foreach (array_keys($items[0]) as $col) {
            echo "<th>{$col}</th>";
        }
        echo "</tr>";
        
        foreach ($items as $item) {
            echo "<tr>";
            foreach ($item as $value) {
                echo "<td>" . htmlspecialchars($value ?? '-') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // 3. ตรวจสอบข้อมูล returned_items ทั้งหมดที่เกี่ยวข้องกับ PO นี้
    echo "<div class='info'><h2>ขั้นตอนที่ 3: returned_items ทั้งหมดของ PO นี้</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            ri.*,
            rr.reason_name
        FROM returned_items ri
        LEFT JOIN return_reasons rr ON ri.reason_id = rr.reason_id
        WHERE ri.po_id = :po_id
        ORDER BY ri.created_at DESC
    ");
    $stmt->execute([':po_id' => $po_id]);
    $all_returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>📋 จำนวน returned_items ทั้งหมด: <strong>" . count($all_returns) . "</strong></p>";
    
    if (!empty($all_returns)) {
        // แสดง columns ที่มีจริงใน returned_items
        echo "<div class='warning'><h3>📋 Columns ที่มีใน returned_items:</h3>";
        echo "<p><code>" . implode(', ', array_keys($all_returns[0])) . "</code></p>";
        echo "</div>";
        
        echo "<table>";
        echo "<tr>";
        foreach (array_keys($all_returns[0]) as $col) {
            echo "<th>{$col}</th>";
        }
        echo "</tr>";
        
        foreach ($all_returns as $ret) {
            $rowClass = '';
            if (isset($ret['is_returnable']) && ($ret['is_returnable'] == 0 || $ret['is_returnable'] === '0')) {
                $rowClass = "style='background-color: #fee2e2;'";
            }
            echo "<tr {$rowClass}>";
            foreach ($ret as $key => $value) {
                if ($key === 'is_returnable') {
                    $returnableClass = ($value == 0 || $value === '0') ? 'badge-danger' : 'badge-success';
                    echo "<td><span class='badge {$returnableClass}'>{$value}</span></td>";
                } elseif ($key === 'po_id') {
                    echo "<td class='highlight'>" . htmlspecialchars($value ?? '-') . "</td>";
                } else {
                    echo "<td>" . htmlspecialchars($value ?? '-') . "</td>";
                }
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ ไม่พบ returned_items สำหรับ PO นี้</div>";
    }
    echo "</div>";
    
    // 4. ตรวจสอบเฉพาะ returned_items ที่ is_returnable = 0 (ขายไม่ได้)
    echo "<div class='info'><h2>ขั้นตอนที่ 4: สินค้าชำรุดขายไม่ได้ (is_returnable = 0)</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            ri.*,
            rr.reason_name
        FROM returned_items ri
        LEFT JOIN return_reasons rr ON ri.reason_id = rr.reason_id
        WHERE (ri.is_returnable = 0 OR ri.is_returnable = '0')
        AND ri.po_id = :po_id
        ORDER BY ri.created_at DESC
    ");
    $stmt->execute([':po_id' => $po_id]);
    $unsellable_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = count($unsellable_items);
    
    if ($count > 0) {
        echo "<div class='success'>✅ พบสินค้าชำรุดขายไม่ได้: <strong>{$count}</strong> รายการ</div>";
        
        // แสดง columns ที่มีจริง
        echo "<div class='warning'><h3>📋 Columns ที่มีในรายการชำรุด:</h3>";
        echo "<p><code>" . implode(', ', array_keys($unsellable_items[0])) . "</code></p>";
        echo "</div>";
        
        echo "<table>";
        echo "<tr>";
        foreach (array_keys($unsellable_items[0]) as $col) {
            echo "<th>{$col}</th>";
        }
        echo "</tr>";
        
        foreach ($unsellable_items as $item) {
            echo "<tr style='background-color: #fee2e2;'>";
            foreach ($item as $key => $value) {
                if ($key === 'is_returnable') {
                    echo "<td><span class='badge badge-danger'><strong>{$value}</strong></span></td>";
                } elseif ($key === 'po_id') {
                    echo "<td class='highlight'><strong>{$value}</strong></td>";
                } elseif ($key === 'return_qty') {
                    echo "<td><strong>" . htmlspecialchars($value ?? '-') . "</strong></td>";
                } elseif ($key === 'created_at') {
                    echo "<td>" . ($value ? date('d/m/Y H:i', strtotime($value)) : '-') . "</td>";
                } elseif ($key === 'notes' && strlen($value) > 50) {
                    echo "<td>" . htmlspecialchars(substr($value, 0, 50)) . "...</td>";
                } else {
                    echo "<td>" . htmlspecialchars($value ?? '-') . "</td>";
                }
            }
            echo "</tr>";
        }
        echo "</table>";
        
        // แสดง JSON format เหมือน API response
        echo "<h3>📤 API Response Format:</h3>";
        echo "<pre>" . json_encode([
            'status' => 'success',
            'data' => $unsellable_items,
            'count' => $count
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
    } else {
        echo "<div class='error'>❌ ไม่พบสินค้าชำรุดขายไม่ได้สำหรับ PO ID: {$po_id}</div>";
        echo "<div class='warning'>";
        echo "<h3>💡 สาเหตุที่เป็นไปได้:</h3>";
        echo "<ol>";
        echo "<li>ยังไม่มีการบันทึกสินค้าชำรุดที่เป็น 'ขายไม่ได้' (is_returnable = 0)</li>";
        echo "<li>ข้อมูลที่บันทึกไว้มี is_returnable = 1 (ขายได้)</li>";
        echo "<li>po_id ในตาราง returned_items ไม่ตรงกับ PO นี้</li>";
        echo "</ol>";
        echo "</div>";
    }
    echo "</div>";
    
    // 5. ทดสอบ API โดยตรง
    echo "<div class='api-test'>";
    echo "<h2>ขั้นตอนที่ 5: ทดสอบ API โดยตรง</h2>";
    echo "<p>🔗 API URL: <code>api/get_damaged_unsellable_by_po.php?po_id={$po_id}</code></p>";
    echo "<p><a href='api/get_damaged_unsellable_by_po.php?po_id={$po_id}' target='_blank' class='btn'>คลิกเพื่อเปิด API Response</a></p>";
    
    echo "<h3>ทดสอบ AJAX Call:</h3>";
    echo "<button id='testApiBtn' style='padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem;'>🧪 ทดสอบ API ผ่าน AJAX</button>";
    echo "<div id='apiResult' style='margin-top: 15px;'></div>";
    
    echo "<script>
    $('#testApiBtn').on('click', function() {
        $('#apiResult').html('<div style=\"padding: 15px; background: #f1f5f9; border-radius: 6px;\">⏳ กำลังโหลด...</div>');
        
        $.ajax({
            url: 'api/get_damaged_unsellable_by_po.php?po_id={$po_id}',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('API Response:', response);
                
                let html = '<div style=\"padding: 15px; background: #e8f5e9; border-radius: 6px; border-left: 4px solid #4CAF50;\">';
                html += '<h3>✅ API Response Successful</h3>';
                html += '<p><strong>Status:</strong> ' + response.status + '</p>';
                html += '<p><strong>Count:</strong> ' + (response.count || 0) + '</p>';
                if (response.debug) {
                    html += '<p><strong>Debug Info:</strong></p>';
                    html += '<pre style=\"background: #f8fafc; padding: 10px; border-radius: 4px;\">' + JSON.stringify(response.debug, null, 2) + '</pre>';
                }
                html += '<h4>Data:</h4>';
                html += '<pre style=\"background: #1e293b; color: #f1f5f9; padding: 15px; border-radius: 8px; overflow-x: auto;\">' + JSON.stringify(response.data, null, 2) + '</pre>';
                html += '</div>';
                
                $('#apiResult').html(html);
            },
            error: function(xhr, status, error) {
                console.error('API Error:', error);
                console.error('XHR:', xhr);
                
                let html = '<div style=\"padding: 15px; background: #ffebee; border-radius: 6px; border-left: 4px solid #f44336;\">';
                html += '<h3>❌ API Error</h3>';
                html += '<p><strong>Status:</strong> ' + xhr.status + '</p>';
                html += '<p><strong>Error:</strong> ' + error + '</p>';
                html += '<p><strong>Response:</strong></p>';
                html += '<pre style=\"background: #1e293b; color: #f1f5f9; padding: 15px; border-radius: 8px;\">' + xhr.responseText + '</pre>';
                html += '</div>';
                
                $('#apiResult').html(html);
            }
        });
    });
    </script>";
    
    echo "</div>";
    
    // 6. สรุปผล
    echo "<div class='info'>";
    echo "<h2>📊 สรุปผลการตรวจสอบ</h2>";
    echo "<table>";
    echo "<tr><th>รายการ</th><th>จำนวน</th><th>สถานะ</th></tr>";
    echo "<tr><td>รายการสินค้าใน PO</td><td>" . count($items) . "</td><td>" . (count($items) > 0 ? '✅' : '❌') . "</td></tr>";
    echo "<tr><td>returned_items ทั้งหมด</td><td>" . count($all_returns) . "</td><td>" . (count($all_returns) > 0 ? '✅' : '⚠️') . "</td></tr>";
    echo "<tr style='background-color: #fee2e2;'><td><strong>สินค้าชำรุดขายไม่ได้</strong></td><td><strong>{$count}</strong></td><td><strong>" . ($count > 0 ? '✅ พบข้อมูล' : '❌ ไม่พบข้อมูล') . "</strong></td></tr>";
    echo "</table>";
    
    if ($count == 0) {
        echo "<div class='warning'>";
        echo "<h3>⚠️ คำแนะนำ:</h3>";
        echo "<p>หาก Console ในเบราว์เซอร์แสดงว่า API ส่ง count = 0 แสดงว่า:</p>";
        echo "<ul>";
        echo "<li>ข้อมูลใน <code>returned_items</code> ไม่มี <code>is_returnable = 0</code> สำหรับ PO นี้</li>";
        echo "<li>ตรวจสอบว่าเมื่อบันทึกสินค้าชำรุด เลือก <strong>'ทิ้ง/ใช้ไม่ได้'</strong> หรือไม่</li>";
        echo "<li>ตรวจสอบว่า <code>po_id</code> ใน <code>returned_items</code> ตรงกับ PO นี้หรือไม่</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "<h3>✅ ข้อมูลถูกต้อง!</h3>";
        echo "<p>พบสินค้าชำรุดขายไม่ได้ {$count} รายการ</p>";
        echo "<p>หากยังไม่เห็นใน popup ให้:</p>";
        echo "<ol>";
        echo "<li>ตรวจสอบ Console ใน Developer Tools (F12)</li>";
        echo "<li>ดูว่า JavaScript เรียก API หรือไม่</li>";
        echo "<li>ดูว่า <code>displayDamagedUnsellableByPo()</code> ถูกเรียกหรือไม่</li>";
        echo "<li>ตรวจสอบว่า <code>#damagedUnsellableSection</code> ถูกแสดงหรือไม่</li>";
        echo "</ol>";
        echo "</div>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ เกิดข้อผิดพลาด</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</div>";
echo "</body></html>";
?>
