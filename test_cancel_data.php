<?php
/**
 * ทดสอบการบันทึกข้อมูลการยกเลิกสินค้า
 */

require 'config/db_connect.php';

try {
    // ตรวจสอบข้อมูลการยกเลิกในตาราง purchase_order_items
    $sql = "SELECT 
        poi.item_id,
        poi.po_id,
        p.name as product_name,
        poi.qty as ordered_qty,
        COALESCE(SUM(ri.receive_qty), 0) as received_qty,
        poi.is_cancelled,
        poi.is_partially_cancelled,
        poi.cancel_qty as cancelled_qty,
        poi.cancel_reason,
        poi.cancel_notes,
        poi.cancelled_at,
        poi.cancelled_by,
        u.username as cancelled_by_user
    FROM purchase_order_items poi
    LEFT JOIN products p ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
    LEFT JOIN users u ON poi.cancelled_by = u.user_id
    WHERE poi.cancel_qty > 0 OR poi.is_cancelled = 1
    GROUP BY poi.item_id
    ORDER BY poi.cancelled_at DESC
    LIMIT 20";

    $stmt = $pdo->query($sql);
    $cancelled_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>ข้อมูลการยกเลิกสินค้า (ทั้งหมด: " . count($cancelled_items) . " รายการ)</h2>";
    echo "<table border='1' cellpadding='10' cellspacing='0' style='width:100%; font-size: 12px;'>";
    echo "<tr style='background: #333; color: white;'>";
    echo "<th>รหัส Item</th>";
    echo "<th>รหัส PO</th>";
    echo "<th>สินค้า</th>";
    echo "<th>สั่ง</th>";
    echo "<th>รับแล้ว</th>";
    echo "<th>ยกเลิก</th>";
    echo "<th>is_cancelled</th>";
    echo "<th>is_partially_cancelled</th>";
    echo "<th>เหตุผล</th>";
    echo "<th>หมายเหตุ</th>";
    echo "<th>ยกเลิกโดย</th>";
    echo "<th>เวลา</th>";
    echo "</tr>";

    foreach ($cancelled_items as $item) {
        echo "<tr>";
        echo "<td>{$item['item_id']}</td>";
        echo "<td>{$item['po_id']}</td>";
        echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
        echo "<td align='center'>{$item['ordered_qty']}</td>";
        echo "<td align='center'>{$item['received_qty']}</td>";
        echo "<td align='center' style='color:red; font-weight:bold;'>{$item['cancelled_qty']}</td>";
        echo "<td align='center'>" . ($item['is_cancelled'] ? '✓' : '-') . "</td>";
        echo "<td align='center'>" . ($item['is_partially_cancelled'] ? '✓' : '-') . "</td>";
        echo "<td>" . htmlspecialchars($item['cancel_reason'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars(substr($item['cancel_notes'] ?? '-', 0, 30)) . "</td>";
        echo "<td>" . htmlspecialchars($item['cancelled_by_user'] ?? '-') . "</td>";
        echo "<td>" . ($item['cancelled_at'] ?? '-') . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "<br><br>";

    // ตรวจสอบว่า receive_items ไม่มีข้อมูลที่ยกเลิก
    echo "<h2>ตรวจสอบ receive_items (ต้องไม่มีข้อมูลที่ยกเลิก)</h2>";
    
    $sql2 = "SELECT ri.*, p.name as product_name, poi.cancel_qty
    FROM receive_items ri
    LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
    LEFT JOIN products p ON ri.product_id = p.product_id
    WHERE poi.cancel_qty > 0
    ORDER BY ri.created_at DESC
    LIMIT 20";

    $stmt2 = $pdo->query($sql2);
    $wrong_records = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>ข้อมูลผิดวาง (ต้องว่าง):</strong> " . count($wrong_records) . " รายการ</p>";
    
    if (count($wrong_records) > 0) {
        echo "<table border='1' cellpadding='10' cellspacing='0' style='width:100%; font-size: 12px; background: #fee2e2;'>";
        echo "<tr style='background: #dc2626; color: white;'>";
        echo "<th>รหัส Receive</th>";
        echo "<th>Item ID</th>";
        echo "<th>สินค้า</th>";
        echo "<th>จำนวน Receive</th>";
        echo "<th>จำนวนที่ยกเลิก</th>";
        echo "<th>สถานะ</th>";
        echo "</tr>";
        
        foreach ($wrong_records as $record) {
            echo "<tr>";
            echo "<td>{$record['receive_id']}</td>";
            echo "<td>{$record['item_id']}</td>";
            echo "<td>" . htmlspecialchars($record['product_name']) . "</td>";
            echo "<td>{$record['receive_qty']}</td>";
            echo "<td style='color: red;'>{$record['cancel_qty']}</td>";
            echo "<td style='color: red; font-weight: bold;'>❌ ข้อมูลผิด</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p style='color: red; font-weight: bold;'>⚠️ พบข้อมูลผิด! receive_items มีข้อมูลที่ยกเลิก ต้องลบออก!</p>";
    } else {
        echo "<p style='color: green; font-weight: bold;'>✓ ถูกต้อง receive_items ไม่มีข้อมูลที่ยกเลิก</p>";
    }

    echo "<br><br>";

    // ตรวจสอบสถานะการรับสินค้า
    echo "<h2>ตรวจสอบสถานะ (received + cancelled >= ordered = สมบูรณ์)</h2>";
    
    $sql3 = "SELECT 
        po.po_id,
        po.po_number,
        COUNT(DISTINCT poi.item_id) as total_items,
        SUM(CASE WHEN (COALESCE(SUM(ri.receive_qty), 0) + COALESCE(poi.cancel_qty, 0)) >= poi.qty THEN 1 ELSE 0 END) as complete_items,
        COUNT(DISTINCT CASE WHEN (COALESCE(SUM(ri.receive_qty), 0) + COALESCE(poi.cancel_qty, 0)) > 0 AND (COALESCE(SUM(ri.receive_qty), 0) + COALESCE(poi.cancel_qty, 0)) < poi.qty THEN poi.item_id END) as partial_items,
        SUM(COALESCE(SUM(ri.receive_qty), 0)) as total_received,
        SUM(COALESCE(poi.cancel_qty, 0)) as total_cancelled
    FROM purchase_orders po
    LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
    LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
    WHERE po.status IN ('pending', 'partial', 'completed')
    GROUP BY po.po_id, po.po_number
    ORDER BY po.po_id DESC
    LIMIT 10";

    $stmt3 = $pdo->query($sql3);
    $po_status = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='10' cellspacing='0' style='width:100%; font-size: 12px;'>";
    echo "<tr style='background: #333; color: white;'>";
    echo "<th>PO ID</th>";
    echo "<th>PO Number</th>";
    echo "<th>ทั้งหมด</th>";
    echo "<th>สมบูรณ์</th>";
    echo "<th>บางส่วน</th>";
    echo "<th>ได้รับ</th>";
    echo "<th>ยกเลิก</th>";
    echo "<th>สถานะ</th>";
    echo "</tr>";

    foreach ($po_status as $po) {
        $status_text = "✓ สมบูรณ์";
        if ($po['complete_items'] < $po['total_items'] && $po['partial_items'] > 0) {
            $status_text = "⚠️ บางส่วน";
        } elseif ($po['partial_items'] == 0 && $po['complete_items'] == 0) {
            $status_text = "⏳ ยังไม่เริ่ม";
        }

        echo "<tr>";
        echo "<td>{$po['po_id']}</td>";
        echo "<td>{$po['po_number']}</td>";
        echo "<td align='center'>{$po['total_items']}</td>";
        echo "<td align='center'>{$po['complete_items']}</td>";
        echo "<td align='center'>{$po['partial_items']}</td>";
        echo "<td align='center'>{$po['total_received']}</td>";
        echo "<td align='center' style='color: red;'>{$po['total_cancelled']}</td>";
        echo "<td>{$status_text}</td>";
        echo "</tr>";
    }

    echo "</table>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
