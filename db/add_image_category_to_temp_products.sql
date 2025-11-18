-- Migration: Add product image and category columns to temp_products table
-- Created: Date
-- Purpose: Support product image upload and category selection for new products in PO

ALTER TABLE `temp_products` 
ADD COLUMN `product_category` varchar(100) DEFAULT NULL COMMENT 'ประเภทสินค้า' AFTER `product_name`,
ADD COLUMN `product_image` longblob DEFAULT NULL COMMENT 'รูปภาพสินค้า (Base64 encoded)' AFTER `product_category`;

-- Index for category queries if needed
CREATE INDEX idx_category ON temp_products(product_category);
