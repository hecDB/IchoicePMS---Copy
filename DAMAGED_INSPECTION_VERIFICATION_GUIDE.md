📋 DAMAGED ITEMS INSPECTION VERIFICATION FUNCTIONS
═════════════════════════════════════════════════════════════
File: returns/damaged_return_inspections.php
Updated: 2026-03-03

═════════════════════════════════════════════════════════════
1. OVERVIEW OF NEW VERIFICATION FUNCTIONS
═════════════════════════════════════════════════════════════

Added comprehensive verification logic to ensure proper handling of:
  ✓ Original products (สินค้าเดิม) vs New products (สินค้าใหม่)
  ✓ Sellable vs Discard dispositions
  ✓ Data validation before API submission
  ✓ User-friendly confirmation dialogs with detailed summaries
  ✓ Proper SKU/Barcode generation for each product type

═════════════════════════════════════════════════════════════
2. NEW VERIFICATION FUNCTIONS
═════════════════════════════════════════════════════════════

function isOriginalProduct(inspection: Object): Boolean
────────────────────────────────────────────────────────────
Purpose: Detect if product is original (existing) or new
Returns: true if product_id exists and > 0, false otherwise

Usage: 
  const isOriginal = isOriginalProduct(currentInspection);
  if (isOriginal) {
    // Handle original product logic
  } else {
    // Handle new product logic
  }

Example:
  Original product: product_id=15  → true
  New product: product_id=null/0   → false


function generateNewSku(inspection: Object, customSuffix?: String): String
────────────────────────────────────────────────────────────────────────────
Purpose: Generate appropriate new SKU based on product type
         For original: "ตำหนิ-" + original SKU
         For new: "ตำหนิ-" + custom or system-generated suffix

Returns: String (new SKU with "ตำหนิ-" prefix)

Parameters:
  - inspection: inspection data object with sku field
  - customSuffix (optional): custom suffix for new products

Usage:
  // Original product with SKU "PROD-001"
  generateNewSku(inspection) → "ตำหนิ-PROD-001"
  
  // New product without SKU
  generateNewSku(inspection) → "ตำหนิ-[TIMESTAMP]"
  
  // With custom suffix
  generateNewSku(inspection, "AUTO-001") → "ตำหนิ-AUTO-001"


function generateNewBarcode(itemId: Integer): String
──────────────────────────────────────────────────────
Purpose: Generate automatic barcode for defect product
Format: BAR-[itemId]-[timestamp][random]

Returns: String (unique barcode)

Usage:
  generateNewBarcode(12) 
  → "BAR-12-A2Z5K9X7R3L1"


function validateInspectionData(
  inspection: Object, 
  disposition: String, 
  restockQty: Number, 
  expiryDate: String, 
  newSku: String
): Object
─────────────────────────────────────────────────────────
Purpose: Comprehensive validation of all inspection data
Returns: { valid: Boolean, errors: Array, warnings: Array }

Validates:
  ✓ Required fields (inspection, SKU, restock qty)
  ✓ Quantity rules (> 0, <= returned qty)
  ✓ Expiry date (if applicable)
  ✓ Product type matching (original/new)

Usage:
  const validation = validateInspectionData(
    currentInspection, 
    'sellable', 
    2.00, 
    '2026-12-31', 
    'ตำหนิ-PROD-001'
  );
  if (!validation.valid) {
    console.log('Errors:', validation.errors);
  }

Returns Example:
  {
    valid: false,
    errors: [
      'จำนวนนำกลับไม่สามารถเกิน 5 ชิ้น',
      'วันหมดอายุต้องไม่เป็นอดีต'
    ],
    warnings: [
      '📝 สินค้าเดิม: ระบบจะสร้าง SKU/Barcode ใหม่ในตาราง products'
    ]
  }


function buildVerificationDialog(
  inspection: Object, 
  disposition: String
): String (HTML)
──────────────────────────────────────────────────────
Purpose: Generate comprehensive summary HTML for confirmation dialog
Returns: HTML string with formatted summary

Content includes:
  ✓ Product type badge (Original/New)
  ✓ Action summary based on disposition
  ✓ Data to be saved (SKU, barcode, quantity)
  ✓ Target table (products/temp_products)
  ✓ Warnings and notes

Usage:
  const html = buildVerificationDialog(inspection, 'sellable');
  // Returns formatted HTML for SweetAlert2 dialog


function showVerificationDialog(
  inspection: Object, 
  disposition: String, 
  newSku: String, 
  restockQty: Number, 
  expiryDate: String, 
  costPrice: Number, 
  salePrice: Number
): Promise<Boolean>
────────────────────────────────────────────────────────────
Purpose: Display comprehensive verification dialog before submission
Returns: Promise resolving to true if confirmed, false if cancelled

Features:
  ✓ Validates data first
  ✓ Shows detailed summary (type, SKU, qty, prices)
  ✓ Lists all system actions to be performed
  ✓ Displays warnings and notes
  ✓ Requires user confirmation

Usage:
  const confirmed = await showVerificationDialog(...);
  if (confirmed) {
    // Submit to API
  }

═════════════════════════════════════════════════════════════
3. WORKFLOW IMPLEMENTATIONS
═════════════════════════════════════════════════════════════

CASE 1: ORIGINAL PRODUCT (สินค้าเดิม)
═══════════════════════════════════════════════════════════

CASE 1.1: SELLABLE (ขายได้)
───────────────────────────────
Status: Inspection determines product can be sold

Processing:
  1️⃣  Create new SKU
      - Format: "ตำหนิ-" + original SKU
      - Example: "ตำหนิ-PROD-001"
  
  2️⃣  Create new barcode
      - Format: "BAR-[itemId]-[timestamp][random]"
      - Example: "BAR-12-A2Z5K9X7R3L1"
  
  3️⃣  Save to products table
      - Copy all relevant fields from original product
      - Update: sku, barcode, name (add "ตำหนิ-" prefix if needed)
      - Keep: unit, category, image, remarks, etc.
  
  4️⃣  Add to PO line items
      - Create purchase_order_items entry
      - Link new product_id to PO
  
  5️⃣  Record product movement
      - Log in product activity/stock movement
      - Quantity: restock_qty
      - Type: "Inspection Defect -> Restock"

Stored In:
  ├─ returned_items (inspection record)
  ├─ products (new defect product)
  ├─ purchase_order_items (PO integration)
  └─ product_activity (movement log)

Database Changes:
  • products.product_id = NEW (auto-increment)
  • products.sku = "ตำหนิ-[original]"
  • products.barcode = "BAR-..."
  • purchase_order_items.product_id = NEW
  • returned_items.new_product_id = NEW
  • returned_items.new_sku = "ตำหนิ-[original]"
  • returned_items.new_barcode = "BAR-..."
  • returned_items.status = 'completed'

Confirmations To User:
  ✓ SKU ใหม่: ตำหนิ-PROD-001
  ✓ บันทึกไปยัง products table
  ✓ เพิ่มรายการ PO
  ✓ บันทึกการเคลื่อนไหว


CASE 1.2: DISCARD (ทิ้ง/ใช้ไม่ได้)
─────────────────────────────────
Status: Product cannot be sold, marked for disposal

Processing:
  1️⃣  Update return record
      - Set status = 'completed'
      - Set return_status = 'completed'
      - Set is_returnable = 0 (not sellable)
  
  2️⃣  Store in history
      - Keep inspection notes (defect_notes)
      - No product creation
      - Record kept for reference only

Stored In:
  └─ returned_items (inspection record with is_returnable=0)

Database Changes:
  • returned_items.status = 'completed'
  • returned_items.return_status = 'completed'
  • returned_items.is_returnable = 0
  • returned_items.defect_notes = inspection findings

Confirmations To User:
  ✓ สินค้าจัดประเมินว่า: ทิ้ง/ใช้ไม่ได้
  ✓ เก็บไว้เป็นข้อมูลเฉยๆในระบบ
  ✓ ไม่บันทึกเข้าสต๊อก

═══════════════════════════════════════════════════════════

CASE 2: NEW PRODUCT (สินค้าใหม่)
═══════════════════════════════════════════════════════════

CASE 2.1: SELLABLE (ขายได้)
───────────────────────────────
Status: New product inspection allows restocking

Processing:
  1️⃣  Create new SKU
      - Format: "ตำหนิ-" + system-generated code
      - No original SKU available for reference
      - Example: "ตำหนิ-A2Z5K9X7"
  
  2️⃣  Create new barcode
      - Format: "BAR-[itemId]-[timestamp][random]"
      - Example: "BAR-12-A2Z5K9X7R3L1"
  
  3️⃣  Save to temp_products table
      - NEW product waiting for staff review
      - Fields: sku, barcode, name, category, unit
      - Flag: pending_approval = true
      - Price info: cost_price, sale_price (optional)
      - Expiry: expiry_date, batch info
  
  4️⃣  Add to PO line items
      - Link to temp_product_id
      - Track which PO the new product came from
  
  5️⃣  NOT yet in stock movement
      - Waits in temp_products
      - Must be approved before entering products

Stored In:
  ├─ returned_items (inspection record)
  ├─ temp_products (new product data for review)
  └─ purchase_order_items (PO reference, temp_product_id)

Database Changes:
  • temp_products.temp_product_id = NEW
  • temp_products.sku = "ตำหนิ-[auto]"
  • temp_products.barcode = "BAR-..."
  • purchase_order_items.temp_product_id = NEW
  • returned_items.temp_product_id = NEW
  • returned_items.new_sku = "ตำหนิ-[auto]"
  • returned_items.new_barcode = "BAR-..."
  • returned_items.status = 'completed'

Confirmations To User:
  ✓ SKU ใหม่: ตำหนิ-A2Z5K9X7 (temp)
  ✓ บันทึกไปยัง temp_products
  ✓ รอการอนุมัติและแก้ไขข้อมูลสินค้า
  ✓ เพิ่มรายการ PO


CASE 2.2: DISCARD (ทิ้ง/ใช้ไม่ได้)
─────────────────────────────────
Status: New product cannot be used

Processing:
  1️⃣  Update return record only
      - Set status = 'completed'
      - Set return_status = 'completed'
      - Set is_returnable = 0
  
  2️⃣  No temp_product creation
      - No inventory entry
      - No future product import

Stored In:
  └─ returned_items (inspection record with is_returnable=0)

Database Changes:
  • returned_items.status = 'completed'
  • returned_items.return_status = 'completed'
  • returned_items.is_returnable = 0
  • returned_items.defect_notes = inspection findings

Confirmations To User:
  ✓ สินค้าใหม่จัดประเมินว่า: ทิ้ง/ใช้ไม่ได้
  ✓ ไม่บันทึกเข้าระบบ
  ✓ เก็บข้อมูลเฉยๆเท่านั้น

═════════════════════════════════════════════════════════════
4. KEY VERIFICATION LOGIC
═════════════════════════════════════════════════════════════

BEFORE SUBMISSION:
  ✓ Check if product is original or new
  ✓ Validate all required fields
  ✓ Check SKU format and uniqueness
  ✓ Verify quantity is valid (> 0, <= returned)
  ✓ Verify expiry date if needed
  ✓ Build comprehensive summary
  ✓ Show detailed confirmation dialog
  ✓ Require user acknowledgment

DURING SUBMISSION:
  ✓ Disable submit button (prevent double-submit)
  ✓ Show loading indicator
  ✓ Send all validation data to API

AFTER CONFIRMATION:
  ✓ API handles database operations
  ✓ Show success message with summary
  ✓ Refresh inspection list
  ✓ Close detail form

ERROR HANDLING:
  ✓ Display validation errors
  ✓ Display API errors
  ✓ Re-enable form for correction
  ✓ Restore submit button

═════════════════════════════════════════════════════════════
5. USER INTERFACE ENHANCEMENTS
═════════════════════════════════════════════════════════════

Product Type Badge
  Original:  📦 สินค้าชนิดเดิม (BLUE)
  New:       🆕 สินค้าชนิดใหม่ (CYAN)

Submit Button Text (Dynamic)
  Original + Sellable: "✓ บันทึกสินค้าเดิมขายได้ (products table)"
  New + Sellable:      "✓ บันทึกสินค้าใหม่ขายได้ (temp_products)"
  Discard:             "✓ บันทึกสินค้าทิ้ง/ใช้ไม่ได้"

Verification Dialog
  Shows:
    ✓ Product type badge
    ✓ Detailed action list
    ✓ Target tables
    ✓ Summary of data
    ✓ Warnings and notes
  
  Requires: User confirmation before final submission

Success Message
  Lists all actions performed:
    ✓ SKU ใหม่: ตำหนิ-PROD-001
    ✓ บันทึกไปยัง products/temp_products table
    ✓ เพิ่มรายการ PO
    ✓ บันทึกการเคลื่อนไหว

═════════════════════════════════════════════════════════════
6. TESTING CHECKLIST
═════════════════════════════════════════════════════════════

ORIGINAL PRODUCT - SELLABLE
  ☐ Select original product damaged item
  ☐ Choose "ขายได้" disposition
  ☐ Fill in restock quantity
  ☐ Verify new SKU displays "ตำหนิ-[original]"
  ☐ Verify button text shows "products table"
  ☐ Click submit
  ☐ Verify dialog shows product type as "📦 สินค้าชนิดเดิม"
  ☐ Verify action list mentions "products table"
  ☐ Click "ยืนยัน"
  ☐ Verify success message shows correct table reference
  ☐ Check returned_items: new_product_id should be set
  ☐ Check products table: new product created with new SKU

ORIGINAL PRODUCT - DISCARD
  ☐ Select original product damaged item
  ☐ Choose "ทิ้ง/ใช้ไม่ได้" disposition
  ☐ Verify button text shows "ทิ้ง"
  ☐ Click submit
  ☐ Verify dialog shows "ทิ้ง/ใช้ไม่ได้" label
  ☐ Verify no "products table" action mentioned
  ☐ Click "ยืนยัน"
  ☐ Check returned_items: is_returnable=0, status=completed

NEW PRODUCT - SELLABLE
  ☐ Select new product damaged item (product_id=null)
  ☐ Choose "ขายได้" disposition
  ☐ Fill in restock quantity
  ☐ Fill in expiry date
  ☐ Verify new SKU displays "ตำหนิ-[auto]"
  ☐ Verify button text shows "temp_products"
  ☐ Click submit
  ☐ Verify dialog shows product type as "🆕 สินค้าชนิดใหม่"
  ☐ Verify action list mentions "temp_products"
  ☐ Click "ยืนยัน"
  ☐ Verify success message shows "temp_products" reference
  ☐ Check returned_items: temp_product_id should be set
  ☐ Check temp_products table: entry created with new SKU

NEW PRODUCT - DISCARD
  ☐ Select new product damaged item
  ☐ Choose "ทิ้ง/ใช้ไม่ได้" disposition
  ☐ Click submit
  ☐ Verify dialog shows "ทิ้ง/ใช้ไม่ได้" label
  ☐ Verify no "temp_products" action mentioned
  ☐ Click "ยืนยัน"
  ☐ Check returned_items: is_returnable=0, status=completed
  ☐ Check temp_products: no new entry created

═════════════════════════════════════════════════════════════

Implementation Status: ✅ COMPLETE
Verification Functions: 8 new functions added
UI Enhancements: Dynamic badges, buttons, and dialogs
API Integration: Ready for process_damaged_inspection endpoint
Database Support: All tables and columns prepared
Testing Status: Ready for QA

═════════════════════════════════════════════════════════════
