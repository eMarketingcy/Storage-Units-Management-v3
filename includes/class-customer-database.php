<?php
/**
 * Customer Database operations for Storage Unit Manager
 */
if (!defined('ABSPATH')) {
    exit;
}

class SUM_Customer_Database {

    /** Get a list of all customers */
    public function get_customers() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storage_customers';
        // Order by name for easy viewing
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY full_name", ARRAY_A);
    }

    /** Get a single customer record */
    public function get_customer($customer_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storage_customers';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $customer_id), ARRAY_A);
    }
    
public function get_all_customers( $args = array() ) {
    global $wpdb;

    $table = $wpdb->prefix . 'storage_customers';

    $defaults = array( 'orderby' => 'id', 'order'   => 'DESC' );
    $args = wp_parse_args( $args, $defaults );
    $orderby = in_array( $args['orderby'], array( 'id', 'full_name', 'email' ), true ) ? $args['orderby'] : 'id';
    $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT id, full_name, email, phone, whatsapp, full_address FROM {$table} ORDER BY {$orderby} {$order}";
    $rows = $wpdb->get_results( $sql, ARRAY_A );

    $units_table = $wpdb->prefix . 'storage_units';
    $pallets_table = $wpdb->prefix . 'storage_pallets';

    foreach ($rows as $key => $row) {
        $customer_id = absint($row['id']);
        
        // Existing counts
        $rows[$key]['unit_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$units_table} WHERE customer_id = {$customer_id}");
        $rows[$key]['pallet_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$pallets_table} WHERE customer_id = {$customer_id}");
        
        // --- NEW: Count unpaid rentals for each customer ---
        $unpaid_units = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$units_table} WHERE customer_id = %d AND payment_status != 'paid'",
            $customer_id
        ));
        $unpaid_pallets = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$pallets_table} WHERE customer_id = %d AND payment_status != 'paid'",
            $customer_id
        ));
        $rows[$key]['unpaid_count'] = (int)$unpaid_units + (int)$unpaid_pallets;
    }
    
    return is_array( $rows ) ? $rows : array();
}

    /**
 * Insert or update a customer record.
 * @param array $data Customer data array.
 * @return array|false The result of the operation or false on error.
 */
public function save_customer($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'storage_customers';

    $customer_id = isset($data['id']) ? absint($data['id']) : 0;

    // Sanitize all incoming data from the form, including the new fields
    $customer_data = array(
        'full_name'    => isset($data['full_name']) ? sanitize_text_field($data['full_name']) : '',
        'email'        => isset($data['email']) ? sanitize_email($data['email']) : '',
        'phone'        => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
        'whatsapp'     => isset($data['whatsapp']) ? sanitize_text_field($data['whatsapp']) : '',
        'full_address' => isset($data['full_address']) ? sanitize_textarea_field($data['full_address']) : '',
        'upload_id'    => isset($data['upload_id']) ? sanitize_text_field($data['upload_id']) : '',
        'utility_bill' => isset($data['utility_bill']) ? sanitize_text_field($data['utility_bill']) : '',
        'updated_at'   => current_time('mysql', 1)
    );

    if (empty($customer_data['full_name']) || empty($customer_data['email'])) {
        return ['status' => 'error', 'message' => 'Full name and email are required.'];
    }

    if ($customer_id > 0) {
        // Update existing customer
        $result = $wpdb->update($table_name, $customer_data, array('id' => $customer_id));
        if ($result === false) {
            return ['status' => 'error', 'message' => 'Database update failed.'];
        }
        return ['status' => 'success', 'id' => $customer_id];
    } else {
        // Insert new customer
        $customer_data['created_at'] = current_time('mysql', 1);
        $result = $wpdb->insert($table_name, $customer_data);
        if ($result) {
            return ['status' => 'success', 'id' => $wpdb->insert_id];
        } else {
            return ['status' => 'error', 'message' => 'Database insert failed.'];
        }
    }
}
    /** * Delete a customer record.
     * @param int $customer_id ID of the customer to delete.
     * @return int|false Number of rows deleted or false on error.
     */
    public function delete_customer($customer_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storage_customers';
        
        // IMPORTANT: In production, ensure no units/pallets are linked before deletion
        // You should implement a check like: 
        // if ($this->is_customer_linked($customer_id)) return false; 
        
        return $wpdb->delete($table_name, array('id' => $customer_id), array('%d'));
    }
    
public function get_customer_rentals( int $customer_id ): array {
    global $wpdb;
    $customer_id = absint($customer_id);

    $units   = $wpdb->prefix . 'storage_units';
    $pallets = $wpdb->prefix . 'storage_pallets';

    $sql = $wpdb->prepare("
        /* Units */
        (SELECT
            'unit'           AS type,
            u.id             AS id,
            u.unit_name      AS name,
            u.period_from    AS period_from,
            u.period_until   AS period_until,
            u.monthly_price  AS monthly_price,
            u.payment_status AS payment_status,
            u.sqm            AS sqm,          /* size for units */
            NULL             AS pallet_type,
            NULL             AS charged_height
         FROM {$units} u
         WHERE u.customer_id = %d)

        UNION ALL

        /* Pallets */
        (SELECT
            'pallet'         AS type,
            p.id             AS id,
            p.pallet_name    AS name,
            p.period_from    AS period_from,
            p.period_until   AS period_until,
            p.monthly_price  AS monthly_price,
            p.payment_status AS payment_status,
            NULL             AS sqm,          /* no sqm for pallets */
            p.pallet_type    AS pallet_type,
            p.charged_height AS charged_height
         FROM {$pallets} p
         WHERE p.customer_id = %d)

        ORDER BY period_until ASC, type ASC, name ASC
    ", $customer_id, $customer_id);

    $rows = $wpdb->get_results($sql, ARRAY_A);
    return is_array($rows) ? $rows : [];
}

/** Ensure a persistent customer payment token exists and return it */
public function ensure_customer_payment_token( int $customer_id ): string {
    global $wpdb;
    $t = $wpdb->prefix . 'storage_customers';
    $token = $wpdb->get_var($wpdb->prepare("SELECT payment_token FROM {$t} WHERE id=%d", $customer_id));
    if ($token && strlen($token) >= 16) return $token;

    $token = wp_generate_password(32, false, false); // URL-safe
    $wpdb->update($t, ['payment_token' => $token], ['id' => $customer_id], ['%s'], ['%d']);
    return $token;
}

/**
 * Return DISTINCT customer_ids that have at least one rental (unit or pallet)
 * expiring in N days. Optionally limit to unpaid.
 *
 * Uses DATEDIFF to avoid time-of-day issues.
 */
public function get_customers_with_expiring_rentals( int $days, bool $only_unpaid = false ): array {
    global $wpdb;
    $units   = $wpdb->prefix . 'storage_units';
    $pallets = $wpdb->prefix . 'storage_pallets';

    $pay_clause_units   = $only_unpaid ? "AND u.payment_status <> 'paid'" : "";
    $pay_clause_pallets = $only_unpaid ? "AND p.payment_status <> 'paid'" : "";

    // DATEDIFF(x, CURDATE()) = N  → x is exactly N calendar days ahead of "today"
    $sql = $wpdb->prepare("
        SELECT customer_id FROM {$units} u
        WHERE u.customer_id IS NOT NULL
          AND u.is_occupied = 1
          {$pay_clause_units}
          AND DATEDIFF(u.period_until, CURDATE()) = %d

        UNION

        SELECT customer_id FROM {$pallets} p
        WHERE p.customer_id IS NOT NULL
          {$pay_clause_pallets}
          AND DATEDIFF(p.period_until, CURDATE()) = %d
    ", $days, $days);

    $ids = $wpdb->get_col($sql);
    if (!is_array($ids)) return [];
    // DISTINCT is logically enforced by UNION, but de-dupe anyway:
    return array_values(array_unique(array_map('intval', $ids)));
}
        public function has_notification_been_sent( int $customer_id, string $type, string $target_date ): bool {
    global $wpdb;
    $t = $wpdb->prefix . 'sum_notifications_log';
    $exists = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$t} WHERE customer_id=%d AND type=%s AND target_date=%s LIMIT 1",
        $customer_id, $type, $target_date
    ));
    return $exists === 1;
}

public function record_notification_sent( int $customer_id, string $type, string $target_date ): void {
    global $wpdb;
    $t = $wpdb->prefix . 'sum_notifications_log';
    $wpdb->insert($t, [
        'customer_id' => $customer_id,
        'type'        => $type,
        'target_date' => $target_date,
        'sent_at'     => current_time('mysql')
    ], ['%d','%s','%s','%s']);
}


}