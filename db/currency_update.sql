-- อัปเดตฐานข้อมูลสำหรับรองรับหลายสกุลเงิน
-- สร้างตาราง currencies
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
INSERT INTO currencies (code, name, symbol, exchange_rate, is_base) VALUES
('THB', 'Thai Baht', '฿', 1.0000, TRUE),
('USD', 'US Dollar', '$', 39.0000, FALSE),
('EUR', 'Euro', '€', 42.0000, FALSE),
('JPY', 'Japanese Yen', '¥', 0.26, FALSE),
('GBP', 'British Pound', '£', 49.0000, FALSE)
ON DUPLICATE KEY UPDATE
exchange_rate = VALUES(exchange_rate),
updated_at = CURRENT_TIMESTAMP;

-- เพิ่มคอลัมน์ในตาราง purchase_orders
ALTER TABLE purchase_orders 
ADD COLUMN IF NOT EXISTS currency_id INT DEFAULT 1 COMMENT 'สกุลเงินที่ใช้',
ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(10,6) DEFAULT 1.0000 COMMENT 'อัตราแลกเปลี่ยนขณะสั่งซื้อ',
ADD COLUMN IF NOT EXISTS total_amount_original DECIMAL(15,2) DEFAULT 0.00 COMMENT 'ยอดรวมในสกุลเงินต้นฉบับ',
ADD COLUMN IF NOT EXISTS total_amount_base DECIMAL(15,2) DEFAULT 0.00 COMMENT 'ยอดรวมในสกุลเงินฐาน (บาท)';

-- เพิ่ม Foreign Key (ถ้ายังไม่มี)
ALTER TABLE purchase_orders 
ADD CONSTRAINT fk_purchase_orders_currency 
FOREIGN KEY (currency_id) REFERENCES currencies(currency_id)
ON UPDATE CASCADE ON DELETE RESTRICT;

-- เพิ่มคอลัมน์ในตาราง purchase_order_items
ALTER TABLE purchase_order_items 
ADD COLUMN IF NOT EXISTS currency_id INT DEFAULT 1 COMMENT 'สกุลเงินของรายการ',
ADD COLUMN IF NOT EXISTS price_original DECIMAL(10,2) DEFAULT 0.00 COMMENT 'ราคาต่อหน่วยในสกุลเงินต้นฉบับ',
ADD COLUMN IF NOT EXISTS price_base DECIMAL(10,2) DEFAULT 0.00 COMMENT 'ราคาต่อหน่วยในสกุลเงินฐาน (บาท)';

-- เพิ่ม Foreign Key สำหรับ purchase_order_items
ALTER TABLE purchase_order_items 
ADD CONSTRAINT fk_purchase_order_items_currency 
FOREIGN KEY (currency_id) REFERENCES currencies(currency_id)
ON UPDATE CASCADE ON DELETE RESTRICT;

-- สร้าง View สำหรับดูข้อมูล PO พร้อมสกุลเงิน
CREATE OR REPLACE VIEW v_purchase_orders_with_currency AS
SELECT 
    po.*,
    c.code as currency_code,
    c.name as currency_name,
    c.symbol as currency_symbol,
    s.name as supplier_name
FROM purchase_orders po
LEFT JOIN currencies c ON po.currency_id = c.currency_id
LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id;

-- สร้าง View สำหรับดูรายการสินค้าพร้อมสกุลเงิน
CREATE OR REPLACE VIEW v_purchase_order_items_with_currency AS
SELECT 
    poi.*,
    c.code as currency_code,
    c.symbol as currency_symbol,
    p.name as product_name,
    p.sku as product_sku
FROM purchase_order_items poi
LEFT JOIN currencies c ON poi.currency_id = c.currency_id
LEFT JOIN products p ON poi.product_id = p.product_id;

-- Index สำหรับประสิทธิภาพ
CREATE INDEX idx_purchase_orders_currency ON purchase_orders(currency_id);
CREATE INDEX idx_purchase_order_items_currency ON purchase_order_items(currency_id);
CREATE INDEX idx_currencies_active ON currencies(is_active);
CREATE INDEX idx_currencies_base ON currencies(is_base);