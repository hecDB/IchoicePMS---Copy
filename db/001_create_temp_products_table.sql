-- ==========================================
-- Migration: Create temp_products table with all required columns
-- Created: Nov 16, 2025
-- Database: ichoice_
-- ==========================================

-- Create temp_products table
CREATE TABLE IF NOT EXISTS `temp_products` (
  `temp_product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(100) NOT NULL COMMENT 'ชื่อสินค้าเบื้องต้น',
  `product_category` varchar(100) DEFAULT NULL COMMENT 'ประเภทสินค้า',
  `product_image` longblob DEFAULT NULL COMMENT 'รูปภาพสินค้า (Base64 encoded)',
  `provisional_sku` varchar(255) DEFAULT NULL COMMENT 'SKU ชั่วคราว',
  `provisional_barcode` varchar(50) DEFAULT NULL COMMENT 'Barcode ชั่วคราว',
  `unit` varchar(20) DEFAULT 'ชิ้น' COMMENT 'หน่วยนับ',
  `remark` text DEFAULT NULL COMMENT 'หมายเหตุเพิ่มเติม',
  `status` enum('draft','pending_approval','approved','rejected','converted') DEFAULT 'draft' COMMENT 'สถานะ',
  `po_id` int(11) NOT NULL COMMENT 'ใบ PO ที่อ้างอิง',
  `created_by` int(11) NOT NULL COMMENT 'สร้างโดย user_id',
  `approved_by` int(11) DEFAULT NULL COMMENT 'อนุมัติโดย user_id',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่สร้าง',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'วันที่อนุมัติ',
  PRIMARY KEY (`temp_product_id`),
  KEY `fk_po_id` (`po_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_category` (`product_category`),
  CONSTRAINT `fk_po_id` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางสินค้าชั่วคราวสำหรับ PO ใหม่';

-- Add column to purchase_order_items to link temp products (if not already exists)
ALTER TABLE `purchase_order_items` 
ADD COLUMN IF NOT EXISTS `temp_product_id` int(11) DEFAULT NULL COMMENT 'ลิงก์ไปยัง temp_products หากเป็นสินค้าใหม่' AFTER `product_id`;

-- Add index if not already exists
ALTER TABLE `purchase_order_items` 
ADD KEY IF NOT EXISTS `idx_temp_product_id` (`temp_product_id`);
