<?php
/**
 * API สำหรับจัดการอัตราแลกเปลี่ยน
 * รองรับการอัปเดตอัตราแลกเปลี่ยนจากแหล่งข้อมูลภายนอก
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

include '../config/db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // ดึงรายการสกุลเงินทั้งหมด
            if (isset($_GET['active_only'])) {
                $stmt = $pdo->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY is_base DESC, code ASC");
            } else {
                $stmt = $pdo->query("SELECT * FROM currencies ORDER BY is_base DESC, code ASC");
            }
            $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'currencies' => $currencies,
                'base_currency' => array_filter($currencies, function($c) {
                    return $c['is_base'] == 1;
                })[0] ?? null
            ]);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['update_rates'])) {
                // อัปเดตอัตราแลกเปลี่ยนหลายสกุลพร้อมกัน
                $rates = $data['rates']; // array of ['currency_id' => rate]
                $updated = 0;
                
                $pdo->beginTransaction();
                
                foreach ($rates as $currency_id => $rate) {
                    $stmt = $pdo->prepare("UPDATE currencies SET exchange_rate = ?, updated_at = NOW() WHERE currency_id = ? AND is_base = 0");
                    if ($stmt->execute([$rate, $currency_id])) {
                        $updated++;
                    }
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => "อัปเดตอัตราแลกเปลี่ยน {$updated} สกุลเงินเรียบร้อย"
                ]);
                
            } elseif (isset($data['currency_id']) && isset($data['exchange_rate'])) {
                // อัปเดตอัตราแลกเปลี่ยนสกุลเดียว
                $stmt = $pdo->prepare("UPDATE currencies SET exchange_rate = ?, updated_at = NOW() WHERE currency_id = ? AND is_base = 0");
                
                if ($stmt->execute([$data['exchange_rate'], $data['currency_id']])) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'อัปเดตอัตราแลกเปลี่ยนเรียบร้อย'
                    ]);
                } else {
                    throw new Exception('ไม่สามารถอัปเดตอัตราแลกเปลี่ยนได้');
                }
                
            } else {
                throw new Exception('ข้อมูลไม่ครบถ้วน');
            }
            break;
            
        case 'PUT':
            // เปิด/ปิดการใช้งานสกุลเงิน
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['currency_id']) || !isset($data['is_active'])) {
                throw new Exception('ข้อมูลไม่ครบถ้วน');
            }
            
            $stmt = $pdo->prepare("UPDATE currencies SET is_active = ?, updated_at = NOW() WHERE currency_id = ? AND is_base = 0");
            
            if ($stmt->execute([$data['is_active'], $data['currency_id']])) {
                echo json_encode([
                    'success' => true,
                    'message' => $data['is_active'] ? 'เปิดใช้งานสกุลเงินแล้ว' : 'ปิดใช้งานสกุลเงินแล้ว'
                ]);
            } else {
                throw new Exception('ไม่สามารถอัปเดตสถานะได้');
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * ฟังก์ชันดึงอัตราแลกเปลี่ยนจาก API ภายนอก (ตัวอย่าง)
 * ในการใช้งานจริงควรใช้ API ที่เชื่อถือได้ เช่น
 * - exchangerate-api.com
 * - fixer.io
 * - Bank of Thailand API
 */
function fetchExchangeRatesFromAPI() {
    // ตัวอย่างการเรียก API (ปิดไว้เพื่อป้องกันการเรียกจริง)
    /*
    $api_url = "https://api.exchangerate-api.com/v4/latest/THB";
    $response = file_get_contents($api_url);
    $data = json_decode($response, true);
    
    if ($data && isset($data['rates'])) {
        return [
            'USD' => 1 / $data['rates']['USD'], // แปลงเป็นอัตราต่อบาท
            'EUR' => 1 / $data['rates']['EUR'],
            'JPY' => 1 / $data['rates']['JPY'],
            'GBP' => 1 / $data['rates']['GBP']
        ];
    }
    */
    
    // ข้อมูลจำลองสำหรับทดสอบ
    return [
        'USD' => 39.25,
        'EUR' => 42.15,
        'JPY' => 0.26,
        'GBP' => 49.80
    ];
}
?>