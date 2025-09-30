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

    // --- FIX: Use 'id' from the form, not 'customer_id' ---
    $customer_id = isset($data['id']) ? absint($data['id']) : 0;

    // Sanitize all incoming data from the form
    $customer_data = array(
        'full_name'    => isset($data['full_name']) ? sanitize_text_field($data['full_name']) : '',
        'email'        => isset($data['email']) ? sanitize_email($data['email']) : '',
        'phone'        => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
        'whatsapp'     => isset($data['whatsapp']) ? sanitize_text_field($data['whatsapp']) : '',
        'full_address' => isset($data['full_address']) ? sanitize_textarea_field($data['full_address']) : '',
    );

    // Filter out any fields that weren't submitted to avoid overwriting with blanks
    $customer_data = array_filter($customer_data, function($value) {
        return $value !== null;
    });

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
    
    public function get_customer_rentals($customer_id) {
    global $wpdb;
    $customer_id = absint($customer_id);
    
    $units_table = $wpdb->prefix . 'storage_units';
    $pallets_table = $wpdb->prefix . 'storage_pallets';

    $sql = "
        (SELECT
            'unit' as type,
            id,
            unit_name as name,
            period_from,
            period_until,
            monthly_price,
            payment_status
        FROM {$units_table}
        WHERE customer_id = {$customer_id})
        UNION ALL
        (SELECT
            'pallet' as type,
            id,
            pallet_name as name,
            period_from,
            period_until,
            monthly_price,
            payment_status
        FROM {$pallets_table}
        WHERE customer_id = {$customer_id})
    ";

    return $wpdb->get_results($sql, ARRAY_A);
}

}