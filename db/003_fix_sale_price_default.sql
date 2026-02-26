-- ==========================================
-- Migration: Fix sale_price default value
-- Created: Feb 26, 2026
-- Database: ichoice_
-- ==========================================

-- Add default value to sale_price column in purchase_order_items
ALTER TABLE `purchase_order_items` 
MODIFY COLUMN `sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ราคาขายต่อหน่วย (พร้อมส่วนลด)';

-- Display table structure to confirm
DESCRIBE `purchase_order_items`;
