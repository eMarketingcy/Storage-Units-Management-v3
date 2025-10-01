# Testing Guide - Payment System

## ✅ Errors Fixed

1. **Database Error** - `setting_value` can't have default value
2. **Deprecated Warnings** - Dynamic property creation

---

## Step 1: Fix Database (If Needed)

**If you still see the database error**, run this SQL in phpMyAdmin:

```sql
ALTER TABLE wpat_storage_settings MODIFY COLUMN setting_value longtext;
```

Or import `fix-database.sql`

---

## Step 2: Enable Debug Logging

Edit `wp-config.php` and add:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

This creates `wp-content/debug.log` file.

---

## Step 3: Test Payment

### Make a Test Payment:

1. Go to **Storage Units** admin
2. Find **Unit B12** (or any unit)
3. Note the current **"UNTIL"** date
4. Click **email icon** → **"View Invoice"** → **"PAY NOW"**
5. Select **6 months** from dropdown
6. Verify amount shown (e.g., €1,820.70)
7. Use test card: **4242 4242 4242 4242**
8. Complete payment

---

## Step 4: Check Logs IMMEDIATELY

**Open terminal/SSH and run:**

```bash
tail -50 /home/cleanthilifecom/selfstorage.cy/wp-content/debug.log | grep "SUM Payment"
```

### ✅ What You SHOULD See:

```
[01-Oct-2025 11:07:28] SUM Payment Processing: entity_id=12, is_customer=no, is_pallet=no, payment_months=6
[01-Oct-2025 11:07:28] SUM Payment: Advance payment detected - extending periods by 6 months
[01-Oct-2025 11:07:28] SUM Payment SUCCESS: Extended unit 12 from 2025-10-03 to 2026-04-03
[01-Oct-2025 11:07:28] SUM Payment History: Recording payment - customer: XSsadsd, items: 1, amount: EUR 1820.70
```

### ❌ If You See ERROR:

```
[01-Oct-2025 11:07:28] SUM Payment: Single month payment (payment_months=1), no period extension needed
```
**Problem:** Frontend is sending `payment_months=1` instead of `6`

```
[01-Oct-2025 11:07:28] SUM Payment ERROR: Unit 12 not found or has no period_until
```
**Problem:** Unit doesn't have a `period_until` date set

```
[01-Oct-2025 11:07:28] SUM Payment ERROR: Failed to extend unit 12 - Duplicate entry...
```
**Problem:** Database constraint issue

---

## Step 5: Verify in Admin

1. **Refresh** Storage Units admin page
2. **Check Unit B12:**
   - Status should be **"Paid"**
   - **"UNTIL"** date should be **extended by 6 months**
   - Example: Was `03 Oct 2025` → Now `03 Apr 2026`

---

## Step 6: Check Payment History

1. Go to **WordPress Admin** → **Storage Units** → **Payment History**
2. You should see the latest payment with:
   - ✅ Customer name (not "N/A")
   - ✅ Amount: EUR 1,820.70
   - ✅ Months: 6 months
   - ✅ Items: **Unit B12** (not "N/A"!)
   - ✅ Transaction ID
   - ✅ Date/Time
3. Click **"View Details"** button
4. Modal should show:
   - Customer name
   - Amount
   - Payment period: 6 months
   - **Items Paid** section with:
     - Type: Unit
     - Name: B12
     - **Paid Until: April 3, 2026** ← Important!

---

## Step 7: Check Database

**Run in phpMyAdmin:**

```sql
-- Check if unit was extended
SELECT id, unit_name, period_from, period_until, payment_status
FROM wpat_storage_units
WHERE unit_name = 'B12';
```

**Expected:**
```
id: 12
unit_name: B12
period_from: 2025-09-02
period_until: 2026-04-03  ← Extended!
payment_status: paid       ← Changed!
```

**Check payment history:**

```sql
SELECT *
FROM wpat_storage_payment_history
ORDER BY payment_date DESC
LIMIT 1;
```

**Expected:**
```
customer_name: XSsadsd (or contact name)
amount: 1820.70
payment_months: 6
items_paid: [{"type":"unit","name":"B12","period_until":"2026-04-03","monthly_price":"303.45"}]
```

---

## Troubleshooting

### Problem: No Logs at All

**Cause:** Debug logging not enabled or cache

**Fix:**
1. Verify `wp-config.php` has debug settings
2. Clear all caches (plugin cache, object cache, etc.)
3. Try payment again

---

### Problem: "payment_months=1" in Logs

**Cause:** Frontend dropdown not sending correct value

**Fix:** Check browser console for JavaScript errors

**To Debug:**
1. Open browser DevTools (F12)
2. Go to **Console** tab
3. Click "PAY NOW" and select months
4. Look for errors in console
5. Go to **Network** tab
6. Find the payment request
7. Check if `payment_months=6` is in the POST data

---

### Problem: "Unit not found or has no period_until"

**Cause:** Unit record incomplete

**Fix:**
```sql
-- Check the unit
SELECT * FROM wpat_storage_units WHERE id = 12;

-- If period_until is NULL, set it:
UPDATE wpat_storage_units
SET period_until = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
WHERE id = 12 AND period_until IS NULL;
```

---

### Problem: Database Update Failed

**Cause:** Table doesn't exist or wrong structure

**Fix:**
```sql
-- Check if table exists
SHOW TABLES LIKE 'wpat_storage_units';

-- Check table structure
DESCRIBE wpat_storage_units;

-- Should have these columns:
-- - id
-- - unit_name
-- - period_from (date)
-- - period_until (date)
-- - payment_status (varchar)
```

---

## Share These If Still Not Working:

1. **Error logs** (last 100 lines):
   ```bash
   tail -100 /path/to/wp-content/debug.log | grep "SUM Payment" > payment-logs.txt
   ```

2. **Database structure**:
   ```sql
   DESCRIBE wpat_storage_units;
   DESCRIBE wpat_storage_payment_history;
   ```

3. **Sample unit data**:
   ```sql
   SELECT * FROM wpat_storage_units WHERE id = 12;
   ```

4. **Browser console errors** (screenshot)

5. **Network request** (screenshot showing POST data)

---

## Expected Complete Flow:

```
1. Payment initiated
   POST: unit_id=12, payment_months=6, amount=182070

2. Backend processing:
   ✓ Verify payment token
   ✓ Call Stripe API
   ✓ Payment successful: ch_3SDL4IBJCxRc2cUi1Cbq2QIa
   ✓ Update payment_status = 'paid'
   ✓ Detect payment_months=6 (advance payment)
   ✓ Calculate: 2025-10-03 + 6 months = 2026-04-03
   ✓ UPDATE period_until to 2026-04-03
   ✓ Record in payment_history with items_paid JSON
   ✓ Generate receipt PDF with fresh data
   ✓ Send email with receipt

3. Result:
   ✓ Unit extended to 2026-04-03
   ✓ Status = "paid"
   ✓ Payment history shows all details
   ✓ Receipt shows correct dates
   ✓ Customer receives email

4. Logs show each step with SUCCESS/ERROR
```

---

**The comprehensive logging will show us EXACTLY where the problem is!** 🔍

Try the test payment and share the logs if it still doesn't work.
