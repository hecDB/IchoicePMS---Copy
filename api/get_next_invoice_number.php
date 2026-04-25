<?php
// ปิด error display แต่เปิด error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

// Set headers ก่อนสิ่งอื่นใด
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    // ล้าง output buffer ที่อาจมี
    ob_clean();
    
    // ตรวจสอบว่าไฟล์ config มีอยู่หรือไม่
    if (!file_exists('../config/db_connect.php')) {
        throw new Exception('ไม่พบไฟล์ config/db_connect.php');
    }
    
    require_once '../config/db_connect.php';
    
    // db_connect.php สร้างตัวแปร $pdo (PDO) ไม่ใช่ $conn (mysqli)
    // สร้าง mysqli connection สำหรับ API นี้
    $host = 'localhost';
    $port = '3306';
    $db   = 'ichoice_';
    $user = 'root';
    $pass = '';
    
    $conn = new mysqli($host, $user, $pass, $db, $port);
    
    // ตรวจสอบ connection
    if ($conn->connect_error) {
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . $conn->connect_error);
    }
    
    $conn->set_charset('utf8mb4');
    
    // รับค่าประเภทเอกสาร - แต่ละประเภทจะมีเลขแยกกัน
    $docType = isset($_GET['doc_type']) ? $_GET['doc_type'] : 'tax_invoice';
    
    // Validate doc_type
    $validDocTypes = ['tax_invoice', 'payment_voucher', 'quotation', 'invoice'];
    if (!in_array($docType, $validDocTypes)) {
        $docType = 'tax_invoice';
    }
    
    // ตรวจสอบว่าตาราง tax_invoices มีอยู่หรือไม่
    $tableCheck = $conn->query("SHOW TABLES LIKE 'tax_invoices'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        throw new Exception('ตาราง tax_invoices ยังไม่ถูกสร้าง กรุณารัน migration script');
    }
    
    // ตรวจสอบว่ามีคอลัมน์ doc_type หรือไม่
    $columnCheck = $conn->query("SHOW COLUMNS FROM tax_invoices LIKE 'doc_type'");
    if (!$columnCheck || $columnCheck->num_rows === 0) {
        throw new Exception('ตาราง tax_invoices ยังไม่มีคอลัมน์ doc_type กรุณารัน migration script');
    }
    
    // สร้างรูปแบบ YYYYMM สำหรับเดือนปัจจุบัน
    $currentYearMonth = date('Ym'); // เช่น 202604
    $prefix = $currentYearMonth . '-';
    
    // ค้นหาเลขที่ล่าสุดในเดือนนี้ สำหรับประเภทเอกสารนี้โดยเฉพาะ
    $stmt = $conn->prepare("
        SELECT inv_no 
        FROM tax_invoices 
        WHERE inv_no LIKE ? 
        AND doc_type = ?
        ORDER BY inv_no DESC 
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception('ไม่สามารถสร้าง prepared statement: ' . $conn->error);
    }
    
    $likePattern = $prefix . '%';
    $stmt->bind_param('ss', $likePattern, $docType);
    
    if (!$stmt->execute()) {
        throw new Exception('ไม่สามารถ execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $nextNumber = 1;
    if ($row = $result->fetch_assoc()) {
        // แยกเลขท้ายออกมา เช่น 202604-001 -> 001
        $lastInvNo = $row['inv_no'];
        if (preg_match('/' . preg_quote($prefix, '/') . '(\d+)$/', $lastInvNo, $matches)) {
            $lastNumber = intval($matches[1]);
            $nextNumber = $lastNumber + 1;
        }
    }
    
    // สร้างเลขใหม่ในรูปแบบ 001, 002, ...
    $newInvNo = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    
    // ล้าง output buffer ก่อน echo JSON
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'inv_no' => $newInvNo,
        'prefix' => $prefix,
        'next_number' => $nextNumber,
        'doc_type' => $docType
    ], JSON_UNESCAPED_UNICODE);
    
    // ส่ง output และหยุดการทำงาน
    ob_end_flush();
    
    $stmt->close();
    $conn->close();
    exit;
    
} catch (Exception $e) {
    // ล้าง output buffer ก่อน echo error
    ob_clean();
    
    // Log error ไปยัง PHP error log
    error_log('API Error (get_next_invoice_number.php): ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    ob_end_flush();
    exit;
}
?>
