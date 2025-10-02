<?php
/**
 * Payment History Manager
 * Tracks all payments made by customers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SUM_Payment_History {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'storage_payment_history';
        $this->ensure_table_exists();
    }

    private function ensure_table_exists() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            customer_name varchar(255) NOT NULL,
            payment_token varchar(255) DEFAULT NULL,
            transaction_id varchar(255) DEFAULT NULL,
            amount decimal(10,2) DEFAULT NULL,
            currency varchar(10) DEFAULT 'EUR',
            payment_months int(11) DEFAULT 1,
            items_paid text NOT NULL,
            payment_date datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY payment_token (payment_token),
            KEY transaction_id (transaction_id),
            KEY payment_date (payment_date),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create pending payment record when invoice is sent
     * This pre-creates the payment history with items and expected until dates
     *
     * @param int $customer_id Customer ID
     * @param string $customer_name Customer full name
     * @param string $payment_token Unique payment token for this invoice
     * @param string $currency Currency code
     * @param int $payment_months Number of months to be paid
     * @param array $items_paid Array of items (units/pallets) with until dates
     * @return int|false Payment history ID or false on failure
     */
    public function create_pending_payment($customer_id, $customer_name, $payment_token, $currency = 'EUR', $payment_months = 1, $items_paid = array()) {
        global $wpdb;

        $items_json = json_encode($items_paid);

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'customer_id' => $customer_id,
                'customer_name' => $customer_name,
                'payment_token' => $payment_token,
                'currency' => $currency,
                'payment_months' => $payment_months,
                'items_paid' => $items_json,
                'status' => 'pending'
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        if ($result === false) {
            error_log("SUM Payment History: Failed to create pending payment - " . $wpdb->last_error);
            return false;
        }

        $history_id = $wpdb->insert_id;
        error_log("SUM Payment History: Created pending payment record ID {$history_id} with token {$payment_token}");
        return $history_id;
    }

    /**
     * Complete a pending payment after successful payment
     * Updates the existing record with transaction details
     *
     * @param string $payment_token Payment token from invoice
     * @param string $transaction_id Stripe transaction ID
     * @param float $amount Amount paid
     * @return bool True on success, false on failure
     */
    public function complete_payment($payment_token, $transaction_id, $amount, $payment_months = 1) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            array(
                'transaction_id' => $transaction_id,
                'amount' => $amount,
                'payment_date' => current_time('mysql'),
                'payment_months' => $payment_months,
                'status' => 'completed'
            ),
            array('payment_token' => $payment_token, 'status' => 'pending'),
            array('%s', '%f', '%s', '%d', '%s'),
            array('%s', '%s')
        );

        if ($result === false) {
            error_log("SUM Payment History: Failed to complete payment for token {$payment_token} - " . $wpdb->last_error);
            return false;
        }

        if ($result === 0) {
            error_log("SUM Payment History: No pending payment found for token {$payment_token}");
            return false;
        }

        error_log("SUM Payment History: Completed payment for token {$payment_token}, transaction {$transaction_id}");
        return true;
    }

    /**
     * Record a payment in history (LEGACY - for direct payments)
     *
     * @param int $customer_id Customer ID
     * @param string $customer_name Customer full name
     * @param string $transaction_id Stripe transaction ID
     * @param float $amount Amount paid
     * @param string $currency Currency code
     * @param int $payment_months Number of months paid
     * @param array $items_paid Array of items (units/pallets) that were paid
     * @return int|false Payment history ID or false on failure
     */
    public function record_payment($customer_id, $customer_name, $transaction_id, $amount, $currency = 'EUR', $payment_months = 1, $items_paid = array()) {
        global $wpdb;

        $items_json = json_encode($items_paid);

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'customer_id' => $customer_id,
                'customer_name' => $customer_name,
                'transaction_id' => $transaction_id,
                'amount' => $amount,
                'currency' => $currency,
                'payment_months' => $payment_months,
                'items_paid' => $items_json,
                'payment_date' => current_time('mysql'),
                'status' => 'completed'
            ),
            array('%d', '%s', '%s', '%f', '%s', '%d', '%s', '%s', '%s')
        );

        if ($result === false) {
            error_log("SUM Payment History: Failed to record payment - " . $wpdb->last_error);
            return false;
        }

        $payment_id = $wpdb->insert_id;
        error_log("SUM Payment History: Recorded payment {$payment_id} for customer {$customer_id} - {$currency} {$amount}");

        return $payment_id;
    }

    /**
     * Get payment history for a customer
     *
     * @param int $customer_id Customer ID
     * @param int $limit Number of records to return
     * @return array Payment history records
     */
    public function get_customer_payments($customer_id, $limit = 50) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE customer_id = %d
             ORDER BY payment_date DESC
             LIMIT %d",
            $customer_id,
            $limit
        ), ARRAY_A);

        foreach ($results as &$record) {
            if (!empty($record['items_paid'])) {
                $record['items_paid'] = json_decode($record['items_paid'], true);
            }
        }

        return $results;
    }

    /**
     * Get recent payment for customer and transaction
     *
     * @param int $customer_id Customer ID
     * @param string $transaction_id Transaction ID
     * @return array|null Payment record or null
     */
    public function get_payment_by_transaction($customer_id, $transaction_id) {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE customer_id = %d AND transaction_id = %s
             ORDER BY payment_date DESC
             LIMIT 1",
            $customer_id,
            $transaction_id
        ), ARRAY_A);

        if ($result && !empty($result['items_paid'])) {
            $result['items_paid'] = json_decode($result['items_paid'], true);
        }

        return $result;
    }

    /**
     * Get all payments in date range
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array Payment records
     */
    public function get_payments_by_date_range($start_date, $end_date) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE DATE(payment_date) BETWEEN %s AND %s
             ORDER BY payment_date DESC",
            $start_date,
            $end_date
        ), ARRAY_A);

        foreach ($results as &$record) {
            if (!empty($record['items_paid'])) {
                $record['items_paid'] = json_decode($record['items_paid'], true);
            }
        }

        return $results;
    }

    /**
     * Get total revenue for a date range
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return float Total revenue
     */
    public function get_revenue_by_date_range($start_date, $end_date) {
        global $wpdb;

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$this->table_name}
             WHERE DATE(payment_date) BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        return $result ? (float)$result : 0.0;
    }
}
