<?php
// ไฟล์ฟังก์ชันสำหรับตรวจสอบเลขแท็กตามรูปแบบที่กำหนด
require_once '../config/db_connect.php';

/**
 * ตรวจสอบเลขแท็กตามรูปแบบที่เปิดใช้งานในฐานข้อมูล
 * @param string $tag เลขแท็กที่ต้องการตรวจสอบ
 * @param string $platform แพลตฟอร์ม (Shopee, Lazada) หรือ null สำหรับตรวจสอบทุกแพลตฟอร์ม
 * @return array ผลการตรวจสอบ
 */
function validateTagNumber($tag, $platform = null) {
    global $pdo;
    
    try {
        // ดึงรูปแบบที่เปิดใช้งาน
        $sql = "SELECT * FROM tag_patterns WHERE is_active = 1";
        $params = [];
        
        if ($platform) {
            $sql .= " AND platform = ?";
            $params[] = $platform;
        }
        
        $sql .= " ORDER BY platform, created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ตรวจสอบกับแต่ละรูปแบบ
        foreach ($patterns as $pattern) {
            try {
                $regex = '/' . str_replace('/', '\/', $pattern['regex_pattern']) . '/';
                if (preg_match($regex, $tag) === 1) {
                    return [
                        'valid' => true,
                        'platform' => $pattern['platform'],
                        'pattern_name' => $pattern['pattern_name'],
                        'pattern_id' => $pattern['pattern_id'],
                        'description' => $pattern['description']
                    ];
                }
            } catch (Exception $e) {
                // หาก regex ผิดพลาด ข้ามรูปแบบนี้
                error_log("Invalid regex pattern: " . $pattern['regex_pattern'] . " - " . $e->getMessage());
                continue;
            }
        }
        
        return [
            'valid' => false,
            'platform' => null,
            'pattern_name' => null,
            'pattern_id' => null,
            'description' => 'ไม่พบรูปแบบที่ตรงกัน'
        ];
        
    } catch (Exception $e) {
        error_log("Tag validation error: " . $e->getMessage());
        return [
            'valid' => false,
            'platform' => null,
            'pattern_name' => null,
            'pattern_id' => null,
            'description' => 'เกิดข้อผิดพลาดในการตรวจสอบ'
        ];
    }
}

/**
 * ตรวจสอบเลขแท็กเฉพาะแพลตฟอร์ม Shopee
 * @param string $tag เลขแท็กที่ต้องการตรวจสอบ
 * @return array ผลการตรวจสอบ
 */
function validateShopeeTag($tag) {
    return validateTagNumber($tag, 'Shopee');
}

/**
 * ตรวจสอบเลขแท็กเฉพาะแพลตฟอร์ม Lazada
 * @param string $tag เลขแท็กที่ต้องการตรวจสอบ
 * @return array ผลการตรวจสอบ
 */
function validateLazadaTag($tag) {
    return validateTagNumber($tag, 'Lazada');
}

/**
 * ดึงรายการรูปแบบที่เปิดใช้งาน
 * @param string $platform แพลตฟอร์ม (หรือ null สำหรับทั้งหมด)
 * @return array รายการรูปแบบ
 */
function getActivePatterns($platform = null) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM tag_patterns WHERE is_active = 1";
        $params = [];
        
        if ($platform) {
            $sql .= " AND platform = ?";
            $params[] = $platform;
        }
        
        $sql .= " ORDER BY platform, created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get active patterns error: " . $e->getMessage());
        return [];
    }
}

/**
 * ตรวจสอบและแนะนำแพลตฟอร์ม
 * @param string $tag เลขแท็ก
 * @return array ข้อมูลแพลตฟอร์มที่แนะนำ
 */
function suggestPlatform($tag) {
    $shopeeResult = validateShopeeTag($tag);
    $lazadaResult = validateLazadaTag($tag);
    
    $suggestions = [];
    
    if ($shopeeResult['valid']) {
        $suggestions[] = [
            'platform' => 'Shopee',
            'confidence' => 'high',
            'pattern_name' => $shopeeResult['pattern_name']
        ];
    }
    
    if ($lazadaResult['valid']) {
        $suggestions[] = [
            'platform' => 'Lazada',
            'confidence' => 'high',
            'pattern_name' => $lazadaResult['pattern_name']
        ];
    }
    
    return [
        'tag' => $tag,
        'suggestions' => $suggestions,
        'has_matches' => count($suggestions) > 0
    ];
}

/**
 * ฟังก์ชันสำหรับ backward compatibility กับโค้ดเดิม
 */
if (!function_exists('detectPlatform')) {
    function detectPlatform($tag) {
        $result = validateTagNumber($tag);
        return $result['valid'] ? $result['platform'] : 'Unknown';
    }
}

if (!function_exists('isValidShopeeTag')) {
    function isValidShopeeTag($tag) {
        $result = validateShopeeTag($tag);
        return $result['valid'];
    }
}

if (!function_exists('isValidLazadaTag')) {
    function isValidLazadaTag($tag) {
        $result = validateLazadaTag($tag);
        return $result['valid'];
    }
}

/**
 * API endpoint สำหรับตรวจสอบเลขแท็ก (เรียกใช้ผ่าน AJAX)
 */
if (isset($_GET['action']) && $_GET['action'] === 'validate') {
    header('Content-Type: application/json');
    
    $tag = $_GET['tag'] ?? '';
    $platform = $_GET['platform'] ?? null;
    
    if (empty($tag)) {
        echo json_encode(['error' => 'กรุณาระบุเลขแท็ก']);
        exit;
    }
    
    $result = validateTagNumber($tag, $platform);
    echo json_encode($result);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'suggest') {
    header('Content-Type: application/json');
    
    $tag = $_GET['tag'] ?? '';
    
    if (empty($tag)) {
        echo json_encode(['error' => 'กรุณาระบุเลขแท็ก']);
        exit;
    }
    
    $result = suggestPlatform($tag);
    echo json_encode($result);
    exit;
}
?>