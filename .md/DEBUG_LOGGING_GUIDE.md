# Expiry Date Debug Logging Guide

## Issue
Expiry dates entered for split quantity transactions are not being saved to the database.

## Solution: Comprehensive Debug Logging Added

The following debug logging has been added to `receive_edit.php` to trace the data flow from frontend to database:

### 1. **Initial POST Data Logging (Lines 22-26)**
**Location:** Right after variable initialization

**Logs:**
```
=== RECEIVE_EDIT START ===
receive_id: [value]
expiry_date from POST: [value]
remark: [value]
POST data: [entire $_POST array]
```

**Purpose:** Shows what data is being sent from the form, including the general expiry_date field from the non-split edit form.

---

### 2. **Split Data Reception Logging (Lines 62-67)**
**Location:** Before processing split_data

**Logs:**
```
Split data received (raw): [JSON string as received]
[If JSON parse error] JSON decode error: [error message]
Split info parsed: [decoded JSON as array]
Main expiry date: [mainExpiryDate value]
```

**Purpose:** Shows whether split_data was successfully received and decoded from JSON, and what the main expiry date is.

---

### 3. **Normal Update Logging (Line 83)**
**Location:** Non-split update path (regular edit without splitting)

**Logs:**
```
Normal update executed. Expiry_date: [value], Rows affected: [count]
```

**Purpose:** Confirms that regular edits (without splitting) are executing the UPDATE statement correctly.

---

### 4. **Split Main PO Update Logging (Lines 176-180)**
**Location:** Inside handleQuantitySplit() when updating main PO

**Logs:**
```
Split update - mainExpiryDate: [value]
Full splitInfo: [full JSON of split data]
Split UPDATE main PO - Result: [boolean], Rows affected: [count]
```

**Purpose:** Shows that the split function received the mainExpiryDate and is attempting to update the database.

---

### 5. **Additional PO Insert Logging (Lines 209-211)**
**Location:** Inside handleQuantitySplit() when inserting additional POs

**Logs:**
```
Additional PO insert - addExpiryDate: [value], addQty: [quantity]
Additional PO INSERT result: [boolean]
Created new receive item: ID=[id], PO_ID=[po_id], Qty=[qty], Expiry=[expiry_date]
```

**Purpose:** Shows each additional PO being created with its specific expiry date.

---

## How to Use This Logging

### Step 1: Enable PHP Error Logging
Make sure PHP error logging is enabled. Check your server's `php.ini` or `.htaccess`:
```ini
log_errors = On
error_log = /path/to/php_errors.log
```

### Step 2: Test the Feature
1. Go to the receive items view page
2. Edit a received item and set an expiry date, **then attempt to split quantities**
3. Fill in the split form with multiple PO entries, each with a different expiry date
4. Submit the form

### Step 3: Check the Logs
After submission, check your PHP error log (typically `/var/log/php_errors.log` or similar):

```bash
# Linux/Mac
tail -n 200 /var/log/php_errors.log | grep "=== RECEIVE_EDIT"

# Windows (if using file logging)
# Check the log file path from php.ini
```

### Step 4: Interpret the Logs

**Expected Log Sequence for Split:**
```
=== RECEIVE_EDIT START ===
receive_id: 123
expiry_date from POST: [value from form]
remark: [remarks]
POST data: [full POST array]
Split data received (raw): {"mainExpiryDate":"2024-12-31","mainQty":5,"mainPoId":10,...}
Split info parsed: Array...
Main expiry date: "2024-12-31"
Split update - mainExpiryDate: "2024-12-31"
Full splitInfo: {"mainExpiryDate"..."additionalPOs":[...]}
Split UPDATE main PO - Result: true, Rows affected: 1
Additional PO insert - addExpiryDate: "2025-01-15", addQty: 3
Additional PO INSERT result: true
Created new receive item: ID=456, PO_ID=11, Qty=3, Expiry=2025-01-15
```

---

## Troubleshooting Based on Logs

### Case 1: POST shows expiry_date is empty/null
**Problem:** Frontend is not capturing the expiry date value

**Check:**
- Is the date input field visible and has a value entered?
- Check browser console for JavaScript errors
- Verify the date format (should be YYYY-MM-DD)

**Solution:** Add client-side validation in the form before submission

---

### Case 2: split_data received (raw) shows empty or no mainExpiryDate
**Problem:** Frontend is not including expiry dates in the split_data JSON

**Check:**
- Are the date input fields in the split modal visible?
- Are values being entered into them?
- Check browser console: Log `window.currentSplitData` before form submit

**Solution:** Debug the JavaScript in `receive_items_view.php` around lines 1740-1780

---

### Case 3: Split UPDATE/INSERT shows Result: true but expiry_date field still empty in database
**Problem:** Database column is not accepting the value or query is wrong

**Check:**
- Verify `receive_items.expiry_date` column exists and is DATE type
- Check MySQL error log for constraint violations
- Run: `SELECT * FROM receive_items WHERE receive_id = 123;`

**Solution:** Check database schema or query syntax

---

### Case 4: Logs show everything correct but database still empty
**Problem:** Transaction might be rolling back

**Check:**
- Are there any transaction errors after the UPDATE/INSERT?
- Check if `pdo->rollback()` is being called

**Solution:** Look for exception messages in logs after the insert statements

---

## Database Verification Query

After testing, verify the data in the database:

```sql
-- Check if expiry_date was saved for the main PO
SELECT receive_id, po_id, receive_qty, expiry_date 
FROM receive_items 
WHERE receive_id = 123;

-- Check if additional POs were created with correct expiry dates
SELECT receive_id, po_id, receive_qty, expiry_date 
FROM receive_items 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
ORDER BY receive_id DESC;
```

---

## Files Modified

- `receive_edit.php` - Added comprehensive logging at 5 key points
- No changes to database schema
- No changes to frontend (yet - only logs data being sent)

## Next Steps

1. **Test:** Run a split transaction with expiry dates
2. **Collect:** Get the PHP error log output
3. **Share:** Provide the log output so we can identify exactly where the issue is
4. **Fix:** Apply targeted fix based on log findings
