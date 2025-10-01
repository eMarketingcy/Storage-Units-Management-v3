# Automated Billing System - Implementation Guide

## Overview
This document outlines the comprehensive automated billing system for the Storage Unit Manager plugin.

## System Architecture

### 1. Automated Invoice Generation
**Trigger:** Daily cron job checks rental periods
**Logic:**
- If `period_until` date is X days away (configurable), generate invoice for next billing period
- Example: Unit paid until 15/10/2025 → Generate invoice for 15/10/2025 to 14/11/2025
- **Only for Customers** - Units and Pallets remain manual

### 2. Reminder Email Workflow
**First Reminder:**
- Sent X days after invoice generation (default: 7 days)
- Only if payment status is still 'unpaid'

**Final Reminder:**
- Sent X days before due date (default: 2 days before period_until)
- Example: If due 14/11/2025, send reminder on 12/11/2025
- Marked as URGENT

### 3. Advance Payment Options
**Payment Page Enhancement:**
- Add dropdown: "Pay for: [1 month] [3 months] [6 months] [8 months] [12 months]"
- Calculate total: base_amount × months_selected
- Apply discount if desired (e.g., 5% off for 12 months)

**Period Extension Logic:**
- Normal invoice: paid until 14/11/2025
- Customer pays for 12 months
- New period_until: 14/11/2026
- All units/pallets for that customer get extended
- Payment status: 'paid'

### 4. Receipt Updates
**New Receipt Fields:**
- "Paid Until Date: 14/11/2026"
- "Payment Period: 12 months"
- "Next Invoice Date: 15/10/2026"

## Files Created

### 1. `/includes/class-billing-automation.php`
Core billing automation engine with:
- Daily cron job handler
- Invoice generation logic
- Reminder email system
- Period extension after advance payments

### 2. `/templates/billing-settings-page.php`
Admin interface for configuring:
- Enable/disable automation
- Invoice generation timing (days before period end)
- First reminder timing (days after invoice)
- Final reminder timing (days before due date)
- Customers-only mode toggle

## Required Modifications

### 1. Main Plugin File (`storage-unit-manager.php`)
```php
// Add after line 150 (after PDF dependencies)
require_once SUM_PLUGIN_PATH . 'includes/class-billing-automation.php';

// In your main init function or constructor:
$billing_automation = new SUM_Billing_Automation();
$billing_automation->init();
```

### 2. Add Menu Item
In your admin menu setup, add:
```php
add_submenu_page(
    'storage-units',
    'Billing Automation',
    'Billing Automation',
    'manage_options',
    'storage-billing-settings',
    array($this, 'render_billing_settings_page')
);
```

### 3. Payment Handler Modifications

#### File: `/includes/class-payment-handler.php`

**A. Update payment form to include advance payment options:**

Around line 200-250, after displaying the total, add:
```php
<div class="sum-payment-period-selector" style="margin: 20px 0; padding: 20px; background: #f0f9ff; border-radius: 8px;">
    <h3>Payment Period</h3>
    <p>Pay in advance and extend your rental period:</p>
    <select name="payment_months" id="payment-months" style="padding: 10px; font-size: 16px;">
        <option value="1" selected>1 Month - EUR <?php echo number_format($total, 2); ?></option>
        <option value="3">3 Months - EUR <?php echo number_format($total * 3, 2); ?></option>
        <option value="6">6 Months - EUR <?php echo number_format($total * 6, 2); ?></option>
        <option value="8">8 Months - EUR <?php echo number_format($total * 8, 2); ?></option>
        <option value="12">12 Months - EUR <?php echo number_format($total * 12, 2); ?></option>
    </select>
    <p class="description">Paying for multiple months extends your rental period automatically.</p>
</div>

<script>
jQuery(document).ready(function($) {
    $('#payment-months').on('change', function() {
        var months = parseInt($(this).val());
        var baseAmount = <?php echo $total * 100; ?>; // in cents
        var newAmount = baseAmount * months;

        // Update displayed amount
        $('#sum-payment-amount-display').text('EUR ' + (newAmount/100).toFixed(2));

        // Update Stripe amount
        window.paymentAmount = newAmount;
    });
});
</script>
```

**B. In `process_stripe_payment()` function, after line 497:**

```php
// Get payment months from POST
$payment_months = isset($_POST['payment_months']) ? absint($_POST['payment_months']) : 1;

if ($payment_months > 1 && $is_customer) {
    // Extend rental periods for advance payments
    if (class_exists('SUM_Billing_Automation')) {
        $billing = new SUM_Billing_Automation();
        $billing->update_rental_periods_after_payment($entity_id, $payment_months);
    }
}
```

**C. Update receipt generation (around line 750-900):**

Add to the receipt HTML:
```php
if ($payment_months > 1) {
    $html .= '<div class="payment-period-info" style="background:#e0f2fe;padding:15px;margin:20px 0;border-radius:8px;">
        <p style="margin:0;"><strong>Payment Period:</strong> ' . $payment_months . ' month(s)</p>
        <p style="margin:8px 0 0;"><strong>Paid Until:</strong> ' . $new_period_until . '</p>
    </div>';
}
```

### 4. Customer Database Updates

#### File: `/includes/class-customer-database.php`

Ensure the `storage_customers` table has a `payment_token` column:
```php
// In your table creation function:
payment_token varchar(64) DEFAULT NULL,
```

### 5. AJAX Handler for Test Billing

#### File: `/includes/class-ajax-handlers.php`

Add this method:
```php
public function test_billing() {
    check_ajax_referer('sum_test_billing', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    if (class_exists('SUM_Billing_Automation')) {
        $billing = new SUM_Billing_Automation();
        $billing->process_daily_billing();
        wp_send_json_success(['message' => 'Billing process completed successfully. Check error log for details.']);
    } else {
        wp_send_json_error(['message' => 'Billing automation class not found']);
    }
}
```

And register it in `init()`:
```php
add_action('wp_ajax_sum_test_billing', array($this, 'test_billing'));
```

## Database Schema Verification

Ensure these columns exist:

### `storage_units` table:
- `period_from` (date)
- `period_until` (date)
- `payment_status` (varchar) - values: 'paid', 'unpaid', 'overdue'
- `monthly_price` (decimal)

### `storage_pallets` table:
- `period_from` (date)
- `period_until` (date)
- `payment_status` (varchar)
- `monthly_price` (decimal)

### `storage_customers` table:
- `payment_token` (varchar 64)
- `email` (varchar 255)
- `full_name` (varchar 255)

## Testing Checklist

1. ✅ Configure billing settings in admin
2. ✅ Create test customer with units expiring soon
3. ✅ Run test billing process
4. ✅ Verify invoice email sent
5. ✅ Test payment with advance options (3, 6, 12 months)
6. ✅ Verify period_until dates updated correctly
7. ✅ Check receipt shows "Paid Until" date
8. ✅ Verify reminders sent at correct intervals
9. ✅ Confirm manual invoices still work for units/pallets

## Important Notes

1. **Cron Job**: WordPress cron runs when someone visits the site. For production, use system cron:
   ```
   */15 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron
   ```

2. **Email Deliverability**: Consider using SMTP plugin for reliable email delivery

3. **Testing**: Use transients to prevent duplicate reminders during testing

4. **Logging**: All billing actions are logged to WordPress error log

5. **Token Security**: Payment tokens are rotated after each successful payment

## Future Enhancements

- Discount management for advance payments
- Payment plan options (installments)
- Auto-suspend access for overdue accounts
- SMS reminders via integration
- Customer payment history dashboard
- Refund handling for early termination

## Support

For issues or questions, check the error log:
- Location: `wp-content/debug.log`
- Enable: Add to wp-config.php:
  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  define('WP_DEBUG_DISPLAY', false);
  ```
