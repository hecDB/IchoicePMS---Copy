📋 VERIFICATION FUNCTIONS - QUICK REFERENCE GUIDE
═════════════════════════════════════════════════════════════

✅ IMPLEMENTATION COMPLETE
File: returns/damaged_return_inspections.php
Functions: 8 new + 6 enhanced

═════════════════════════════════════════════════════════════
FUNCTION MAP
═════════════════════════════════════════════════════════════

┌─ DETECTION LAYER
│  └─ isOriginalProduct(inspection)
│     Detects: Is product in products table (original)?
│     Returns: Boolean (true/false)
│     Usage: First step in verification flow
│
├─ GENERATION LAYER
│  ├─ generateNewSku(inspection, customSuffix?)
│  │  Creates: "ตำหนิ-" prefixed SKU
│  │  For original: "ตำหนิ-" + original SKU
│  │  For new: "ตำหนิ-" + auto-generated suffix
│  │
│  └─ generateNewBarcode(itemId)
│     Creates: BAR-[itemId]-[timestamp][random]
│     Returns: Unique barcode string
│
├─ VALIDATION LAYER
│  └─ validateInspectionData(inspection, disposition, restockQty, expiryDate, newSku)
│     Checks: Required fields, quantity rules, dates
│     Returns: { valid: bool, errors: [], warnings: [] }
│     Usage: Before showing dialog
│
├─ PRESENTATION LAYER
│  ├─ buildVerificationDialog(inspection, disposition)
│  │  Returns: HTML string with summary
│  │  Contains: Product type, actions, target table, warnings
│  │
│  └─ showVerificationDialog(...all params...)
│     Shows: SweetAlert2 confirmation dialog
│     Requires: User click "ยืนยัน" to proceed
│     Returns: Promise<Boolean>
│
└─ SUBMISSION LAYER
   └─ submitInspection(event)
      Calls: showVerificationDialog()
      Submits: API call if user confirms
      Updates: List, resets form

═════════════════════════════════════════════════════════════
DECISION TREE
═════════════════════════════════════════════════════════════

User selects damaged item
    ↓
populateDetail(data) loads
    ├─ Detects product type: isOriginalProduct(data)
    ├─ Generates SKU: generateNewSku(data)
    ├─ Shows badge: 📦 or 🆕
    └─ Updates button text based on type
    ↓
User chooses disposition
    ├─ "ขายได้" → sellable = true
    └─ "ทิ้ง/ใช้ไม่ได้" → sellable = false
    ↓
User clicks submit
    ↓
submitInspection() called
    ├─ Validates data: validateInspectionData()
    ├─ If errors → Show & return (form stays open)
    └─ If OK → Continue...
    ↓
showVerificationDialog() called
    ├─ Validates again
    ├─ Builds HTML: buildVerificationDialog()
    ├─ Shows SweetAlert2 dialog
    └─ Waits for user confirmation
    ↓
User clicks "ยืนยัน"
    ├─ API call: process_damaged_inspection
    ├─ API executes: Based on product type & disposition
    └─ (Original+Sellable → products table)
       (New+Sellable → temp_products table)
       (Discard → Just update status)
    ↓
Success!
    ├─ Show success message
    ├─ Refresh list
    └─ Close form

═════════════════════════════════════════════════════════════
CASE ROUTING
═════════════════════════════════════════════════════════════

isOriginalProduct = TRUE (has product_id > 0)
    ├─ Disposition = SELLABLE
    │   → "📦 สินค้าเดิมขายได้"
    │   → Products table storage
    │   → "✓ บันทึกสินค้าเดิมขายได้ (products table)"
    │
    └─ Disposition = DISCARD
        → "📦 สินค้าเดิมทิ้ง"
        → Status only (no insert)
        → "✓ บันทึกสินค้าทิ้ง/ใช้ไม่ได้"

isOriginalProduct = FALSE (product_id = null)
    ├─ Disposition = SELLABLE
    │   → "🆕 สินค้าใหม่ขายได้"
    │   → Temp_products table storage
    │   → "✓ บันทึกสินค้าใหม่ขายได้ (temp_products)"
    │
    └─ Disposition = DISCARD
        → "🆕 สินค้าใหม่ทิ้ง"
        → Status only (no insert)
        → "✓ บันทึกสินค้าทิ้ง/ใช้ไม่ได้"

═════════════════════════════════════════════════════════════
DATA FLOW
═════════════════════════════════════════════════════════════

INPUT (from form)
    ├─ inspection_id
    ├─ disposition (sellable/discard)
    ├─ restock_qty
    ├─ expiry_date
    ├─ cost_price
    └─ sale_price

DETECTION & GENERATION
    ├─ isOriginalProduct() → product_type: "original" | "new"
    ├─ generateNewSku() → new_sku: "ตำหนิ-..."
    └─ generateNewBarcode() → new_barcode: "BAR-..."

VALIDATION
    └─ validateInspectionData()
        ├─ errors[] → If any, show form error
        └─ warnings[] → Show in confirmation dialog

CONFIRMATION
    └─ showVerificationDialog()
        ├─ Shows summary (type, sku, qty, price)
        ├─ Lists planned actions
        ├─ Shows warnings
        └─ Requires "ยืนยัน" click

SUBMISSION
    └─ submitInspection() → fetch API
        ├─ POST process_damaged_inspection
        └─ Payload includes:
            - inspection_id
            - disposition
            - new_sku
            - restock_qty
            - expiry_date
            - cost_price/sale_price

API PROCESSING
    └─ Conditional table insert/update
        ├─ Original + Sellable → INSERT products
        ├─ New + Sellable → INSERT temp_products
        └─ Discard → UPDATE returned_items only

OUTPUT (to user)
    └─ Success message
        ├─ Shows new SKU
        ├─ Shows target table
        └─ Shows all cached actions

═════════════════════════════════════════════════════════════
IMPLEMENTATION LOCATIONS
═════════════════════════════════════════════════════════════

returns/damaged_return_inspections.php
    Line ~703  → isOriginalProduct()
    Line ~714  → generateNewSku()
    Line ~734  → generateNewBarcode()
    Line ~749  → validateInspectionData()
    Line ~803  → buildVerificationDialog()
    Line ~860  → showVerificationDialog()
    Line ~?    → populateDetail() [ENHANCED]
    Line ~?    → submitInspection() [ENHANCED]
    Line ~?    → dispositionSelect change handler [UPDATED]

api/returned_items_api.php
    Line ~1245 → process_damaged_inspection action [READY]
              → Already handles both original & new products
              → Creates appropriate table entries

═════════════════════════════════════════════════════════════
TESTING VERIFICATION
═════════════════════════════════════════════════════════════

To verify functions are working:

1. Open returns/damaged_return_inspections.php
2. Press F12 → Console
3. Paste this command:
   
   console.log('Available functions:',
     typeof isOriginalProduct,
     typeof generateNewSku,
     typeof generateNewBarcode,
     typeof validateInspectionData,
     typeof buildVerificationDialog,
     typeof showVerificationDialog
   );

4. Should see: "function function function function function function"

Or run full test:
   Load & execute: test_verification_functions.js (from browser console)

═════════════════════════════════════════════════════════════
KEY VALIDATION RULES
═════════════════════════════════════════════════════════════

MUST PASS (errors):
  ✓ inspection_id valid & exists
  ✓ new_sku not empty
  ✓ restock_qty > 0
  ✓ restock_qty ≤ return_qty
  ✓ If sellable: expiry_date not in past

SHOULD SHOW (warnings):
  ⚠ Product type info (original/new)
  ⚠ Target table (products/temp_products)
  ⚠ Disposal action (sell/discard)

═════════════════════════════════════════════════════════════
UI INDICATORS
═════════════════════════════════════════════════════════════

BADGES:
  📦 Original Product  (Blue badge)
  🆕 New Product      (Cyan badge)

BUTTON TEXT:
  Original + Sell → "✓ บันทึกสินค้าเดิมขายได้ (products table)"
  New + Sell      → "✓ บันทึกสินค้าใหม่ขายได้ (temp_products)"
  Any + Discard   → "✓ บันทึกสินค้าทิ้ง/ใช้ไม่ได้"

DIALOG SUMMARY:
  Lists all planned actions
  Shows target table
  Shows product type badge
  Shows data summary
  Shows warnings if any

SUCCESS MESSAGE:
  Confirms what was saved
  Shows new SKU
  Shows target table
  Shows all executed actions

═════════════════════════════════════════════════════════════
STATUS: ✅ COMPLETE & READY
═════════════════════════════════════════════════════════════
