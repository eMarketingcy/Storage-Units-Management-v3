<?php
/**
 * Payment History Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SUM_Payment_History')) {
    require_once SUM_PLUGIN_PATH . 'includes/class-payment-history.php';
}

$history = new SUM_Payment_History();

// Handle date filter
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
$customer_filter = isset($_GET['customer_id']) ? absint($_GET['customer_id']) : 0;

// Get payments
if ($customer_filter) {
    $payments = $history->get_customer_payments($customer_filter);
} else {
    $payments = $history->get_payments_by_date_range($start_date, $end_date);
}

// Calculate totals
$total_revenue = 0;
$total_payments = count($payments);
foreach ($payments as $payment) {
    $total_revenue += floatval($payment['amount']);
}

// Get all customers for filter dropdown
global $wpdb;
$customers = $wpdb->get_results("SELECT id, full_name FROM {$wpdb->prefix}storage_customers ORDER BY full_name ASC");
?>

<div class="wrap">
    <h1>💰 Payment History</h1>

    <div class="sum-filters" style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <form method="get" action="">
            <input type="hidden" name="page" value="sum-payment-history">

            <div style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" style="padding: 8px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">End Date</label>
                    <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" style="padding: 8px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Customer</label>
                    <select name="customer_id" style="padding: 8px; min-width: 200px;">
                        <option value="0">All Customers</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo esc_attr($customer->id); ?>" <?php selected($customer_filter, $customer->id); ?>>
                                <?php echo esc_html($customer->full_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="button button-primary">Filter</button>
                    <a href="?page=sum-payment-history" class="button">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="sum-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 8px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Total Revenue</div>
            <div style="font-size: 32px; font-weight: bold;">€<?php echo number_format($total_revenue, 2); ?></div>
        </div>

        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 25px; border-radius: 8px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Total Payments</div>
            <div style="font-size: 32px; font-weight: bold;"><?php echo $total_payments; ?></div>
        </div>

        <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 25px; border-radius: 8px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Average Payment</div>
            <div style="font-size: 32px; font-weight: bold;">€<?php echo $total_payments > 0 ? number_format($total_revenue / $total_payments, 2) : '0.00'; ?></div>
        </div>
    </div>

    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <?php if (empty($payments)): ?>
            <p style="text-align: center; padding: 40px; color: #666;">No payments found for the selected period.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 0;">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Months</th>
                        <th>Items Paid</th>
                        <th>Transaction ID</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <?php
                        $items = is_array($payment['items_paid']) ? $payment['items_paid'] : [];
                        $items_summary = [];
                        foreach ($items as $item) {
                            $items_summary[] = ucfirst($item['type'] ?? 'Item') . ' ' . ($item['name'] ?? '');
                        }
                        $items_text = !empty($items_summary) ? implode(', ', $items_summary) : 'N/A';
                        ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($payment['id']); ?></strong></td>
                            <td>
                                <strong><?php echo esc_html($payment['customer_name']); ?></strong>
                                <br>
                                <small style="color: #666;">ID: <?php echo esc_html($payment['customer_id']); ?></small>
                            </td>
                            <td>
                                <strong style="color: #10b981; font-size: 16px;">
                                    <?php echo esc_html($payment['currency']); ?> <?php echo number_format(floatval($payment['amount']), 2); ?>
                                </strong>
                            </td>
                            <td>
                                <span style="background: #e0f2fe; color: #0369a1; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?php echo esc_html($payment['payment_months']); ?> month<?php echo $payment['payment_months'] > 1 ? 's' : ''; ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($items_text); ?></td>
                            <td>
                                <code style="font-size: 11px;"><?php echo esc_html(substr($payment['transaction_id'], 0, 20)); ?>...</code>
                            </td>
                            <td><?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($payment['payment_date']))); ?></td>
                            <td>
                                <button type="button" class="button button-small" onclick="viewPaymentDetails(<?php echo esc_js($payment['id']); ?>)">View Details</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Details Modal -->
<div id="payment-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative;">
        <button onclick="closePaymentDetails()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        <div id="payment-details-content"></div>
    </div>
</div>

<script>
function viewPaymentDetails(paymentId) {
    const payments = <?php echo json_encode($payments); ?>;
    const payment = payments.find(p => p.id == paymentId);

    if (!payment) return;

    let itemsHtml = '';
    if (payment.items_paid && payment.items_paid.length > 0) {
        itemsHtml = '<h3 style="margin-top: 25px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">Items Paid</h3><ul style="list-style: none; padding: 0;">';
        payment.items_paid.forEach(item => {
            const type = item.type ? item.type.charAt(0).toUpperCase() + item.type.slice(1) : 'Item';
            const paidUntil = item.period_until ? new Date(item.period_until).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
            itemsHtml += `
                <li style="background: #f9fafb; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #10b981;">
                    <div style="font-weight: 600; margin-bottom: 5px;">${type} ${item.name || ''}</div>
                    <div style="color: #666; font-size: 13px;">Monthly Price: €${parseFloat(item.monthly_price || 0).toFixed(2)}</div>
                    <div style="color: #10b981; font-size: 13px; font-weight: 600; margin-top: 5px;">Paid Until: ${paidUntil}</div>
                </li>
            `;
        });
        itemsHtml += '</ul>';
    }

    const html = `
        <h2 style="margin-top: 0; color: #1e293b;">Payment Details #${payment.id}</h2>

        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <div style="color: #666; font-size: 12px; margin-bottom: 3px;">CUSTOMER</div>
                    <div style="font-weight: 600; color: #1e293b;">${payment.customer_name}</div>
                </div>
                <div>
                    <div style="color: #666; font-size: 12px; margin-bottom: 3px;">AMOUNT</div>
                    <div style="font-weight: 700; color: #10b981; font-size: 18px;">${payment.currency} ${parseFloat(payment.amount).toFixed(2)}</div>
                </div>
                <div>
                    <div style="color: #666; font-size: 12px; margin-bottom: 3px;">PAYMENT PERIOD</div>
                    <div style="font-weight: 600; color: #1e293b;">${payment.payment_months} month${payment.payment_months > 1 ? 's' : ''}</div>
                </div>
                <div>
                    <div style="color: #666; font-size: 12px; margin-bottom: 3px;">DATE</div>
                    <div style="font-weight: 600; color: #1e293b;">${new Date(payment.payment_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</div>
                </div>
            </div>
        </div>

        <div style="margin: 20px 0;">
            <div style="color: #666; font-size: 12px; margin-bottom: 5px;">TRANSACTION ID</div>
            <code style="background: #f1f5f9; padding: 8px 12px; border-radius: 4px; display: block; word-break: break-all; font-size: 12px;">${payment.transaction_id}</code>
        </div>

        ${itemsHtml}
    `;

    document.getElementById('payment-details-content').innerHTML = html;
    document.getElementById('payment-details-modal').style.display = 'flex';
}

function closePaymentDetails() {
    document.getElementById('payment-details-modal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('payment-details-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePaymentDetails();
    }
});
</script>

<style>
.sum-filters label {
    font-weight: 600;
    color: #1e293b;
}

.wp-list-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #1e293b;
}

.wp-list-table tbody tr:hover {
    background: #f8fafc;
}
</style>
<?php
