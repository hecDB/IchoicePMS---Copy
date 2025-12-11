<?php
/**
 * API to compute missing product statistics grouped by remark/preset type.
 */
header('Content-Type: application/json');
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $sql = "SELECT
                CASE
                    WHEN mp.remark IS NULL OR mp.remark = '' THEN 'ไม่มีหมายเหตุ'
                    WHEN mp.remark IN ('ชำรุด/สูญหาย', 'ส่งแทนสินค้าอื่น', 'ส่งตาม', 'ยืม') THEN mp.remark
                    ELSE 'อื่นๆ'
                END AS remark_group,
                SUM(mp.quantity_missing) AS total_quantity,
                COUNT(*) AS total_records
            FROM missing_products mp
            GROUP BY remark_group
            ORDER BY remark_group";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
} catch (Exception $e) {
    error_log('Error getting missing product stats: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
