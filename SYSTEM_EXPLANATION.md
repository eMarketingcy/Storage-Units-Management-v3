# Two-Step Payment History System

## ✅ NEW SYSTEM IMPLEMENTED

### How It Works:

**STEP 1: Invoice Sent** → Create pending payment record
**STEP 2: Payment Received** → Complete the record

---

## 📋 STEP 1: When Invoice is Sent

**File:** `includes/class-email-handler.php`

When you send an invoice from admin:
1. **Generate payment token** (unique ID)
2. **Create pending payment history record** with:
   - Customer name
   - Payment token
   - Currency (EUR)
   - Months due
   - **Items paid** (unit/pallet names)
   - **Expected until dates** (after payment)
   - Status: **"pending"**

3. **Send invoice email** with payment link

---

## 💳 STEP 2: When Payment is Received

**File:** `includes/class-payment-handler.php`

When customer pays:
1. **Find pending record** by payment token
2. **Update the record** with:
   - Transaction ID (from Stripe)
   - Amount paid
   - Payment date
   - Status: **"completed"**

3. **Update unit/pallet** period_until dates
4. **Send receipt email**

---

## 🎯 Benefits:

### ✅ Items Always Show in Payment History
- Items are recorded WHEN INVOICE SENT
- NOT when payment is received
- **No more N/A in "Items Paid" column!**

### ✅ Until Dates Pre-calculated
- Expected "paid until" dates stored with invoice
- Visible BEFORE payment
- Easy to see what customer will get

### ✅ Track Pending Invoices
- See which invoices were sent but not paid
- Status column shows: "pending" or "completed"

### ✅ Reliable Data
- Payment history exists even if payment fails
- Can resend invoice, same token, same record
- One invoice → One history record

---

## 📊 Payment History Table Structure:

```sql
CREATE TABLE wpat_storage_payment_history (
    id bigint(20) AUTO_INCREMENT PRIMARY KEY,
    customer_id bigint(20) NOT NULL,
    customer_name varchar(255) NOT NULL,
    payment_token varchar(255) DEFAULT NULL,      -- ← NEW: Links invoice to payment
    transaction_id varchar(255) DEFAULT NULL,      -- ← NULL until paid
    amount decimal(10,2) DEFAULT NULL,             -- ← NULL until paid
    currency varchar(10) DEFAULT 'EUR',
    payment_months int(11) DEFAULT 1,
    items_paid text NOT NULL,                      -- ← Set when invoice sent!
    payment_date datetime DEFAULT NULL,            -- ← NULL until paid
    status varchar(20) DEFAULT 'pending',          -- ← NEW: pending/completed
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    KEY payment_token (payment_token),
    KEY status (status)
);
```

---

## 🧪 Test the System:

### Test 1: Send Invoice

1. **Go to Storage Units** admin
2. **Find Unit B12**
3. **Click email icon** → **Send Invoice**
4. **Check Payment History**
   - Should show **pending** record
   - **Items:** Unit B12 ✓
   - **Until Date:** Expected date after payment ✓
   - **Status:** pending
   - **Amount:** NULL (not paid yet)

---

### Test 2: Pay Invoice

1. **Customer clicks** payment link in email
2. **Pays with:** 4242 4242 4242 4242
3. **Check Payment History**
   - **Same record** now shows:
   - **Status:** completed ✓
   - **Amount:** EUR 1,820.70 ✓
   - **Transaction ID:** ch_xxx ✓
   - **Payment Date:** Today ✓
   - **Items:** Still Unit B12 ✓

---

### Test 3: Unit Extended

1. **Check Unit B12** in admin
2. **"UNTIL" column** should show new date (6 months later)
3. **"STATUS" column** should show "Paid"

---

## 🔍 Database Queries for Testing:

### Check Pending Invoices:
```sql
SELECT id, customer_name, payment_months, items_paid, status, created_at
FROM wpat_storage_payment_history
WHERE status = 'pending'
ORDER BY created_at DESC;
```

### Check Completed Payments:
```sql
SELECT id, customer_name, amount, payment_months, items_paid, status, payment_date
FROM wpat_storage_payment_history
WHERE status = 'completed'
ORDER BY payment_date DESC
LIMIT 10;
```

### Check Specific Payment by Token:
```sql
SELECT *
FROM wpat_storage_payment_history
WHERE payment_token = 'YOUR_TOKEN_HERE';
```

---

## 📝 Code Changes Made:

### 1. Payment History Table ✓
- Added `payment_token` column
- Added `status` column  
- Made `transaction_id`, `amount`, `payment_date` nullable
- File: `includes/class-payment-history.php`

### 2. New Methods ✓
- `create_pending_payment()` - Create record when invoice sent
- `complete_payment()` - Update record when payment received
- File: `includes/class-payment-history.php`

### 3. Email Handler ✓
- Create pending payment BEFORE sending invoice
- Store items and expected dates
- File: `includes/class-email-handler.php`

### 4. Payment Handler ✓
- Changed from creating NEW record to UPDATING existing
- Uses `complete_payment()` instead of `record_payment()`
- File: `includes/class-payment-handler.php`

---

## 🎯 Result:

**Payment History NOW shows:**
- ✅ Customer name (not N/A)
- ✅ Items paid (Unit B12, not N/A)
- ✅ Expected until dates
- ✅ Status (pending/completed)
- ✅ Amount (after payment)
- ✅ All data preserved and visible

**The system is ready to test!** 🚀

---

## 📧 Next Steps:

1. **Send an invoice** to Unit B12
2. **Check Payment History** - should show pending record with items
3. **Pay the invoice** 
4. **Check Payment History** - same record now completed
5. **Verify unit dates extended**

**Share results after testing!** 📊
