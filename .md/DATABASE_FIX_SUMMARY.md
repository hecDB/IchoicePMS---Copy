# Database Error Fix - Missing Columns

## Problem
Database error: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'poi.is_cancelled' in 'field list'`

This error occurred because the code was referencing columns that didn't exist in the `purchase_order_items` table.

## Root Cause
The PO cancellation feature required new columns in the `purchase_order_items` table, but they hadn't been added to the database schema.

## Solution
Created and executed a database migration script (`add_cancelled_columns.php`) that adds all required columns to the `purchase_order_items` table.

## Columns Added
The following columns were successfully added to the `purchase_order_items` table:

1. **is_cancelled** (TINYINT(1)) - Marks if an item is fully cancelled
2. **is_partially_cancelled** (TINYINT(1)) - Marks if an item is partially cancelled
3. **cancel_qty** (INT) - Quantity of items that were cancelled
4. **cancelled_by** (INT) - User ID of who cancelled the item
5. **cancelled_at** (DATETIME) - Timestamp when the item was cancelled
6. **cancel_reason** (VARCHAR(100)) - Reason for cancellation
7. **cancel_notes** (TEXT) - Additional notes about the cancellation

## Verification
All columns are now present and properly defined in the database:

```
- is_cancelled (tinyint(1))
- is_partially_cancelled (tinyint(1))
- cancel_qty (int(11))
- cancelled_by (int(11))
- cancelled_at (datetime)
- cancel_reason (varchar(100))
- cancel_notes (text)
```

## Files Modified
- `add_cancelled_columns.php` - Database migration script (created)
- All existing code files now have the necessary columns available:
  - `/api/get_po_items.php`
  - `/receive/process_receive_po.php`
  - `/receive/cancelled_items.php`
  - `/receive/get_completed_pos.php`
  - And other related files

## How to Apply This Fix

The fix has already been applied! The script `add_cancelled_columns.php` was executed successfully and all columns have been added to the database.

If you need to re-run the migration (e.g., on another environment), execute:
```bash
php add_cancelled_columns.php
```

## Status
✅ **FIXED** - All required columns are now in the database. The application should work without the "Unknown column" error.
