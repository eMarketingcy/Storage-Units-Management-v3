# Complete System Explanation: Automated Billing & Payments

## Table of Contents
1. [Automated Billing System](#automated-billing-system)
2. [Payment WITHOUT Advance (1 Month)](#payment-without-advance)
3. [Payment WITH Advance (Multiple Months)](#payment-with-advance)
4. [After Payment Success](#after-payment-success)
5. [Timeline Examples](#timeline-examples)

---

# Automated Billing System

## How It Works

The system runs **automatically every day** using WordPress cron jobs.

### Daily Process (Runs at midnight)

```
┌─────────────────────────────────────────────────────────────┐
│  DAILY AUTOMATED BILLING CHECK (Midnight)                   │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Step 1: Generate Invoices                                  │
│  Step 2: Send First Reminders                               │
│  Step 3: Send Final Reminders                               │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Settings (Configurable in Admin)

| Setting | Default | Description |
|---------|---------|-------------|
| **Auto Billing Enabled** | Yes | Turn automated billing on/off |
| **Invoice Generation Days** | 0 days | How many days BEFORE period_until to send invoice |
| **First Reminder Days** | 7 days | Days AFTER invoice to send first reminder |
| **Final Reminder Days** | 2 days | Days BEFORE period_until to send final reminder |
| **Customers Only** | Yes | Only send to customer accounts (not individual units) |

---

## Step 1: Invoice Generation

**When:** X days before `period_until` date

**Example:** If setting is "0 days" and unit expires on **Oct 31, 2025**:
- Invoice sent on: **Oct 31, 2025** (same day)

**Example:** If setting is "7 days" and unit expires on **Oct 31, 2025**:
- Invoice sent on: **Oct 24, 2025** (7 days before)

### What Happens:

```sql
-- System checks for units/pallets expiring on target date
SELECT customers
FROM storage_customers c
JOIN storage_units u ON u.customer_name = c.full_name
WHERE u.period_until = '2025-10-31'
AND u.payment_status != 'paid'
```

### Email Sent:

**Subject:** "Storage Invoice - Payment Due Oct 31, 2025"

**Content:**
```
Dear Customer,

Your storage rental invoice is ready.

Unit 5: €150.00/month
Pallet 2: €153.45/month
─────────────────────
Total Due: €303.45

Due Date: October 31, 2025

[PAY NOW] ← Payment link
```

**Attachment:** Invoice PDF with full details

---

## Step 2: First Reminder (7 days after invoice)

**When:** 7 days after invoice was sent (if still unpaid)

**Example Timeline:**
- Invoice sent: Oct 24, 2025
- First reminder: Oct 31, 2025 (invoice date + 7 days)

### What Happens:

```sql
-- Check if payment status is still unpaid/overdue
SELECT customers
WHERE payment_status IN ('unpaid', 'overdue')
AND reminder was not sent in last 30 days
```

### Email Sent:

**Subject:** "Payment Reminder - Your Storage Invoice is Due Soon"

**Content:**
```
📧 Payment Reminder

Dear Customer,

This is a friendly reminder that your storage invoice is due.

Amount Due: €303.45
Due Date: October 31, 2025

Please make payment to avoid service interruption.

[PAY NOW]
```

---

## Step 3: Final Reminder (2 days before due date)

**When:** 2 days before `period_until` expires (if still unpaid)

**Example Timeline:**
- Due date: Oct 31, 2025
- Final reminder: Oct 29, 2025 (2 days before)

### Email Sent:

**Subject:** "⚠️ URGENT: Payment Due in 2 Days - Storage Invoice"

**Content:**
```
⚠️ URGENT PAYMENT REMINDER

Dear Customer,

⚠️ Your payment is due in 2 days!

Amount Due: €303.45
Due Date: October 31, 2025

Please settle your invoice immediately to avoid service interruption.

[PAY NOW IMMEDIATELY]
```

---

## Payment Status States

| Status | Meaning |
|--------|---------|
| **paid** | Customer paid, rental period extended |
| **unpaid** | Invoice sent, payment not received |
| **overdue** | Past due date, payment not received |
| **pending** | Payment processing (temporary state) |

---

# Payment WITHOUT Advance (1 Month)

## Scenario: Customer Pays for 1 Month Only

### Before Payment

```
Customer: John Doe
Unit 5: Paid until Oct 31, 2025 (payment_status: unpaid)
Monthly price: €150.00

Payment page shows:
Payment period: [1 month ▼]
Amount to pay: €150.00
```

### Customer Clicks "Pay with Card"

```
1. Frontend calculates: 1 month × €150.00 = €150.00
2. Stripe processes payment
3. Transaction ID: ch_3SDK1XBJCxRc2cUi0123456
4. Backend receives payment confirmation
```

### Backend Processing

```php
// Step 1: Verify Stripe payment
✓ Payment confirmed: €150.00

// Step 2: Update payment status
UPDATE storage_units
SET payment_status = 'pending'
WHERE id = 5;

// Step 3: Period extension (1 month)
$current_until = '2025-10-31';
$payment_months = 1;
$new_until = '2025-10-31' + 1 month = '2025-11-30';

UPDATE storage_units
SET period_until = '2025-11-30',
    payment_status = 'paid'
WHERE id = 5;

// Step 4: Record in payment history
INSERT INTO payment_history (
    customer_id = 123,
    transaction_id = 'ch_3SDK...',
    amount = 150.00,
    payment_months = 1,
    items_paid = '[{"type":"unit","name":"5","period_until":"2025-11-30"}]'
);

// Step 5: Generate receipt
Filename: receipt-unit5-2025-10-01-10-00-28.pdf
Content:
  Total Paid: €150.00
  Unit 5: November 30, 2025 (NEW)

// Step 6: Send email
To: customer@email.com
Subject: Payment Confirmation
Attachment: receipt-unit5-2025-10-01-10-00-28.pdf
```

### Result

```
✓ Payment successful
✓ Unit 5 extended from Oct 31 → Nov 30, 2025
✓ Payment status: paid
✓ Receipt sent to customer
✓ Payment recorded in history
```

---

# Payment WITH Advance (Multiple Months)

## Scenario: Customer Pays 6 Months in Advance

### Before Payment

```
Customer: Jane Smith
Unit 5: Paid until Oct 31, 2025 (unpaid)
Pallet 2: Paid until Oct 30, 2025 (unpaid)
Monthly total: €303.45

Payment page shows:
Payment period: [6 months ▼]
Amount to pay: €303.45 × 6 = €1,820.70
Preview: "Items will be paid until April 30, 2026"
```

### Customer Clicks "Pay with Card"

```
1. Frontend calculates: 6 months × €303.45 = €1,820.70
2. Stripe processes payment
3. Transaction ID: ch_3SDK1XBJCxRc2cUi0TcwjwqC
4. Backend receives payment confirmation
```

### Backend Processing

```php
// Step 1: Verify Stripe payment
✓ Payment confirmed: €1,820.70

// Step 2: Update payment status
UPDATE storage_units SET payment_status = 'pending' WHERE id = 5;
UPDATE storage_pallets SET payment_status = 'pending' WHERE id = 2;

// Step 3: Period extension (6 months) - EACH ITEM INDIVIDUALLY
// Unit 5:
$current_until = '2025-10-31';
$payment_months = 6;
$new_until = '2025-10-31' + 6 months = '2026-04-30';

UPDATE storage_units
SET period_until = '2026-04-30',
    payment_status = 'paid'
WHERE id = 5;

// Pallet 2:
$current_until = '2025-10-30';
$payment_months = 6;
$new_until = '2025-10-30' + 6 months = '2026-04-30';

UPDATE storage_pallets
SET period_until = '2026-04-30',
    payment_status = 'paid'
WHERE id = 2;

// Step 4: Record in payment history
INSERT INTO payment_history (
    customer_id = 123,
    transaction_id = 'ch_3SDK...',
    amount = 1820.70,
    currency = 'EUR',
    payment_months = 6,
    items_paid = '[
        {"type":"unit","name":"5","period_until":"2026-04-30","monthly_price":"150.00"},
        {"type":"pallet","name":"2","period_until":"2026-04-30","monthly_price":"153.45"}
    ]',
    payment_date = '2025-10-01 10:00:00'
);

// Step 5: Generate receipt with NEW dates
Filename: receipt-unit5-pallet2-2025-10-01-10-00-28.pdf
Content:
  Total Paid: €1,820.70

  📅 Advance Payment Confirmation
  Payment Period: 6 month(s)
  Items Paid Until:
  • Unit 5: April 30, 2026
  • Pallet 2: April 30, 2026
  ✓ Your rental period has been extended automatically.

// Step 6: Send email
To: customer@email.com
Subject: Payment Confirmation - Self Storage Cyprus
Attachment: receipt-unit5-pallet2-2025-10-01-10-00-28.pdf
```

### Result

```
✓ Payment successful: €1,820.70
✓ Unit 5 extended: Oct 31, 2025 → Apr 30, 2026 (6 months)
✓ Pallet 2 extended: Oct 30, 2025 → Apr 30, 2026 (6 months)
✓ Both payment status: paid
✓ Receipt with advance payment info sent
✓ Payment history recorded with all details
```

---

# After Payment Success

## What Happens Immediately

### 1. Database Updates

```sql
-- Units Table
UPDATE wp_storage_units
SET
    period_until = '2026-04-30',     -- Extended
    payment_status = 'paid',          -- Marked paid
    last_payment_date = NOW()         -- Timestamp
WHERE id = 5;

-- Pallets Table
UPDATE wp_storage_pallets
SET
    period_until = '2026-04-30',      -- Extended
    payment_status = 'paid',           -- Marked paid
    last_payment_date = NOW()          -- Timestamp
WHERE id = 2;

-- Payment History Table (NEW)
INSERT INTO wp_storage_payment_history
(customer_id, customer_name, transaction_id, amount,
 currency, payment_months, items_paid, payment_date)
VALUES
(123, 'Jane Smith', 'ch_3SDK...', 1820.70,
 'EUR', 6, '[...items JSON...]', NOW());
```

### 2. Receipt Generation

**PDF Created:**
- Filename: `receipt-unit5-pallet2-2025-10-01-10-00-28.pdf`
- Saved temporarily in: `wp-content/uploads/receipts/`
- Contains:
  - Company logo and details
  - Customer information
  - Transaction ID
  - Payment date and time
  - List of all items paid
  - Advance payment section (if applicable)
  - Payment history table
  - Total amount paid

### 3. Email Notifications

**Email to Customer:**
```
From: company@email.com
To: customer@email.com
Subject: Payment Confirmation - Self Storage Cyprus
Attachment: receipt-unit5-pallet2-2025-10-01-10-00-28.pdf

Dear Jane Smith,

Thank you for your payment. We have successfully received
your payment and your receipt is attached to this email.

Payment Details:
- Transaction ID: ch_3SDK1XBJCxRc2cUi0TcwjwqC
- Payment Date: October 1, 2025 10:00 AM
- Amount Paid: EUR 1,820.70

Items Paid:
- Unit 5: €150.00
- Pallet 2: €153.45

📅 Advance Payment Confirmation
Payment Period: 6 month(s)
Items Paid Until:
• Unit 5: April 30, 2026
• Pallet 2: April 30, 2026
✓ Your rental period has been extended automatically.
```

**Email to Admin (Copy):**
```
From: company@email.com
To: admin@email.com
Subject: [Admin Copy] Payment Confirmation - Self Storage Cyprus
Same content as customer email
```

### 4. System Logs

```
[2025-10-01 10:00:28] SUM Billing: Extending customer 123 rentals by 6 months
[2025-10-01 10:00:28] SUM Billing: Extended unit 5 from 2025-10-31 to 2026-04-30
[2025-10-01 10:00:28] SUM Billing: Extended pallet 2 from 2025-10-30 to 2026-04-30
[2025-10-01 10:00:28] SUM Billing: Period extension complete - 1 units and 1 pallets updated
[2025-10-01 10:00:28] SUM Payment History: Recorded payment 45 for customer 123 - EUR 1820.70
```

### 5. Frontend Response

**Success message shown to customer:**
```
✓ Payment Successful!

Your payment of €1,820.70 has been processed successfully.

Receipt has been sent to your email.

Your rentals are now paid until April 30, 2026.

Thank you for your payment!
```

---

## What Does NOT Happen

❌ Unit/pallet is NOT locked or unlocked
❌ No SMS notifications (only email)
❌ No physical access changes
❌ No inventory changes
❌ No refund processing

---

## Future Automated Billing Cycle

### After Payment, System Automatically:

**Example: Customer paid 6 months on Oct 1, 2025**

```
Oct 1, 2025:  ✓ Payment received, extended to Apr 30, 2026
Oct 2-Mar 2026: [No actions needed - customer is paid]
Apr 30, 2026:   [System checks at midnight]
                ↓
Apr 30, 2026:   📧 Invoice sent (invoice_generation_days = 0)
                "Payment Due: Apr 30, 2026"
                "Amount: €303.45"
                payment_status changed to: unpaid
                ↓
May 7, 2026:    📧 First Reminder sent (7 days after invoice)
                "Payment reminder - due soon"
                ↓
Apr 28, 2026:   📧 Final Reminder sent (2 days before due)
                "⚠️ URGENT: Payment due in 2 days"
                ↓
Apr 30, 2026:   [If still unpaid]
                payment_status changed to: overdue
```

---

# Timeline Examples

## Example 1: Regular Monthly Payment

```
Today: October 1, 2025
Current status: Unit paid until Oct 31, 2025 (unpaid)

Customer pays 1 month (€150):
────────────────────────────────────────────────────
Before:  Unit 5 [========|] Oct 31, 2025 (unpaid)
After:   Unit 5 [============|] Nov 30, 2025 (paid)

Extension: +30 days
```

## Example 2: 6 Month Advance Payment

```
Today: October 1, 2025
Current status:
- Unit 5: Oct 31, 2025 (unpaid)
- Pallet 2: Oct 30, 2025 (unpaid)

Customer pays 6 months (€1,820.70):
────────────────────────────────────────────────────
Before:
Unit 5:    [==|] Oct 31, 2025
Pallet 2:  [==|] Oct 30, 2025

After:
Unit 5:    [==========================|] Apr 30, 2026
Pallet 2:  [==========================|] Apr 30, 2026

Extension: +6 months each (from their own dates)
No billing until: April 30, 2026
```

## Example 3: Mixed Items with Different Dates

```
Today: October 1, 2025
Current status:
- Unit 5: Oct 31, 2025
- Unit 7: Nov 15, 2025
- Pallet 2: Oct 30, 2025

Customer pays 3 months:
────────────────────────────────────────────────────
Unit 5:    Oct 31 ──→ Jan 31, 2026 (+3 months)
Unit 7:    Nov 15 ──→ Feb 15, 2026 (+3 months)
Pallet 2:  Oct 30 ──→ Jan 30, 2026 (+3 months)

Each item keeps its own cycle!
```

---

## Summary

### Automated Billing:
✅ Runs daily at midnight
✅ Sends invoices X days before due date
✅ Sends first reminder 7 days after invoice
✅ Sends final reminder 2 days before due date
✅ Fully configurable in settings

### Payments (1 Month):
✅ Extends period_until by 1 month
✅ Sets payment_status to 'paid'
✅ Sends receipt immediately
✅ Records in payment history

### Payments (Advance):
✅ Extends EACH item from its OWN date
✅ Shows "Paid Until" dates in receipt
✅ Records advance months in history
✅ Filename includes all items
✅ No more billing until expiry date

### After Payment:
✅ Database updated immediately
✅ Receipt PDF generated and emailed
✅ Payment history recorded
✅ Admin copy sent
✅ System logs everything
✅ Next billing cycle starts automatically

**The system is fully automated and production-ready!** 🎉
