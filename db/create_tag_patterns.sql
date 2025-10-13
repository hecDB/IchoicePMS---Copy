-- สร้างตาราง tag_patterns สำหรับจัดการรูปแบบเลขแท็ก
CREATE TABLE IF NOT EXISTS tag_patterns (
    pattern_id INT AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(50) NOT NULL COMMENT 'แพลตฟอร์ม (Shopee, Lazada)',
    pattern_name VARCHAR(100) NOT NULL COMMENT 'ชื่อรูปแบบ',
    description TEXT COMMENT 'คำอธิบายรูปแบบ',
    regex_pattern VARCHAR(500) NOT NULL COMMENT 'Regular Expression Pattern',
    example_tags TEXT COMMENT 'ตัวอย่างเลขแท็ก (คั่นด้วยจุลภาค)',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'สถานะการใช้งาน (1=ใช้งาน, 0=ปิด)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_platform (platform),
    INDEX idx_active (is_active),
    INDEX idx_platform_active (platform, is_active)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มข้อมูลตัวอย่าง
INSERT INTO tag_patterns (platform, pattern_name, description, regex_pattern, example_tags, is_active) VALUES
('Shopee', 'Shopee Standard', 'รูปแบบมาตรฐานของ Shopee ขึ้นต้นด้วย SPE ตามด้วยตัวเลข 10 หลัก', '^SPE[0-9]{10}$', 'SPE1234567890,SPE9876543210', 1),
('Shopee', 'Shopee New Format', 'รูปแบบใหม่ของ Shopee ตัวเลข 12 หลัก ลงท้ายด้วย SH', '^[0-9]{12}SH$', '123456789012SH,987654321098SH', 1),
('Lazada', 'Lazada Standard', 'รูปแบบมาตรฐานของ Lazada ขึ้นต้นด้วย LAZ ตามด้วยตัวเลข 8-12 หลัก', '^LAZ[0-9]{8,12}$', 'LAZ12345678,LAZ123456789012', 1),
('Lazada', 'Lazada Numeric Only', 'รูปแบบตัวเลขล้วน 10-15 หลัก สำหรับ Lazada', '^[0-9]{10,15}$', '1234567890123,9876543210987', 0);