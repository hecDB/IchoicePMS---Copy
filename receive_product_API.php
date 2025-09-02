<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "db_connect.php";

try {
    $keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
    if ($keyword === '') {
        echo json_encode(["status"=>"error","message"=>"กรุณาใส่คำค้นหา"]);
        exit;
    }

    $likeKeyword = "%$keyword%";

    // ดึงสินค้าพร้อม PO ที่ pending ตัวแรก (เก่าที่สุด)
    $sql = "
        SELECT 
            p.product_id,
            p.name AS product_name,
            p.sku,
            p.barcode,
            p.unit,
            p.image,
            po.po_id,
            po.po_number,
            po.status AS po_status
        FROM products AS p
        LEFT JOIN (
            SELECT poi.product_id, po.po_id, po.po_number, po.status
            FROM purchase_order_items AS poi
            INNER JOIN purchase_orders AS po ON poi.po_id = po.po_id
            WHERE po.status='pending'
            ORDER BY po.po_id ASC
        ) AS po ON po.product_id = p.product_id
        WHERE p.name LIKE :name OR p.sku LIKE :sku OR p.barcode LIKE :barcode
        GROUP BY p.product_id
        ORDER BY p.product_id ASC
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $likeKeyword,
        ':sku' => $likeKeyword,
        ':barcode' => $likeKeyword
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];
    foreach($rows as $row){
        $products[] = [
            "product_id" => $row['product_id'],
            "name" => $row['product_name'],
            "sku" => $row['sku'],
            "barcode" => $row['barcode'],
            "unit" => $row['unit'],
            "image" => $row['image'],
            "po_list" => $row['po_id'] ? [
                [
                    "po_id" => $row['po_id'],
                    "po_number" => $row['po_number'],
                    "status" => $row['po_status']
                ]
            ] : []
        ];
    }

    if(count($products) > 0){
        echo json_encode(["status"=>"success","data"=>$products], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["status"=>"not_found","message"=>"ไม่พบสินค้า"], JSON_UNESCAPED_UNICODE);
    }

} catch(Exception $e){
    echo json_encode(["status"=>"error","message"=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
