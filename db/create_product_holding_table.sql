-- สร้างตารางพักสินค้า (Product Holding/Staging Table)
-- สำหรับเก็บสินค้าใกล้หมดอายุที่ต้องการแก้ไข SKU หรือข้อมูลอื่นก่อนนำกลับขายใหม่
CREATE TABLE IF NOT EXISTS product_holding (
    holding_id INT AUTO_INCREMENT PRIMARY KEY,
    holding_code VARCHAR(50) UNIQUE NOT NULL COMMENT 'รหัสอ้างอิงพักสินค้า เช่น HOLD-20251121-001',
    
    product_id INT NOT NULL COMMENT 'ID สินค้า',
    receive_id INT NOT NULL COMMENT 'ID การรับเข้า (เพื่อลดจำนวนในตาราง receive_items)',
    
    original_sku VARCHAR(50) COMMENT 'SKU เดิม',
    new_sku VARCHAR(50) COMMENT 'SKU ใหม่ (อาจจะยังไม่กำหนด)',
    
    holding_qty INT NOT NULL COMMENT 'จำนวนสินค้าที่พักไว้',
    cost_price DECIMAL(10, 2) COMMENT 'ราคาต้นทุนต่อหน่วย',
    sale_price DECIMAL(10, 2) COMMENT 'ราคาขายต่อหน่วย (พร้อมส่วนลด)',
    
    holding_reason VARCHAR(255) COMMENT 'เหตุผลการพักสินค้า เช่น "โปรโมชั่นสินค้าใกล้หมดอายุ"',
    promo_name VARCHAR(255) COMMENT 'ชื่อโปรโมชั่น',
    promo_discount INT COMMENT 'ส่วนลด (%)',
    
    expiry_date DATE COMMENT 'วันหมดอายุของสินค้า',
    days_to_expire INT COMMENT 'จำนวนวันที่เหลือก่อนหมดอายุ',
    
    status ENUM('holding', 'moved_to_sale', 'returned_to_stock', 'disposed') DEFAULT 'holding' COMMENT 'สถานะ: holding=พักไว้, moved_to_sale=ย้ายไปขาย, returned_to_stock=คืนกลับคลัง, disposed=ทำลาย',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'เวลาสร้างบันทึก',
    created_by INT COMMENT 'ผู้สร้าง (user_id)',
    moved_at DATETIME COMMENT 'เวลาที่ย้ายไปขาย',
    moved_by INT COMMENT 'ผู้ย้ายไปขาย',
    remark TEXT COMMENT 'หมายเหตุเพิ่มเติม',
    
    INDEX idx_holding_code (holding_code),
    INDEX idx_product_id (product_id),
    INDEX idx_receive_id (receive_id),
    INDEX idx_status (status),
    INDEX idx_expiry_date (expiry_date),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (moved_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางพักสินค้าใกล้หมดอายุ';
