-- สร้างตาราง sales_orders สำหรับเก็บข้อมูลรายการขาย
CREATE TABLE IF NOT EXISTS sales_orders (
    sale_order_id INT AUTO_INCREMENT PRIMARY KEY,
    issue_tag VARCHAR(50) NOT NULL COMMENT 'เลขแท็คส่งออก',
    platform VARCHAR(20) DEFAULT NULL COMMENT 'แพลตฟอร์มขาย (Shopee, Lazada)',
    total_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'ยอดรวมทั้งหมด',
    total_items INT DEFAULT 0 COMMENT 'จำนวนรายการสินค้าทั้งหมด',
    issued_by INT NOT NULL COMMENT 'ผู้ทำรายการ',
    remark TEXT DEFAULT NULL COMMENT 'หมายเหตุ',
    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่ขาย',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_issue_tag (issue_tag),
    INDEX idx_platform (platform),
    INDEX idx_sale_date (sale_date),
    INDEX idx_issued_by (issued_by),
    
    FOREIGN KEY (issued_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางรายการขายหลัก';

-- เพิ่มคอลัมน์ sale_order_id ในตาราง issue_items
ALTER TABLE issue_items 
ADD COLUMN sale_order_id INT DEFAULT NULL AFTER issue_id,
ADD INDEX idx_sale_order_id (sale_order_id),
ADD FOREIGN KEY (sale_order_id) REFERENCES sales_orders(sale_order_id) ON DELETE SET NULL;