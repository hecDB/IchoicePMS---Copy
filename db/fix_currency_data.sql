-- Fix for missing currency data
-- เพิ่มข้อมูลสกุลเงินหากยังไม่มี

-- ตรวจสอบและเพิ่มตาราง currencies หากยังไม่มี
CREATE TABLE IF NOT EXISTS currencies (
    currency_id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(3) NOT NULL UNIQUE COMMENT 'รหัสสกุลเงิน เช่น THB, USD',
    name VARCHAR(50) NOT NULL COMMENT 'ชื่อเต็มของสกุลเงิน',
    symbol VARCHAR(5) NOT NULL COMMENT 'สัญลักษณ์ เช่น ฿, $',
    exchange_rate DECIMAL(10,6) DEFAULT 1.0000 COMMENT 'อัตราแลกเปลี่ยนต่อบาท',
    is_base BOOLEAN DEFAULT FALSE COMMENT 'เป็นสกุลเงินหลักหรือไม่',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'ใช้งานได้หรือไม่',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- เพิ่มข้อมูลสกุลเงินเริ่มต้น
INSERT IGNORE INTO currencies (code, name, symbol, exchange_rate, is_base, is_active) VALUES
('THB', 'Thai Baht', '฿', 1.0000, TRUE, TRUE),
('USD', 'US Dollar', '$', 35.0000, FALSE, TRUE),
('EUR', 'Euro', '€', 38.0000, FALSE, TRUE);

-- อัปเดตอัตราแลกเปลี่ยนหากมีข้อมูลแล้ว
UPDATE currencies SET 
    exchange_rate = CASE 
        WHEN code = 'USD' THEN 35.0000
        WHEN code = 'EUR' THEN 38.0000
        ELSE exchange_rate
    END,
    updated_at = CURRENT_TIMESTAMP
WHERE code IN ('USD', 'EUR') AND is_base = FALSE;

-- แสดงผลลัพธ์
SELECT 'Currency data updated successfully' as message;
SELECT * FROM currencies ORDER BY is_base DESC, code ASC;