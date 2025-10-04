-- Migration script to add currency support to purchase_order_items table
-- Date: 2025-10-04
-- Purpose: Add currency and original_price columns to support multi-currency

USE ichoice_;

-- Add new columns for currency support
ALTER TABLE purchase_order_items 
ADD COLUMN currency VARCHAR(3) DEFAULT 'THB' COMMENT 'Currency code (THB, USD, etc.)',
ADD COLUMN original_price DECIMAL(10,2) NULL COMMENT 'Original price in the specified currency';

-- Update existing records to have default currency
UPDATE purchase_order_items 
SET currency = 'THB', 
    original_price = price_per_unit 
WHERE currency IS NULL;

-- Add index for better performance on currency queries
ALTER TABLE purchase_order_items 
ADD INDEX idx_currency (currency);

-- Verify the changes
DESCRIBE purchase_order_items;

-- Show sample data
SELECT item_id, po_id, product_id, qty, price_per_unit, original_price, currency, total 
FROM purchase_order_items 
LIMIT 5;