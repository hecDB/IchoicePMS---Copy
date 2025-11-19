# Phase 33: Receive Items Enhancement - Expiry Date Feature

## 📋 Overview
Enhanced the receive items popup to display and capture expiry dates with Thai date formatting and validation.

## ✅ Changes Completed

### 1. **receive_po_items.php** (Main UI Page)

#### A. Table Header Update
- Added "วันหมดอายุ" (Expiry Date) column to the receive items table
- Updated table structure from 9 columns → 10 columns
- Adjusted column widths proportionally

#### B. displayPoItems() Function Enhancement
- Modified to render expiry_date column in each row
- Added color-coded badges:
  - 🔴 **Red (bg-danger)**: Expired dates (date < today)
  - 🔵 **Blue (bg-info)**: Valid dates (date >= today)
  - ⚪ **Neutral**: "-" when no expiry date
- Uses `formatThaiDate()` to display dates in Thai format (DD MMM YYYY)

#### C. Quick Receive Modal Enhancement
- Added expiry date input field: `<input type="date" id="quickExpiryDate">`
- Field is **optional** (not required)
- Placed after notes textarea in form
- Label: "วันหมดอายุ (ถ้ามี)" (Expiry Date if applicable)

#### D. saveSingleReceive() Function Update
- Captures expiry_date before sending: `const expiryDate = $('#quickExpiryDate').val();`
- Sends expiry_date in POST data to `process_receive_po.php`
- Format: "YYYY-MM-DD"

#### E. Helper Functions (NEW)
```javascript
// 1. formatThaiDate(dateString)
// Converts YYYY-MM-DD to Thai format: "19 พ.ค. 2567"
// Returns "-" if no date provided

// 2. isExpired(dateString)
// Returns true if date < today (for color coding)
// Returns false otherwise
```

### 2. **process_receive_po.php** (Backend API)

**Already supports expiry_date:**
- ✅ Accepts `expiry_date` from POST data
- ✅ Validates date format (Y-m-d)
- ✅ Validates expiry date is not in the past
- ✅ Saves expiry_date to `receive_items` table
- ✅ Works with both quick receive and batch receive modes

### 3. **get_po_items.php** (API - Regular PO)

**Enhanced to return expiry_date:**
- Added `MAX(ri.expiry_date) as expiry_date` to SQL query
- Expiry_date field now included in JSON response
- Uses latest expiry date if multiple receives exist for same item

### 4. **get_po_items_new_product.php** (API - New Product PO)

**Enhanced to return expiry_date:**
- Added `MAX(ri.expiry_date) as expiry_date` to SQL subquery
- Expiry_date field now included in JSON response
- Supports new product purchases with expiry date tracking

## 🗄️ Database

**receive_items Table Structure:**
- Column: `expiry_date`
- Type: `DATE`
- Nullable: `YES` (optional)
- Constraints: None (allows past dates to be stored for historical records)

## 🔄 Data Flow

### Receiving with Expiry Date:

```
User Opens Receive Page
    ↓
displayPoItems() fetches items via API (get_po_items.php)
    ↓
API returns items WITH expiry_date
    ↓
User clicks "รับ" (Receive) button
    ↓
Quick Receive Modal opens with date input
    ↓
User enters expiry date (optional)
    ↓
saveSingleReceive() captures date and sends to API
    ↓
process_receive_po.php:
    - Validates date format (Y-m-d)
    - Checks date is not in past (optional validation)
    - Inserts receive record WITH expiry_date
    ↓
receive_items table updated with expiry_date
    ↓
Page refreshes, displayPoItems() shows date with color badge
```

## 📱 UI Features

### Color Coding
- **Red Badge**: Expired products (visual alert)
- **Blue Badge**: Valid/upcoming expiry dates
- **Neutral**: No expiry date set

### Date Format
- **Display**: Thai format (e.g., "19 พ.ค. 2567")
- **Input**: HTML5 date picker (YYYY-MM-DD)
- **Validation**: Server-side format and past-date checks

## 🧪 Testing Checklist

- [ ] Test receiving item WITH expiry date
- [ ] Verify date displays in Thai format
- [ ] Verify RED badge shows for past dates
- [ ] Verify BLUE badge shows for future dates
- [ ] Test receiving item WITHOUT expiry date (shows "-")
- [ ] Verify data persists in database
- [ ] Test with both Regular PO and New Product PO
- [ ] Test batch receive with expiry dates
- [ ] Test on different browsers (date picker support)

## 📊 API Response Examples

### get_po_items.php Response:
```json
{
  "success": true,
  "items": [
    {
      "item_id": "1",
      "product_name": "Product A",
      "order_qty": "100",
      "received_qty": "50",
      "remaining_qty": "50",
      "expiry_date": "2024-11-30"
    }
  ]
}
```

### Quick Receive POST:
```json
{
  "action": "receive_single",
  "po_id": "1",
  "item_id": "1",
  "quantity": "25",
  "notes": "Good condition",
  "expiry_date": "2024-11-30",
  "po_number": "PO-001"
}
```

## 🔒 Validation Rules

1. **Date Format**: Must be "YYYY-MM-DD"
2. **Past Dates**: Rejected by server (optional, can be overridden)
3. **Field Type**: HTML5 date input ensures user selects valid dates
4. **NULL Handling**: Empty field = NULL in database (optional field)

## 🚀 Production Status

**Ready for Testing**: ✅
- All code changes implemented
- Backend support already in place
- Helper functions added
- APIs updated

**Next Steps**:
1. Run end-to-end test with sample PO
2. Verify database saves correctly
3. Test date display in various browser
4. Deploy to production

## 📝 Notes

- **Backward Compatibility**: Maintained - existing receives without expiry_date still work
- **Data Migration**: No migration needed - column already exists in DB
- **Performance**: Minimal impact - single date field per item
- **Locale**: Thai date formatting implemented using Buddhist calendar (543 year offset)

---

**Implementation Date**: Phase 33
**Status**: ✅ Complete - Ready for Testing
