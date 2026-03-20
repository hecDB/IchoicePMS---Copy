-- สร้างตารางเก็บข้อมูลใบกำกับภาษี และเอกสารอื่นๆ (ใบสำคัญจ่าย, ใบเสนอราคา, ใบแจ้งหนี้)
-- เพื่อรองรับระบบการสร้างเอกสารทางการเงิน

-- ตารางหลักเก็บข้อมูลเอกสาร
CREATE TABLE IF NOT EXISTS tax_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doc_type VARCHAR(50) NOT NULL COMMENT 'ประเภทเอกสาร: tax_invoice, payment_voucher, quotation, invoice',
    inv_no VARCHAR(100) NOT NULL UNIQUE COMMENT 'เลขที่เอกสาร',
    sales_tag VARCHAR(100) DEFAULT NULL COMMENT 'เลขแท็กรายการขายสินค้า (อ้างอิงจากแท็กสินค้า)',
    inv_date DATE NOT NULL COMMENT 'วันที่ออกเอกสาร',
    platform VARCHAR(100) DEFAULT NULL COMMENT 'ช่องทางการสั่งซื้อ: Shopee, Lazada, Tiktok, อื่นๆ',
    
    -- ข้อมูลลูกค้า
    customer_name VARCHAR(255) NOT NULL COMMENT 'ชื่อลูกค้า/บริษัท',
    customer_tax_id VARCHAR(20) DEFAULT NULL COMMENT 'เลขประจำตัวผู้เสียภาษี 13 หลัก',
    customer_address TEXT DEFAULT NULL COMMENT 'ที่อยู่ลูกค้า',
    
    -- ข้อมูลการคำนวณ
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'รวมเงิน (ยอดรวมก่อนหักส่วนลด)',
    discount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลด (รวม)',
    shipping DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ค่าจัดส่ง',
    before_vat DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'มูลค่าก่อนภาษี',
    vat DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ภาษีมูลค่าเพิ่ม 7%',
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'รวมทั้งสิ้น (ยอดรวมหลังภาษี)',
    special_discount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลดพิเศษ/ส่วนลดอื่น',
    payable DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'จำนวนเงินที่ชำระ (ยอดรวมสุทธิ)',
    amount_text VARCHAR(500) DEFAULT NULL COMMENT 'จำนวนเงินเป็นตัวอักษร (ภาษาไทย)',
    
    -- ข้อมูลการสร้างและแก้ไข
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่สร้างข้อมูล',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'วันที่แก้ไขข้อมูลล่าสุด',
    created_by INT DEFAULT NULL COMMENT 'รหัสผู้สร้างเอกสาร',
    
    -- สถานะเอกสาร
    status VARCHAR(20) DEFAULT 'active' COMMENT 'สถานะเอกสาร: active, cancelled, void',
    notes TEXT DEFAULT NULL COMMENT 'หมายเหตุเพิ่มเติม',
    
    INDEX idx_inv_no (inv_no),
    INDEX idx_doc_type (doc_type),
    INDEX idx_inv_date (inv_date),
    INDEX idx_sales_tag (sales_tag),
    INDEX idx_customer_name (customer_name),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บข้อมูลใบกำกับภาษีและเอกสารอื่นๆ';

-- ตารางเก็บรายละเอียดสินค้า/บริการในแต่ละเอกสาร
CREATE TABLE IF NOT EXISTS tax_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL COMMENT 'อ้างอิงไปยัง tax_invoices.id',
    seq INT NOT NULL DEFAULT 1 COMMENT 'ลำดับรายการสินค้า',
    
    -- ข้อมูลสินค้า/บริการ
    item_name VARCHAR(500) NOT NULL COMMENT 'รายละเอียดสินค้า/บริการ',
    qty DECIMAL(12,2) NOT NULL DEFAULT 1.00 COMMENT 'จำนวน',
    unit VARCHAR(50) NOT NULL DEFAULT 'ชิ้น' COMMENT 'หน่วยนับ',
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ราคาต่อหน่วย',
    total_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'จำนวนเงิน (qty * unit_price)',
    
    -- ข้อมูลเพิ่มเติม
    product_id INT DEFAULT NULL COMMENT 'อ้างอิงรหัสสินค้าใน products table (ถ้ามี)',
    notes TEXT DEFAULT NULL COMMENT 'หมายเหตุรายการสินค้า',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES tax_invoices(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_seq (seq),
    INDEX idx_item_name (item_name(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บรายละเอียดสินค้า/บริการในใบกำกับภาษี';

-- สร้าง view สำหรับดูข้อมูลรวม
CREATE OR REPLACE VIEW v_tax_invoices_summary AS
SELECT 
    ti.id,
    ti.doc_type,
    ti.inv_no,
    ti.sales_tag,
    ti.inv_date,
    ti.platform,
    ti.customer_name,
    ti.customer_tax_id,
    ti.payable,
    ti.status,
    COUNT(tii.id) as item_count,
    ti.created_at,
    ti.updated_at
FROM tax_invoices ti
LEFT JOIN tax_invoice_items tii ON ti.id = tii.invoice_id
GROUP BY ti.id
ORDER BY ti.created_at DESC;
