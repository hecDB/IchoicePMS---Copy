-- Create Borrow System Tables

-- หมวดหมู่การยืม
CREATE TABLE IF NOT EXISTS borrow_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ตารางการยืมสินค้า
CREATE TABLE IF NOT EXISTS item_borrows (
    borrow_id INT AUTO_INCREMENT PRIMARY KEY,
    borrow_number VARCHAR(50) NOT NULL UNIQUE,
    borrow_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    category_id INT,
    borrower_name VARCHAR(100) NOT NULL,
    borrower_phone VARCHAR(20),
    borrower_email VARCHAR(100),
    purpose TEXT,
    expected_return_date DATETIME,
    actual_return_date DATETIME NULL,
    status ENUM('active', 'returned', 'overdue', 'cancelled') DEFAULT 'active',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES borrow_categories(category_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_status (status),
    INDEX idx_borrow_date (borrow_date),
    INDEX idx_expected_return (expected_return_date)
);

-- ตารางรายการสินค้าที่ยืม
CREATE TABLE IF NOT EXISTS borrow_items (
    borrow_item_id INT AUTO_INCREMENT PRIMARY KEY,
    borrow_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(50),
    qty INT NOT NULL,
    unit VARCHAR(20),
    image LONGBLOB,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (borrow_id) REFERENCES item_borrows(borrow_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    INDEX idx_borrow_id (borrow_id)
);

-- Insert default borrow categories
INSERT IGNORE INTO borrow_categories (category_id, category_name, description) VALUES
(1, 'โฆษณา / Marketing', 'สินค้าใช้สำหรับการโฆษณาหรือการตลาด'),
(2, 'ตรวจสอบ / QC', 'สินค้าสำหรับการตรวจสอบคุณภาพหรือทดสอบ'),
(3, 'เปรียบเทียบ / Demo', 'สินค้าสำหรับการสาธิตหรือเปรียบเทียบกับคู่แข่ง'),
(4, 'วิจัย / Research', 'สินค้าสำหรับการวิจัยและพัฒนา'),
(5, 'อื่นๆ', 'อื่นๆ');

-- Create stored procedure to generate borrow number
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS GenerateBorrowNumber()
BEGIN
    -- Returns the next borrow number in format: BRW-YYYY-XXXXXX
    SELECT CONCAT('BRW-', DATE_FORMAT(NOW(), '%Y'), '-', LPAD(COUNT(*) + 1, 6, '0'))
    FROM item_borrows
    WHERE borrow_date >= DATE_FORMAT(NOW(), '%Y-01-01');
END //
DELIMITER ;
