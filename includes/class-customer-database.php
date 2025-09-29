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

    /** * Insert or update a customer record.
     * @param array $data Customer data array.
     * @return int|false The ID of the inserted/updated customer or false on error.
     */
    public function save_customer($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storage_customers';

        $customer_id = intval($data['customer_id'] ?? 0);

        $customer_data = array(
            'full_name'    => sanitize_text_field($data['full_name']),
            'full_address' => sanitize_textarea_field($data['full_address'] ?? ''), 
            'phone'        => sanitize_text_field($data['phone'] ?? ''),
            'whatsapp'     => sanitize_text_field($data['whatsapp'] ?? ''),
            'email'        => sanitize_email($data['email']),
            'upload_id'    => sanitize_text_field($data['upload_id'] ?? ''), 
            'utility_bill' => sanitize_text_field($data['utility_bill'] ?? ''), 
            'updated_at'   => current_time('mysql', 1)
        );

        // Data format: (full_name, full_address, phone, whatsapp, email, upload_id, utility_bill, updated_at)
        $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');

        if ($customer_id > 0) {
            // Update existing customer
            $result = $wpdb->update($table_name, $customer_data, array('id' => $customer_id), $format, array('%d'));
            return $result === false ? false : $customer_id;
        } else {
            // Insert new customer
            $customer_data['created_at'] = current_time('mysql', 1);
            $format[] = '%s'; // Add format for created_at
            
            $result = $wpdb->insert($table_name, $customer_data, $format);
            return $result ? $wpdb->insert_id : false;
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

}