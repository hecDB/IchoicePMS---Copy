-- Create table for tracking missing/lost products
CREATE TABLE `missing_products` (
  `missing_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity_missing` decimal(10,2) NOT NULL COMMENT 'จำนวนที่สูญหายหรือหาไม่เจอ',
  `remark` text DEFAULT NULL COMMENT 'หมายเหตุเพิ่มเติม',
  `reported_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  KEY `fk_product_id` (`product_id`),
  KEY `fk_reported_by` (`reported_by`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ตารางบันทึกสินค้าสูญหายหรือหาไม่เจอ';

-- Create a transaction record in receive_items as a movement record
-- When a missing product is recorded, we'll create a negative entry in receive_items table
-- This keeps the transaction history consistent
