<?php
/**
 * Billing Automation Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($database)) {
    global $wpdb;
    if (class_exists('SUM_Database')) {
        $database = new SUM_Database();
    }
}

if (isset($_POST['sum_billing_settings_submit']) && check_admin_referer('sum_billing_settings_nonce')) {
    $database->save_setting('auto_billing_enabled', !empty($_POST['auto_billing_enabled']) ? '1' : '0');
    $database->save_setting('invoice_generation_days', absint($_POST['invoice_generation_days'] ?? 0));
    $database->save_setting('first_reminder_days', absint($_POST['first_reminder_days'] ?? 7));
    $database->save_setting('second_reminder_days', absint($_POST['second_reminder_days'] ?? 2));
    $database->save_setting('billing_customers_only', !empty($_POST['billing_customers_only']) ? '1' : '0');

    echo '<div class="notice notice-success"><p>Billing settings saved successfully!</p></div>';
}

$auto_enabled = $database->get_setting('auto_billing_enabled', '1');
$invoice_days = $database->get_setting('invoice_generation_days', '0');
$first_reminder = $database->get_setting('first_reminder_days', '7');
$second_reminder = $database->get_setting('second_reminder_days', '2');
$customers_only = $database->get_setting('billing_customers_only', '1');
?>

<div class="wrap">
    <h1>⚙️ Automated Billing Settings</h1>

    <div class="sum-settings-intro" style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #10b981; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0;">How Automated Billing Works</h2>
        <p>The system automatically generates invoices and sends reminders based on rental period end dates. Configure the timing below:</p>
        <ol>
            <li><strong>Invoice Generation:</strong> New invoices are created X days before the rental period ends</li>
            <li><strong>First Reminder:</strong> Sent X days after invoice generation if still unpaid</li>
            <li><strong>Final Reminder:</strong> Sent X days before the due date if still unpaid</li>
        </ol>
        <p><strong>Example:</strong> If a unit is paid until <code>15/10/2025</code>, the system will:
            <ul style="list-style: disc; margin-left: 30px;">
                <li>Generate invoice for period <code>15/10/2025 - 14/11/2025</code> on the generation day</li>
                <li>Send first reminder 7 days later if unpaid</li>
                <li>Send final reminder 2 days before <code>14/11/2025</code> if still unpaid</li>
            </ul>
        </p>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('sum_billing_settings_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="auto_billing_enabled">Enable Automated Billing</label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="auto_billing_enabled" name="auto_billing_enabled" value="1" <?php checked($auto_enabled, '1'); ?>>
                        <strong>Enable automatic invoice generation and reminders</strong>
                    </label>
                    <p class="description">When enabled, the system will automatically process billing daily</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="billing_customers_only">Automated Billing Scope</label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="billing_customers_only" name="billing_customers_only" value="1" <?php checked($customers_only, '1'); ?>>
                        <strong>Send automated invoices to Customers only</strong>
                    </label>
                    <p class="description">When enabled, automated billing applies only to Customers. Units and Pallets can still send manual invoices.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="invoice_generation_days">Invoice Generation Timing</label>
                </th>
                <td>
                    <input type="number" id="invoice_generation_days" name="invoice_generation_days" value="<?php echo esc_attr($invoice_days); ?>" min="0" max="30" class="small-text">
                    <span>days before rental period ends</span>
                    <p class="description">
                        Generate new invoices this many days before the current period expires.<br>
                        <strong>Example:</strong> Set to <code>0</code> to generate on the period end date, <code>7</code> to generate a week before.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="first_reminder_days">First Reminder Timing</label>
                </th>
                <td>
                    <input type="number" id="first_reminder_days" name="first_reminder_days" value="<?php echo esc_attr($first_reminder); ?>" min="1" max="30" class="small-text">
                    <span>days after invoice generation</span>
                    <p class="description">
                        Send the first payment reminder this many days after the invoice was generated (if still unpaid).<br>
                        <strong>Recommended:</strong> 7 days
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="second_reminder_days">Final Reminder Timing</label>
                </th>
                <td>
                    <input type="number" id="second_reminder_days" name="second_reminder_days" value="<?php echo esc_attr($second_reminder); ?>" min="1" max="14" class="small-text">
                    <span>days before due date</span>
                    <p class="description">
                        Send the final urgent reminder this many days before the rental period ends (if still unpaid).<br>
                        <strong>Recommended:</strong> 2 days
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="sum_billing_settings_submit" class="button button-primary button-large">
                💾 Save Billing Settings
            </button>
        </p>
    </form>

    <hr style="margin: 40px 0;">

    <div class="sum-billing-test-section" style="background: #f0f9ff; padding: 20px; border-left: 4px solid #3b82f6;">
        <h2>🧪 Test Billing System</h2>
        <p>Run the billing process manually to test your configuration:</p>
        <button type="button" id="sum-test-billing" class="button button-secondary">
            ▶️ Run Billing Process Now
        </button>
        <div id="sum-billing-test-result" style="margin-top: 15px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#sum-test-billing').on('click', function() {
        const $btn = $(this);
        const $result = $('#sum-billing-test-result');

        $btn.prop('disabled', true).text('⏳ Running...');
        $result.html('<p>Processing billing...</p>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sum_test_billing',
                nonce: '<?php echo wp_create_nonce('sum_test_billing'); ?>'
            },
            success: function(response) {
                $btn.prop('disabled', false).text('▶️ Run Billing Process Now');

                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error inline"><p>Error: ' + (response.data.message || 'Unknown error') + '</p></div>');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('▶️ Run Billing Process Now');
                $result.html('<div class="notice notice-error inline"><p>Connection error</p></div>');
            }
        });
    });
});
</script>

<style>
.sum-settings-intro ul {
    list-style: disc;
    margin-left: 30px;
}
.sum-settings-intro code {
    background: #f3f4f6;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}
.notice.inline {
    margin: 10px 0;
    padding: 10px 15px;
}
</style>
