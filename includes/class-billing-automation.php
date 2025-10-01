<?php
/**
 * Automated Billing System for Storage Unit Manager
 * Handles invoice generation, reminders, and payment tracking for customers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SUM_Billing_Automation {

    private $database;
    private $customer_db;
    private $email_handler;
    private $pdf_generator;

    public function __construct() {
        $this->init_dependencies();
    }

    private function init_dependencies() {
        if (!class_exists('SUM_Database')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-database.php';
        }
        if (!class_exists('SUM_Customer_Database')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-database.php';
        }
        if (!class_exists('SUM_Customer_Email_Handler')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-email-handler.php';
        }
        if (!class_exists('SUM_Customer_PDF_Generator')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-pdf-generator.php';
        }

        $this->database = new SUM_Database();
        $this->customer_db = new SUM_Customer_Database();
        $this->email_handler = new SUM_Customer_Email_Handler($this->customer_db);
        $this->pdf_generator = new SUM_Customer_PDF_Generator($this->customer_db, $this->database);
    }

    public function init() {
        if (!wp_next_scheduled('sum_billing_daily_check')) {
            wp_schedule_event(time(), 'daily', 'sum_billing_daily_check');
        }

        add_action('sum_billing_daily_check', array($this, 'process_daily_billing'));
    }

    public function get_billing_settings() {
        return array(
            'auto_billing_enabled' => $this->database->get_setting('auto_billing_enabled', '1'),
            'invoice_generation_days' => absint($this->database->get_setting('invoice_generation_days', '0')),
            'first_reminder_days' => absint($this->database->get_setting('first_reminder_days', '7')),
            'second_reminder_days' => absint($this->database->get_setting('second_reminder_days', '2')),
            'customers_only' => $this->database->get_setting('billing_customers_only', '1'),
        );
    }

    public function process_daily_billing() {
        $settings = $this->get_billing_settings();

        if ($settings['auto_billing_enabled'] !== '1') {
            return;
        }

        $this->generate_upcoming_invoices($settings['invoice_generation_days']);
        $this->send_first_reminders($settings['first_reminder_days']);
        $this->send_second_reminders($settings['second_reminder_days']);

        error_log('SUM Billing: Daily billing process completed at ' . current_time('mysql'));
    }

    private function generate_upcoming_invoices($days_before) {
        global $wpdb;

        $target_date = date('Y-m-d', strtotime("+{$days_before} days"));

        $customers = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT c.id, c.full_name, c.email
            FROM {$wpdb->prefix}storage_customers c
            LEFT JOIN {$wpdb->prefix}storage_units u ON u.customer_name = c.full_name
            LEFT JOIN {$wpdb->prefix}storage_pallets p ON p.customer_name = c.full_name
            WHERE (
                (u.is_occupied = 1 AND u.period_until = %s AND u.payment_status != 'paid')
                OR
                (p.customer_name IS NOT NULL AND p.period_until = %s AND p.payment_status != 'paid')
            )
        ", $target_date, $target_date));

        foreach ($customers as $customer) {
            $this->generate_and_send_invoice($customer->id, 'new_invoice');
        }

        error_log("SUM Billing: Generated invoices for " . count($customers) . " customers");
    }

    private function send_first_reminders($days_after_invoice) {
        global $wpdb;

        $reminder_date = date('Y-m-d', strtotime("-{$days_after_invoice} days"));

        $customers = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT
                c.id,
                c.full_name,
                c.email,
                MAX(u.period_until) as due_date
            FROM {$wpdb->prefix}storage_customers c
            LEFT JOIN {$wpdb->prefix}storage_units u ON u.customer_name = c.full_name
            LEFT JOIN {$wpdb->prefix}storage_pallets p ON p.customer_name = c.full_name
            WHERE (
                (u.is_occupied = 1 AND u.payment_status IN ('unpaid', 'overdue'))
                OR
                (p.payment_status IN ('unpaid', 'overdue'))
            )
            AND (u.period_until >= %s OR p.period_until >= %s)
            GROUP BY c.id
        ", $reminder_date, $reminder_date));

        foreach ($customers as $customer) {
            $last_reminder = get_transient("sum_reminder_1_{$customer->id}");
            if (!$last_reminder) {
                $this->send_reminder_email($customer->id, 'first_reminder');
                set_transient("sum_reminder_1_{$customer->id}", time(), DAY_IN_SECONDS * 30);
            }
        }

        error_log("SUM Billing: Sent first reminders to " . count($customers) . " customers");
    }

    private function send_second_reminders($days_before_due) {
        global $wpdb;

        $target_date = date('Y-m-d', strtotime("+{$days_before_due} days"));

        $customers = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT
                c.id,
                c.full_name,
                c.email,
                MIN(u.period_until) as due_date
            FROM {$wpdb->prefix}storage_customers c
            LEFT JOIN {$wpdb->prefix}storage_units u ON u.customer_name = c.full_name
            LEFT JOIN {$wpdb->prefix}storage_pallets p ON p.customer_name = c.full_name
            WHERE (
                (u.is_occupied = 1 AND u.payment_status IN ('unpaid', 'overdue') AND u.period_until = %s)
                OR
                (p.payment_status IN ('unpaid', 'overdue') AND p.period_until = %s)
            )
            GROUP BY c.id
        ", $target_date, $target_date));

        foreach ($customers as $customer) {
            $last_reminder = get_transient("sum_reminder_2_{$customer->id}");
            if (!$last_reminder) {
                $this->send_reminder_email($customer->id, 'final_reminder');
                set_transient("sum_reminder_2_{$customer->id}", time(), DAY_IN_SECONDS * 30);
            }
        }

        error_log("SUM Billing: Sent final reminders to " . count($customers) . " customers");
    }

    private function generate_and_send_invoice($customer_id, $type = 'new_invoice') {
        try {
            $result = $this->email_handler->send_full_invoice($customer_id);

            if ($result && isset($result['success']) && $result['success']) {
                update_option("sum_last_invoice_{$customer_id}", array(
                    'date' => current_time('mysql'),
                    'type' => $type
                ));
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("SUM Billing Error: Failed to send invoice for customer {$customer_id}: " . $e->getMessage());
            return false;
        }
    }

    private function send_reminder_email($customer_id, $reminder_type) {
        $customer = $this->customer_db->get_customer($customer_id);
        if (!$customer) {
            return false;
        }

        $rentals = $this->customer_db->get_customer_rentals($customer_id, true);
        if (empty($rentals)) {
            return false;
        }

        $subject = $reminder_type === 'first_reminder'
            ? 'Payment Reminder - Your Storage Invoice is Due Soon'
            : 'URGENT: Payment Due in 2 Days - Storage Invoice';

        $total_due = 0;
        foreach ($rentals as $rental) {
            $total_due += floatval($rental['monthly_price'] ?? 0);
        }

        $payment_url = $this->generate_payment_link($customer_id);

        $message = $this->build_reminder_email($customer, $rentals, $total_due, $payment_url, $reminder_type);

        $headers = array('Content-Type: text/html; charset=UTF-8');

        $sent = wp_mail($customer['email'], $subject, $message, $headers);

        if ($sent) {
            error_log("SUM Billing: {$reminder_type} sent to customer {$customer_id}");
        }

        return $sent;
    }

    private function build_reminder_email($customer, $rentals, $total_due, $payment_url, $type) {
        $company_name = $this->database->get_setting('company_name', 'Self Storage Cyprus');
        $company_email = $this->database->get_setting('company_email', get_option('admin_email'));

        $is_urgent = ($type === 'final_reminder');

        $html = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: ' . ($is_urgent ? '#ef4444' : '#10b981') . '; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .rental-item { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #10b981; }
                .total { font-size: 24px; font-weight: bold; color: #10b981; margin: 20px 0; }
                .button { display: inline-block; background: #10b981; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . ($is_urgent ? '⚠️ URGENT PAYMENT REMINDER' : '📧 Payment Reminder') . '</h1>
                </div>
                <div class="content">
                    <p>Dear ' . esc_html($customer['full_name']) . ',</p>';

        if ($is_urgent) {
            $html .= '<div class="warning">
                        <strong>⚠️ Your payment is due in 2 days!</strong><br>
                        Please settle your invoice immediately to avoid service interruption.
                      </div>';
        } else {
            $html .= '<p>This is a friendly reminder that you have an outstanding invoice for your storage rentals.</p>';
        }

        $html .= '<h3>Outstanding Items:</h3>';

        foreach ($rentals as $rental) {
            $html .= '<div class="rental-item">
                        <strong>' . esc_html($rental['name']) . '</strong><br>
                        Period: ' . esc_html($rental['period_from']) . ' to ' . esc_html($rental['period_until']) . '<br>
                        Amount: EUR ' . number_format($rental['monthly_price'], 2) . '
                      </div>';
        }

        $html .= '<div class="total">Total Due: EUR ' . number_format($total_due, 2) . '</div>';

        $html .= '<p><a href="' . esc_url($payment_url) . '" class="button">Pay Now</a></p>';

        $html .= '<p>If you have already made this payment, please disregard this reminder.</p>
                  <p>For any questions, contact us at ' . esc_html($company_email) . '</p>
                  <p>Best regards,<br>' . esc_html($company_name) . '</p>
                </div>
            </div>
        </body>
        </html>';

        return $html;
    }

    private function generate_payment_link($customer_id) {
        global $wpdb;

        $token = $wpdb->get_var($wpdb->prepare(
            "SELECT payment_token FROM {$wpdb->prefix}storage_customers WHERE id = %d",
            $customer_id
        ));

        if (!$token) {
            $token = wp_generate_password(32, false);
            $wpdb->update(
                $wpdb->prefix . 'storage_customers',
                array('payment_token' => $token),
                array('id' => $customer_id)
            );
        }

        $payment_page_id = $this->database->get_setting('payment_page_id');
        if ($payment_page_id) {
            return add_query_arg(
                array(
                    'customer_id' => $customer_id,
                    'token' => $token
                ),
                get_permalink($payment_page_id)
            );
        }

        return home_url('/?page=storage-payment&customer_id=' . $customer_id . '&token=' . $token);
    }

    public function update_rental_periods_after_payment($customer_id, $months_paid) {
        global $wpdb;

        $customer = $this->customer_db->get_customer($customer_id);
        if (!$customer) {
            return false;
        }

        $units_table = $wpdb->prefix . 'storage_units';
        $pallets_table = $wpdb->prefix . 'storage_pallets';

        $units = $wpdb->get_results($wpdb->prepare(
            "SELECT id, period_until FROM {$units_table}
             WHERE customer_name = %s AND is_occupied = 1",
            $customer['full_name']
        ));

        foreach ($units as $unit) {
            $current_until = $unit->period_until;
            $new_until = date('Y-m-d', strtotime($current_until . ' +' . $months_paid . ' months'));

            $wpdb->update(
                $units_table,
                array(
                    'period_until' => $new_until,
                    'payment_status' => 'paid'
                ),
                array('id' => $unit->id)
            );
        }

        $pallets = $wpdb->get_results($wpdb->prepare(
            "SELECT id, period_until FROM {$pallets_table}
             WHERE customer_name = %s",
            $customer['full_name']
        ));

        foreach ($pallets as $pallet) {
            $current_until = $pallet->period_until;
            $new_until = date('Y-m-d', strtotime($current_until . ' +' . $months_paid . ' months'));

            $wpdb->update(
                $pallets_table,
                array(
                    'period_until' => $new_until,
                    'payment_status' => 'paid'
                ),
                array('id' => $pallet->id)
            );
        }

        error_log("SUM Billing: Updated rental periods for customer {$customer_id} - extended by {$months_paid} months");

        return true;
    }
}
