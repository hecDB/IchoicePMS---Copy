# Item Cancellation Feature - Quick Reference

## What's New

A red **Cancel** button has been added to the item receive interface that allows you to cancel items that:
- Are no longer in stock
- Are damaged or defective
- Were cancelled by the supplier
- Cannot be delivered for any reason

## How to Use

### Step 1: Open PO in Receive Mode
- Click "รับเข้า" (Receive) button on any PO card
- Modal opens showing all items

### Step 2: Find Item to Cancel
- Locate the item in the table that needs to be cancelled
- Each row has a quantity input field and buttons on the right

### Step 3: Click Cancel Button
- Click the red **Cancel** button (X icon)
- Next to the "Quick Receive" button (speed icon)

### Step 4: Select Reason
The Cancel Item modal opens with:
- Product name (automatically filled)
- Quantity to cancel (automatically filled)
- **Reason dropdown** (required):
  - สินค้าไม่มีสต็อก (Stock unavailable)
  - สินค้าหมด (Out of stock)
  - สินค้าเสียหาย (Damaged)
  - ผู้จำหน่ายยกเลิก (Supplier cancelled)
  - อื่นๆ (Other)

### Step 5: Add Notes (Optional)
- Add any additional details about the cancellation
- Example: "Supplier informed on 2025-12-05"

### Step 6: Confirm
- Click "ยืนยันการยกเลิก" (Confirm Cancel) button
- Confirmation dialog appears
- Click again to confirm

### Step 7: Done
- Success message appears
- Item is marked as cancelled
- PO status updates automatically
- Modal refreshes to show changes

## What Happens When You Cancel

✅ Item is marked as "Cancelled" in the system
✅ Cancellation reason is recorded
✅ Your username and timestamp are logged
✅ PO automatically marks cancelled quantity as "received" (100% complete)
✅ PO statistics update to reflect cancellation
✅ Future reports will show cancelled items separately

## Important Notes

⚠️ **Cancellation is final** - Make sure you select the correct item
⚠️ **Reason is required** - You must select a cancellation reason
⚠️ **Entire quantity is cancelled** - The system cancels all remaining items (not partial cancel from this interface)
⚠️ **History is kept** - Cancellation records cannot be deleted, only modified through admin

## Button Locations

| Button | Purpose | Color |
|--------|---------|-------|
| รับเข้า (Receive) | Input quantity | Blue |
| ⚡ (Speed) | Quick receive | Primary |
| ✕ (Cancel) | Cancel item | Red |

## Keyboard Shortcuts

- Tab key: Navigate through form fields
- Enter: Submit form (if focused on Confirm button)
- Escape: Close modal without saving

## Troubleshooting

**Q: Cancel button is not showing**
A: The button only appears when:
- Modal is in "Receive" mode (not "View" mode)
- Item still has remaining quantity

**Q: "Reason is required" error**
A: Select a reason from the dropdown before clicking confirm

**Q: Cancellation not saved**
A: Check your internet connection and try again. Error message will explain the issue.

**Q: Need to undo cancellation?**
A: Contact system administrator. Cancellations are logged but require admin to modify/reverse.

## Example Scenarios

### Scenario 1: Item Out of Stock
1. Open PO
2. Supplier confirms item is out of stock
3. Click cancel button
4. Select: "สินค้าหมด" (Out of stock)
5. Add note: "Confirmed by supplier email"
6. Confirm

### Scenario 2: Damaged Item
1. Open PO
2. Inspection finds damaged goods
3. Click cancel button
4. Select: "สินค้าเสียหาย" (Damaged)
5. Add note: "Damage during shipping"
6. Confirm

### Scenario 3: Supplier Cancellation
1. Open PO
2. Receive cancellation from supplier
3. Click cancel button
4. Select: "ผู้จำหน่ายยกเลิก" (Supplier cancelled)
5. Add note: Supplier PO reference or date
6. Confirm

---

**Need Help?** Contact your supervisor or system administrator.
