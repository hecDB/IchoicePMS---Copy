-- สร้างตารางประเภทสินค้า
CREATE TABLE IF NOT EXISTS product_category (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- เพิ่มข้อมูลประเภทสินค้า
INSERT INTO product_category (category_name, description) VALUES
('อาหารเสริม', 'วิตามิน อาหารเสริม สารอาหาร'),
('เครื่องใช้ไฟฟ้า', 'อุปกรณ์ไฟฟ้า เครื่องใช้ในครัว'),
('เครื่องสำอาง/ความงาม', 'เครื่องสำอาง สกินแคร์ ผลิตภัณฑ์ความงาม'),
('สำหรับแม่และเด็ก', 'ผลิตภัณฑ์แม่และเด็ก นม ผ้าอ้อม'),
('สัตว์เลี้ยง', 'อาหารสัตว์เลี้ยง อุปกรณ์สัตว์เลี้ยง'),
('เครื่องใช้ในบ้าน/ออฟฟิศ', 'เฟอร์นิเจอร์ เครื่องใช้บ้าน สำนักงาน');
