<?php
/**
 * API for getting missing products list
 */
header('Content-Type: application/json');
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$remark_group = isset($_GET['remark_group']) ? trim($_GET['remark_group']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 200;

if ($limit <= 0) {
    $limit = 200;
} elseif ($limit > 500) {
    $limit = 500;
}

try {
    // Build query with optional filters
    $sql = "SELECT 
                mp.missing_id,
                mp.product_id,
                mp.sku,
                mp.barcode,
                mp.product_name,
                mp.quantity_missing,
                                mp.remark,
                                mp.created_at,
                                u.name AS created_by_name,
                                mp.reported_by,
                                (
                                        SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                                        FROM receive_items ri_return
                                        WHERE ri_return.remark LIKE CONCAT('รับคืนจากเมนูสูญหาย (Missing ID: ', mp.missing_id, '%')
                                            AND ri_return.receive_qty > 0
                                ) AS is_returned,
                                (
                                        SELECT ri_return.created_at
                                        FROM receive_items ri_return
                                        WHERE ri_return.remark LIKE CONCAT('รับคืนจากเมนูสูญหาย (Missing ID: ', mp.missing_id, '%')
                                            AND ri_return.receive_qty > 0
                                        ORDER BY ri_return.receive_id DESC
                                        LIMIT 1
                                ) AS return_created_at,
                                (
                                        SELECT u_return.name
                                        FROM receive_items ri_return
                                        LEFT JOIN users u_return ON ri_return.created_by = u_return.user_id
                                        WHERE ri_return.remark LIKE CONCAT('รับคืนจากเมนูสูญหาย (Missing ID: ', mp.missing_id, '%')
                                            AND ri_return.receive_qty > 0
                                        ORDER BY ri_return.receive_id DESC
                                        LIMIT 1
                                ) AS return_created_by_name
            FROM missing_products mp
            LEFT JOIN users u ON mp.reported_by = u.user_id
            WHERE 1 = 1";

    $params = [];

    if ($filter_date !== '') {
        $sql .= " AND DATE(mp.created_at) = :filter_date";
        $params[':filter_date'] = $filter_date;
    }

    if ($search_term !== '') {
        $normalized = preg_replace('/\s+/u', ' ', $search_term);
        $keywords = preg_split('/\s+/u', $normalized);
        $keywordIndex = 0;

        foreach ($keywords as $keyword) {
            $trimmedKeyword = trim($keyword);
            if ($trimmedKeyword === '') {
                continue;
            }

            $escapedKeyword = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $trimmedKeyword);
            $likeValue = '%' . $escapedKeyword . '%';

            $columnExpressions = [
                'mp.product_name',
                'mp.sku',
                'mp.barcode',
                'mp.remark',
                'u.name',
                'CAST(mp.quantity_missing AS CHAR)'
            ];

            $likeClauses = [];
            foreach ($columnExpressions as $columnIndex => $column) {
                $paramName = ':search_' . $keywordIndex . '_' . $columnIndex;
                $likeClauses[] = "$column LIKE $paramName ESCAPE '\\\\'";
                $params[$paramName] = $likeValue;
            }

            if (!empty($likeClauses)) {
                $sql .= ' AND (' . implode(' OR ', $likeClauses) . ')';
                $keywordIndex++;
            }
        }
    }

    $allowedRemarkGroups = ['ชำรุด/สูญหาย', 'ส่งแทนสินค้าอื่น', 'ส่งตาม', 'ยืม', 'อื่นๆ', 'ไม่มีหมายเหตุ'];

    if ($remark_group !== '' && in_array($remark_group, $allowedRemarkGroups, true)) {
        if ($remark_group === 'ไม่มีหมายเหตุ') {
            $sql .= " AND (mp.remark IS NULL OR mp.remark = '')";
        } elseif ($remark_group === 'อื่นๆ') {
            $sql .= " AND (mp.remark IS NOT NULL AND mp.remark <> '' AND mp.remark NOT IN ('ชำรุด/สูญหาย', 'ส่งแทนสินค้าอื่น', 'ส่งตาม', 'ยืม'))";
        } else {
            $sql .= " AND mp.remark = :remark_group";
            $params[':remark_group'] = $remark_group;
        }
    }

    $sql .= "
            ORDER BY mp.created_at DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => count($results),
        'date' => $filter_date,
        'search' => $search_term,
        'remark_group' => $remark_group,
        'data' => $results
    ]);
    
} catch (Exception $e) {
    error_log("Error getting missing products: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
