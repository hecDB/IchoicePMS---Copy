<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require '../config/db_connect.php';

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่ได้รับอนุญาตให้เข้าถึง']);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Tag Management API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ']);
}

// ฟังก์ชันจัดการ GET request
function handleGet() {
    global $pdo;
    
    if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['pattern_id'])) {
        // ดึงข้อมูลรูปแบบเดียว
        $stmt = $pdo->prepare("SELECT * FROM tag_patterns WHERE pattern_id = ?");
        $stmt->execute([$_GET['pattern_id']]);
        $pattern = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pattern) {
            echo json_encode(['success' => true, 'data' => $pattern]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบรูปแบบที่ระบุ']);
        }
    } elseif (isset($_GET['action']) && $_GET['action'] === 'check_usage' && isset($_GET['pattern_id'])) {
        // ตรวจสอบการใช้งานรูปแบบ
        $stmt = $pdo->prepare("SELECT platform, regex_pattern, pattern_name FROM tag_patterns WHERE pattern_id = ?");
        $stmt->execute([$_GET['pattern_id']]);
        $pattern = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pattern) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบรูปแบบที่ระบุ']);
            return;
        }
        
        // นับการใช้งาน
        $usage_count = 0;
        $examples = [];
        
        try {
            // ดึงรายการขายที่ตรงกับแพลตฟอร์มนี้
            $stmt = $pdo->prepare("
                SELECT issue_tag, DATE_FORMAT(sale_date, '%d/%m/%Y') as sale_date 
                FROM sales_orders 
                WHERE platform = ? 
                ORDER BY sale_date DESC 
                LIMIT 100
            ");
            $stmt->execute([$pattern['platform']]);
            $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ตรวจสอบว่าแท็กไหนตรงกับรูปแบบ
            foreach ($tags as $tag_data) {
                if (preg_match('/' . $pattern['regex_pattern'] . '/', $tag_data['issue_tag'])) {
                    $usage_count++;
                    if (count($examples) < 5) { // เก็บตัวอย่างไว้ 5 รายการ
                        $examples[] = $tag_data;
                    }
                }
            }
        } catch (Exception $e) {
            // หากเกิดข้อผิดพลาดในการตรวจสอบ regex
            $usage_count = 0;
            $examples = [];
        }
        
        echo json_encode([
            'success' => true, 
            'usage_count' => $usage_count,
            'examples' => $examples,
            'pattern_name' => $pattern['pattern_name']
        ]);
    } else {
        // ดึงข้อมูลรูปแบบทั้งหมด
        $stmt = $pdo->query("SELECT * FROM tag_patterns ORDER BY platform, created_at DESC");
        $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $patterns]);
    }
}

// ฟังก์ชันจัดการ POST request
function handlePost() {
    global $pdo;
    
    if (isset($_POST['action']) && $_POST['action'] === 'test') {
        // ทดสอบรูปแบบ
        handleTest();
        return;
    }
    
    // บันทึกรูปแบบใหม่หรือแก้ไข
    $pattern_id = $_POST['pattern_id'] ?? null;
    $platform = trim($_POST['platform'] ?? '');
    $pattern_name = trim($_POST['pattern_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $regex_pattern = trim($_POST['regex_pattern'] ?? '');
    $example_tags = trim($_POST['example_tags'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($platform) || empty($pattern_name) || empty($regex_pattern)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน']);
        return;
    }
    
    // ตรวจสอบความถูกต้องของ regex
    if (@preg_match('/' . str_replace('/', '\/', $regex_pattern) . '/', '') === false) {
        echo json_encode(['success' => false, 'message' => 'รูปแบบ Regular Expression ไม่ถูกต้อง']);
        return;
    }
    
    try {
        if (empty($pattern_id)) {
            // สร้างใหม่
            $stmt = $pdo->prepare("
                INSERT INTO tag_patterns (platform, pattern_name, description, regex_pattern, example_tags, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$platform, $pattern_name, $description, $regex_pattern, $example_tags, $is_active]);
            echo json_encode(['success' => true, 'message' => 'เพิ่มรูปแบบเลขแท็กสำเร็จ']);
        } else {
            // แก้ไข
            $stmt = $pdo->prepare("
                UPDATE tag_patterns 
                SET platform = ?, pattern_name = ?, description = ?, regex_pattern = ?, example_tags = ?, is_active = ?, updated_at = NOW()
                WHERE pattern_id = ?
            ");
            $stmt->execute([$platform, $pattern_name, $description, $regex_pattern, $example_tags, $is_active, $pattern_id]);
            echo json_encode(['success' => true, 'message' => 'แก้ไขรูปแบบเลขแท็กสำเร็จ']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
    }
}

// ฟังก์ชันจัดการ PUT request
function handlePut() {
    global $pdo;
    
    parse_str(file_get_contents("php://input"), $input);
    
    if (isset($input['action']) && $input['action'] === 'toggle') {
        // เปิด/ปิดใช้งานรูปแบบ
        $pattern_id = $input['pattern_id'] ?? null;
        $is_active = $input['is_active'] ?? 0;
        
        if (empty($pattern_id)) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสรูปแบบ']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE tag_patterns SET is_active = ?, updated_at = NOW() WHERE pattern_id = ?");
            $stmt->execute([$is_active, $pattern_id]);
            
            $action_text = $is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
            echo json_encode(['success' => true, 'message' => $action_text . 'รูปแบบเลขแท็กสำเร็จ']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัพเดทข้อมูล']);
        }
    }
}

// ฟังก์ชันจัดการ DELETE request
function handleDelete() {
    global $pdo;
    
    // รับ pattern_id จากหลายแหล่งที่เป็นไปได้
    $pattern_id = $_GET['pattern_id'] ?? $_POST['pattern_id'] ?? null;
    
    // หากไม่พบใน GET/POST ให้ลองอ่านจาก raw input
    if (empty($pattern_id)) {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $data = json_decode($input, true);
            $pattern_id = $data['pattern_id'] ?? null;
        }
    }
    
    // หากยังไม่พบให้ลองจาก parse_str
    if (empty($pattern_id)) {
        parse_str($input, $params);
        $pattern_id = $params['pattern_id'] ?? null;
    }
    
    if (empty($pattern_id)) {
        echo json_encode([
            'success' => false, 
            'message' => 'ไม่พบรหัสรูปแบบที่ต้องการลบ'
        ]);
        return;
    }
    
    try {
        // ดึงข้อมูลรูปแบบที่จะลบ
        $stmt = $pdo->prepare("SELECT platform, regex_pattern FROM tag_patterns WHERE pattern_id = ?");
        $stmt->execute([$pattern_id]);
        $pattern = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pattern) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบรูปแบบที่ต้องการลบ']);
            return;
        }
        
        // ตรวจสอบการใช้งานโดยค้นหาจากแท็กที่ตรงกับรูปแบบ
        $usage_count = 0;
        try {
            // ดึงรายการขายที่ตรงกับแพลตฟอร์มนี้
            $stmt = $pdo->prepare("SELECT issue_tag FROM sales_orders WHERE platform = ?");
            $stmt->execute([$pattern['platform']]);
            $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // ตรวจสอบว่าแท็กไหนตรงกับรูปแบบที่จะลบ
            foreach ($tags as $tag) {
                if (preg_match('/' . $pattern['regex_pattern'] . '/', $tag)) {
                    $usage_count++;
                }
            }
        } catch (Exception $e) {
            // หากเกิดข้อผิดพลาดในการตรวจสอบ regex ให้ข้ามไป
            $usage_count = 0;
        }
        
        if ($usage_count > 0) {
            echo json_encode([
                'success' => false, 
                'message' => "ไม่สามารถลบรูปแบบนี้ได้ เนื่องจากมีการใช้งานอยู่ {$usage_count} รายการ กรุณาปิดการใช้งานแทน"
            ]);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM tag_patterns WHERE pattern_id = ?");
        $stmt->execute([$pattern_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'ลบรูปแบบเลขแท็กสำเร็จ']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบรูปแบบที่ต้องการลบ']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล']);
    }
}

// ฟังก์ชันทดสอบรูปแบบ
function handleTest() {
    global $pdo;
    
    $pattern_id = $_POST['pattern_id'] ?? null;
    $test_tags = $_POST['test_tags'] ?? [];
    
    if (empty($pattern_id)) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสรูปแบบ']);
        return;
    }
    
    // ดึงข้อมูลรูปแบบ
    $stmt = $pdo->prepare("SELECT * FROM tag_patterns WHERE pattern_id = ?");
    $stmt->execute([$pattern_id]);
    $pattern = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pattern) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรูปแบบที่ระบุ']);
        return;
    }
    
    $results = [];
    $regex = '/' . str_replace('/', '\/', $pattern['regex_pattern']) . '/';
    
    foreach ($test_tags as $tag) {
        $tag = trim($tag);
        if (!empty($tag)) {
            $is_valid = preg_match($regex, $tag) === 1;
            $results[] = [
                'tag' => $tag,
                'valid' => $is_valid,
                'pattern' => $pattern['regex_pattern']
            ];
        }
    }
    
    echo json_encode(['success' => true, 'results' => $results]);
}

// ฟังก์ชันตรวจสอบเลขแท็กตามรูปแบบที่เปิดใช้งาน (สำหรับใช้จากระบบอื่น)
function validateTag($tag, $platform = null) {
    global $pdo;
    
    $sql = "SELECT * FROM tag_patterns WHERE is_active = 1";
    $params = [];
    
    if ($platform) {
        $sql .= " AND platform = ?";
        $params[] = $platform;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($patterns as $pattern) {
        $regex = '/' . str_replace('/', '\/', $pattern['regex_pattern']) . '/';
        if (preg_match($regex, $tag) === 1) {
            return [
                'valid' => true,
                'pattern' => $pattern,
                'platform' => $pattern['platform']
            ];
        }
    }
    
    return ['valid' => false, 'pattern' => null, 'platform' => null];
}

// Export ฟังก์ชันสำหรับใช้จากไฟล์อื่น
if (!function_exists('validateTagNumber')) {
    function validateTagNumber($tag, $platform = null) {
        return validateTag($tag, $platform);
    }
}
?>