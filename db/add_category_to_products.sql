-- เพิ่มคอลัมน์ product_category_id ในตาราง products
ALTER TABLE products 
ADD COLUMN product_category_id INT DEFAULT NULL,
ADD CONSTRAINT fk_products_category 
FOREIGN KEY (product_category_id) 
REFERENCES product_category(category_id) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

-- เพิ่มคอลัมน์เพิ่มเติม
ALTER TABLE products 
ADD COLUMN category_name VARCHAR(100) COMMENT 'ชื่อประเภท' DEFAULT NULL;

-- สร้าง index เพื่อเพิ่มประสิทธิภาพการค้นหา
CREATE INDEX idx_products_category ON products(product_category_id);
