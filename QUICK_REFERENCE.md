# Quick Reference Guide

## 🤖 Automated Billing (Runs Daily at Midnight)

```
┌───────────────────────────────────────────────────────────────┐
│                    DAILY BILLING CYCLE                         │
├───────────────────────────────────────────────────────────────┤
│                                                                │
│  MIDNIGHT: System wakes up                                    │
│     ↓                                                          │
│  Check Settings: Is auto-billing enabled? → YES               │
│     ↓                                                          │
│  STEP 1: Send Invoices                                        │
│    → Find units expiring in X days                            │
│    → Send invoice email + PDF                                 │
│    → Mark as "unpaid"                                         │
│     ↓                                                          │
│  STEP 2: Send First Reminders (7 days after invoice)          │
│    → Find unpaid invoices sent 7 days ago                     │
│    → Send friendly reminder                                   │
│     ↓                                                          │
│  STEP 3: Send Final Reminders (2 days before due)             │
│    → Find units expiring in 2 days (still unpaid)             │
│    → Send URGENT warning                                      │
│     ↓                                                          │
│  GO BACK TO SLEEP until tomorrow                              │
│                                                                │
└───────────────────────────────────────────────────────────────┘
```

---

## 💳 Payment Flow

### Single Month Payment

```
Customer Pays 1 Month
         ↓
┌─────────────────────────┐
│ 1. Stripe Payment       │ → €150.00 charged
├─────────────────────────┤
│ 2. Database Update      │ → period_until +1 month
│                         │ → payment_status = 'paid'
├─────────────────────────┤
│ 3. No Extension Needed  │ → Only 1 month
├─────────────────────────┤
│ 4. Payment History      │ → Record transaction
├─────────────────────────┤
│ 5. Generate Receipt     │ → receipt-unit5-2025-10-01.pdf
├─────────────────────────┤
│ 6. Send Emails          │ → Customer + Admin
└─────────────────────────┘
         ↓
    ✓ COMPLETE
```

### Advance Payment (Multiple Months)

```
Customer Pays 6 Months
         ↓
┌──────────────────────────────────┐
│ 1. Stripe Payment                │ → €1,820.70 charged
├──────────────────────────────────┤
│ 2. Database Update               │ → payment_status = 'pending'
├──────────────────────────────────┤
│ 3. EXTEND EACH ITEM INDIVIDUALLY │ → Unit: Oct 31 + 6 = Apr 30
│                                  │ → Pallet: Oct 30 + 6 = Apr 30
│                                  │ → payment_status = 'paid'
├──────────────────────────────────┤
│ 4. Payment History               │ → Record with items_paid JSON
├──────────────────────────────────┤
│ 5. Generate Receipt with Dates   │ → Shows "Paid Until: Apr 30"
│                                  │ → Filename: receipt-unit5-pallet2...
├──────────────────────────────────┤
│ 6. Send Emails with Confirmation │ → "Extended for 6 months"
└──────────────────────────────────┘
         ↓
    ✓ COMPLETE
```

---

## 📊 Database Changes After Payment

### Tables Updated:

```sql
-- UNITS TABLE
UPDATE wp_storage_units
SET period_until = '2026-04-30',    -- +6 months
    payment_status = 'paid',         -- Was 'unpaid'
    last_payment_date = NOW()
WHERE id = 5;

-- PALLETS TABLE
UPDATE wp_storage_pallets
SET period_until = '2026-04-30',    -- +6 months
    payment_status = 'paid',         -- Was 'unpaid'
    last_payment_date = NOW()
WHERE id = 2;

-- PAYMENT HISTORY TABLE (NEW RECORD)
INSERT INTO wp_storage_payment_history
VALUES (
    customer_id: 123,
    transaction_id: 'ch_3SDK...',
    amount: 1820.70,
    payment_months: 6,
    items_paid: '[{"type":"unit",...}, {"type":"pallet",...}]',
    payment_date: '2025-10-01 10:00:00'
);
```

---

## 📧 Emails Sent

### After Successful Payment:

```
┌─────────────────────────────────────────┐
│ TO: customer@email.com                  │
│ SUBJECT: Payment Confirmation           │
│ ATTACHMENT: receipt-unit5-pallet2.pdf   │
├─────────────────────────────────────────┤
│ Dear Customer,                          │
│                                         │
│ ✓ Payment received: €1,820.70           │
│ Transaction: ch_3SDK...                 │
│                                         │
│ 📅 Advance Payment                      │
│ Payment Period: 6 months                │
│ Items Paid Until:                       │
│ • Unit 5: April 30, 2026                │
│ • Pallet 2: April 30, 2026              │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ TO: admin@email.com                     │
│ SUBJECT: [Admin Copy] Payment...        │
│ ATTACHMENT: Same PDF                    │
│ CONTENT: Same as customer email         │
└─────────────────────────────────────────┘
```

---

## 🔄 Payment Status Flow

```
NEW RENTAL
    ↓
[paid] ────→ Rental active, period_until in future
    ↓
[Time passes]
    ↓
X days before period_until
    ↓
[unpaid] ───→ Invoice sent, awaiting payment
    ↓
7 days later (still unpaid)
    ↓
[unpaid] ───→ First reminder sent
    ↓
2 days before period_until (still unpaid)
    ↓
[unpaid] ───→ Final reminder sent
    ↓
period_until date passes (still unpaid)
    ↓
[overdue] ──→ Past due, payment urgent
    ↓
Customer pays
    ↓
[pending] ──→ Processing payment (temporary)
    ↓
Payment confirmed
    ↓
[paid] ─────→ Period extended, cycle restarts
```

---

## ⏱️ Timeline Example

### Customer: Jane Smith
### Unit 5 + Pallet 2
### Paid 6 months on Oct 1, 2025

```
Oct 1, 2025
    │
    │ ✓ Payment received: €1,820.70
    │ ✓ Extended to Apr 30, 2026
    │ ✓ Status: paid
    │
    ├─── Oct 2-29, 2025
    │    [No action - customer paid]
    │
    ├─── Apr 23, 2026 (7 days before)
    │    [Still paid, no action yet]
    │
    ├─── Apr 30, 2026 (midnight)
    │    📧 Invoice generated and sent
    │    Status changed to: unpaid
    │    "Payment Due: Apr 30, 2026"
    │
    ├─── May 7, 2026
    │    📧 First reminder
    │    "Please pay soon"
    │
    ├─── Apr 28, 2026
    │    📧 Final reminder
    │    "⚠️ URGENT: 2 days left"
    │
    └─── May 1, 2026
         [If still unpaid → Status: overdue]
```

---

## 🎯 Key Points

### Automated Billing:
✅ Runs every day at midnight
✅ No manual intervention needed
✅ Three emails: Invoice → Reminder 1 → Reminder 2
✅ Fully configurable timing

### Single Month Payment:
✅ Extends 1 month from current period_until
✅ Simple receipt
✅ Status: paid

### Advance Payment:
✅ Each item extended INDIVIDUALLY from its own date
✅ Receipt shows all "Paid Until" dates
✅ Filename includes all items
✅ Payment history tracks everything
✅ No more billing until expiry

### After Payment:
✅ Database updated instantly
✅ Receipt generated and emailed
✅ Payment history recorded
✅ Everything logged
✅ Status: paid

---

## 🔍 Checking Payment History

### For Admins:

```php
// Get customer payment history
$history = new SUM_Payment_History();
$payments = $history->get_customer_payments(123);

// Returns:
[
    {
        "id": 1,
        "customer_id": 123,
        "customer_name": "Jane Smith",
        "transaction_id": "ch_3SDK...",
        "amount": 1820.70,
        "currency": "EUR",
        "payment_months": 6,
        "items_paid": [
            {"type": "unit", "name": "5", "period_until": "2026-04-30"},
            {"type": "pallet", "name": "2", "period_until": "2026-04-30"}
        ],
        "payment_date": "2025-10-01 10:00:00"
    }
]
```

### Database Query:

```sql
-- See all payments
SELECT * FROM wp_storage_payment_history
ORDER BY payment_date DESC;

-- Customer's payments
SELECT * FROM wp_storage_payment_history
WHERE customer_id = 123;

-- Revenue this month
SELECT SUM(amount) FROM wp_storage_payment_history
WHERE DATE(payment_date) BETWEEN '2025-10-01' AND '2025-10-31';
```

---

**Everything is automated and tracked!** 🚀
