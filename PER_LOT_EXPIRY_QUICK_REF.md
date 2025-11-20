# ⚡ Per-Lot Expiry Date - Quick Reference

## What Changed?

| Before | After |
|--------|-------|
| Edit item → Expiry shows old date | Edit item → Expiry field EMPTY |
| User might forget to change | User MUST enter new date |
| Risk of wrong dates | Each lot gets own date |

## How to Use

### Receiving New Lot
```
1. Click Edit on receive item
2. Field shows empty expiry date ← This is NORMAL
3. Type new expiry for this lot
4. Save
```

### Example
```
Lot 1 (Nov 20): Expiry 2025-11-30
                ↓
Edit Lot 1 → Empty field → Enter 2025-11-30 → Save ✓

Lot 2 (Nov 21): Different lot
                ↓
Edit Lot 2 → Empty field → Enter 2025-12-31 → Save ✓
```

## Code Changes

**File:** `receive_items_view.php`

- Line 1100: `$('#edit-expiry-date').val('')` ← Always empty
- Lines 1145-1151: API auto-fill disabled (commented out)

## Database Result

```sql
-- Each receive_id (lot) has own expiry_date
SELECT receive_id, product_id, expiry_date FROM receive_items;
42    5    2025-11-30
43    5    2025-12-31  ← Different lot, different date ✓
```

## Testing

✅ Click Edit → Expiry empty  
✅ Enter new date → Save  
✅ Check DB → New date saved  

## FAQ

**Q: Why is it empty?**  
A: By design - to force entry for each new lot

**Q: Can I see old dates?**  
A: Yes, in database history

**Q: How to set new lot date?**  
A: Type it in when editing

---

**Status:** ✅ Active  
**Impact:** Per-lot tracking enabled
