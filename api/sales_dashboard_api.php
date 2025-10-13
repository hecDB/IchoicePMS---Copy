<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

function getSalesDashboardStats($pdo, $dateFrom = null, $dateTo = null) {
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if ($dateFrom && !empty($dateFrom)) {
        $whereClause .= " AND DATE(so.sale_date) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo && !empty($dateTo)) {
        $whereClause .= " AND DATE(so.sale_date) <= ?";
        $params[] = $dateTo;
    }
    
    // ดึงข้อมูลสถิติรวม
    $totalQuery = "
        SELECT 
            COUNT(DISTINCT so.sale_order_id) as total_orders,
            COUNT(DISTINCT so.issue_tag) as total_tags,
            COALESCE(SUM(so.total_items), 0) as total_items,
            COALESCE(SUM(so.total_amount), 0) as total_amount
        FROM sales_orders so 
        $whereClause
    ";
    
    $stmt = $pdo->prepare($totalQuery);
    $stmt->execute($params);
    $totalStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลตามแพลตฟอร์ม
    $platformQuery = "
        SELECT 
            COALESCE(so.platform,
                CASE 
                    WHEN so.issue_tag REGEXP '^[A-Za-z]{5,6}[0-9]{8}$' THEN 'Shopee'
                    WHEN so.issue_tag REGEXP '^[0-9]{11}$' THEN 'Lazada'
                    ELSE 'General'
                END
            ) as platform,
            COUNT(DISTINCT so.sale_order_id) as order_count,
            COUNT(DISTINCT so.issue_tag) as tag_count,
            COALESCE(SUM(so.total_items), 0) as total_qty,
            COALESCE(SUM(so.total_amount), 0) as total_amount,
            COALESCE(AVG(so.total_amount), 0) as avg_amount
        FROM sales_orders so 
        $whereClause
        GROUP BY platform
        ORDER BY total_amount DESC
    ";
    
    $stmt = $pdo->prepare($platformQuery);
    $stmt->execute($params);
    $platformStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลยอดขายรายวัน (7 วันล่าสุด)
    $dailyQuery = "
        SELECT 
            DATE(so.sale_date) as sale_date,
            COUNT(DISTINCT so.sale_order_id) as daily_orders,
            COALESCE(SUM(so.total_amount), 0) as daily_amount
        FROM sales_orders so 
        WHERE DATE(so.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        " . ($dateFrom || $dateTo ? "AND DATE(so.sale_date) BETWEEN COALESCE(?, DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AND COALESCE(?, CURDATE())" : "") . "
        GROUP BY DATE(so.sale_date)
        ORDER BY sale_date DESC
        LIMIT 7
    ";
    
    $dailyParams = [];
    if ($dateFrom || $dateTo) {
        $dailyParams[] = $dateFrom ?: date('Y-m-d', strtotime('-7 days'));
        $dailyParams[] = $dateTo ?: date('Y-m-d');
    }
    
    $stmt = $pdo->prepare($dailyQuery);
    $stmt->execute($dailyParams);
    $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // จัดรูปแบบข้อมูลแพลตฟอร์ม
    $formattedPlatforms = [];
    foreach ($platformStats as $platform) {
        $platformName = $platform['platform'] ?: 'General';
        $formattedPlatforms[] = [
            'platform' => $platformName,
            'platform_display' => $platformName === 'Shopee' ? '🛍️ Shopee' : 
                                ($platformName === 'Lazada' ? '🛒 Lazada' : '📦 ทั่วไป'),
            'platform_class' => $platformName === 'Shopee' ? 'shopee-badge' : 
                               ($platformName === 'Lazada' ? 'lazada-badge' : 'badge bg-secondary'),
            'order_count' => (int)($platform['order_count'] ?: 0),
            'tag_count' => (int)($platform['tag_count'] ?: 0),
            'total_qty' => (int)($platform['total_qty'] ?: 0),
            'total_amount' => (float)($platform['total_amount'] ?: 0),
            'total_amount_formatted' => number_format((float)($platform['total_amount'] ?: 0), 2),
            'avg_amount' => (float)($platform['avg_amount'] ?: 0),
            'avg_amount_formatted' => number_format((float)($platform['avg_amount'] ?: 0), 2)
        ];
    }
    
    return [
        'total_stats' => [
            'total_orders' => (int)($totalStats['total_orders'] ?: 0),
            'total_tags' => (int)($totalStats['total_tags'] ?: 0),
            'total_items' => (int)($totalStats['total_items'] ?: 0),
            'total_amount' => (float)($totalStats['total_amount'] ?: 0),
            'total_amount_formatted' => number_format((float)($totalStats['total_amount'] ?: 0), 2)
        ],
        'platform_stats' => $formattedPlatforms,
        'daily_stats' => $dailyStats ?: []
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        $stats = getSalesDashboardStats($pdo, $dateFrom, $dateTo);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log("Sales Dashboard API Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>