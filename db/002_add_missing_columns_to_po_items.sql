-- ==========================================
-- Migration: Add missing columns to purchase_order_items
-- Created: Nov 16, 2025
-- Database: ichoice_
-- ==========================================

-- Add missing columns to purchase_order_items table
ALTER TABLE `purchase_order_items` 
ADD COLUMN IF NOT EXISTS `temp_product_id` int(11) DEFAULT NULL COMMENT 'ลิงก์ไปยัง temp_products หากเป็นสินค้าใหม่' AFTER `product_id`,
ADD COLUMN IF NOT EXISTS `quantity` decimal(10,2) DEFAULT NULL COMMENT 'จำนวน (alias for qty)',
ADD COLUMN IF NOT EXISTS `unit_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคา/หน่วย (alias for price_per_unit)',
ADD COLUMN IF NOT EXISTS `unit` varchar(20) DEFAULT NULL COMMENT 'หน่วยนับ',
ADD COLUMN IF NOT EXISTS `po_item_amount` decimal(12,2) DEFAULT NULL COMMENT 'ยอดรวมรายการ';

-- Add index if not already exists
ALTER TABLE `purchase_order_items` 
ADD KEY IF NOT EXISTS `idx_temp_product_id` (`temp_product_id`);
