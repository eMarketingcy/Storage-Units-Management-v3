# Two-Step Payment System - Implementation Steps

## ✅ ALL CODE CHANGES COMPLETE

### Files Modified:
1. ✅ `includes/class-payment-history.php` - Added two-step methods
2. ✅ `includes/class-payment-handler.php` - Changed to update existing records
3. ✅ `includes/class-email-handler.php` - Creates pending records for units
4. ✅ `includes/class-customer-email-handler.php` - Creates pending records for customers
5. ✅ `includes/class-pallet-email-handler.php` - Creates pending records for pallets

---

## 🗄️ DATABASE UPDATE REQUIRED

### Option 1: Run in phpMyAdmin (RECOMMENDED)

**File:** `ALTER_PAYMENT_HISTORY_TABLE.sql`

1. Open **phpMyAdmin**
2. Select your **WordPress database**
3. Click **SQL** tab
4. Copy contents from `ALTER_PAYMENT_HISTORY_TABLE.sql`
5. Click **Go**

---

### Option 2: Run via WP-CLI

```bash
wp db query < ALTER_PAYMENT_HISTORY_TABLE.sql
```

---

### Option 3: Copy-Paste Individual Queries

Run these ONE AT A TIME in phpMyAdmin:

```sql
-- Add payment_token column
ALTER TABLE `wpat_storage_payment_history` 
ADD COLUMN `payment_token` VARCHAR(255) DEFAULT NULL 
AFTER `customer_name`;

-- Add status column
ALTER TABLE `wpat_storage_payment_history` 
ADD COLUMN `status` VARCHAR(20) DEFAULT 'pending' 
AFTER `payment_date`;

-- Make transaction_id nullable
ALTER TABLE `wpat_storage_payment_history` 
MODIFY COLUMN `transaction_id` VARCHAR(255) DEFAULT NULL;

-- Make amount nullable
ALTER TABLE `wpat_storage_payment_history` 
MODIFY COLUMN `amount` DECIMAL(10,2) DEFAULT NULL;

-- Make payment_date nullable
ALTER TABLE `wpat_storage_payment_history` 
MODIFY COLUMN `payment_date` DATETIME DEFAULT NULL;

-- Add indexes
ALTER TABLE `wpat_storage_payment_history` 
ADD INDEX `idx_payment_token` (`payment_token`);

ALTER TABLE `wpat_storage_payment_history` 
ADD INDEX `idx_status` (`status`);
```

---

## ✅ Verify Database Changes

```sql
SHOW COLUMNS FROM `wpat_storage_payment_history`;
```

**Expected columns:**
- id
- customer_id
- customer_name
- **payment_token** ← NEW
- transaction_id (nullable)
- amount (nullable)
- currency
- payment_months
- items_paid
- payment_date (nullable)
- **status** ← NEW
- created_at

---

## 🧪 TEST THE SYSTEM

### Test 1: Send Invoice (Creates Pending Record)

1. **Go to Storage Units** admin
2. **Find Unit B12**
3. **Click email icon** → Send Invoice
4. **Check Payment History page**
   - Should show **NEW pending record**
   - ✅ Customer: XSsadsd
   - ✅ Items: Unit B12
   - ✅ Status: pending
   - ⏳ Amount: (empty - not paid yet)
   - ⏳ Transaction ID: (empty)

---

### Test 2: Pay Invoice (Completes Existing Record)

1. **Open email** → Click payment link
2. **Pay** with: 4242 4242 4242 4242
3. **Check Payment History page**
   - **SAME record** now shows:
   - ✅ Customer: XSsadsd (still there)
   - ✅ Items: Unit B12 (still there!)
   - ✅ Status: **completed**
   - ✅ Amount: EUR 1,820.70 (filled!)
   - ✅ Transaction ID: ch_xxx (filled!)
   - ✅ Payment Date: Today (filled!)

---

### Test 3: Verify Unit Extended

1. **Go to Storage Units** admin
2. **Find Unit B12**
3. **Check "UNTIL" column** → Should show new date (+6 months)
4. **Check "STATUS" column** → Should show "Paid"

---

## 🔍 Database Verification Queries

### Check Pending Invoices:
```sql
SELECT id, customer_name, payment_token, items_paid, status, created_at
FROM wpat_storage_payment_history
WHERE status = 'pending'
ORDER BY created_at DESC;
```

### Check Completed Payments:
```sql
SELECT id, customer_name, amount, transaction_id, items_paid, status, payment_date
FROM wpat_storage_payment_history
WHERE status = 'completed'
ORDER BY payment_date DESC
LIMIT 10;
```

### View All Recent Activity:
```sql
SELECT 
    id, 
    customer_name, 
    payment_token,
    transaction_id,
    amount,
    status, 
    created_at,
    payment_date,
    JSON_EXTRACT(items_paid, '$[0].name') as first_item
FROM wpat_storage_payment_history
ORDER BY created_at DESC
LIMIT 20;
```

---

## 📊 How It Works

### OLD SYSTEM (Before):
1. Send invoice → No record created
2. Payment received → Create payment history record
3. **Problem:** Items showed as N/A because payment data was incomplete

### NEW SYSTEM (Now):
1. **Send invoice** → Create **pending** payment history record
   - ✅ Customer name saved
   - ✅ Items saved (Unit B12)
   - ✅ Expected until dates calculated and saved
   - ⏳ Amount: NULL (not paid yet)
   
2. **Payment received** → **Update** existing record
   - ✅ Fill in transaction_id
   - ✅ Fill in amount
   - ✅ Fill in payment_date
   - ✅ Change status to "completed"
   - ✅ Items ALREADY THERE (from step 1!)

**Result:** Payment history shows complete data including items!

---

## 🎯 Benefits

1. ✅ **Items always visible** - Recorded when invoice sent
2. ✅ **No more N/A values** - All data preserved from invoice
3. ✅ **Track pending invoices** - See what's unpaid
4. ✅ **Reliable records** - One invoice = one history record
5. ✅ **Expected dates stored** - Know what customer will get

---

## 📧 What Happens Now

### When Admin Sends Invoice:
1. System generates unique payment token
2. **Creates pending payment record** with all items
3. Sends email with payment link
4. Record visible in Payment History (status: pending)

### When Customer Pays:
1. System finds pending record by token
2. **Updates same record** with payment details
3. Extends unit/pallet dates
4. Changes status to completed
5. Sends receipt

---

## 🚀 Ready to Use!

1. ✅ Run ALTER TABLE queries in phpMyAdmin
2. ✅ Verify columns added
3. ✅ Send test invoice to Unit B12
4. ✅ Check Payment History shows pending record with items
5. ✅ Pay the invoice
6. ✅ Check Payment History shows completed record (same row!)

---

**All code is ready - just need to update the database!** 💪
