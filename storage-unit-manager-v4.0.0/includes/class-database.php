<?php
/**
 * Database operations for Storage Unit Manager
 */
if (!defined('ABSPATH')) {
    exit;
}

class SUM_Database {

    public function __construct() {
        // Make sure schema and default settings exist even if activation hook didn't run
        $this->ensure_schema();
        $this->ensure_vat_settings();
    }

   /** Create (or update) tables on demand */
    private function ensure_schema() {
        global $wpdb;

        // Ensure settings table exists (safe to call anytime)
        $charset_collate = $wpdb->get_charset_collate();
        $settings_table  = $wpdb->prefix . 'storage_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($settings_sql);

        // Ensure storage_units has payment_token and customer_id columns
        $units_table = $wpdb->prefix . 'storage_units';
        // If table doesn't exist yet, create it (including new columns via create_tables)
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME, $units_table
        ) );
        if (!$table_exists) {
            // This call will trigger the creation of all tables, including the customers table 
            // if we update create_tables in class-database.php, which we already did in Step 1.
            $this->create_tables(); 
        } else {
            // Check for and add payment_token
            $col_payment_token = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM $units_table LIKE %s", 'payment_token') );
            if (!$col_payment_token) {
                $wpdb->query("ALTER TABLE $units_table ADD COLUMN payment_token varchar(64) DEFAULT NULL AFTER primary_contact_email");
            }
            
            // Check for and add customer_id
            $col_customer_id = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM $units_table LIKE %s", 'customer_id') );
            if (!$col_customer_id) {
                $wpdb->query("ALTER TABLE $units_table ADD COLUMN customer_id mediumint(9) DEFAULT NULL AFTER is_occupied");
            }
        }
    }
    
    /** Ensure VAT keys exist with sane defaults */
    private function ensure_vat_settings() {
        $this->ensure_setting_exists('vat_enabled', '0');  // off by default
        $this->ensure_setting_exists('vat_rate',    '19'); // 19%
        $this->ensure_setting_exists('company_vat', '');   // empty
    }

    private function ensure_setting_exists($key, $default) {
        global $wpdb;
        $table = $wpdb->prefix . 'storage_settings';
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE setting_key = %s", $key)
        );
        if (!$exists) {
            $wpdb->insert($table, array(
                'setting_key'   => $key,
                'setting_value' => $default,
            ), array('%s','%s'));
        }
    }

    // --- Your original methods (kept), with storage_units now including payment_token in create_tables() ---

public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Storage units table (UPDATED: Added customer_id, REMOVED primary_contact_* fields)
        $table_name = $wpdb->prefix . 'storage_units';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            unit_name varchar(100) NOT NULL,
            size varchar(100) DEFAULT '',
            sqm decimal(10,2) DEFAULT NULL,
            monthly_price decimal(10,2) DEFAULT NULL,
            website_name varchar(200) DEFAULT '',
            is_occupied tinyint(1) DEFAULT 0,
            customer_id mediumint(9) DEFAULT NULL, // NEW: Foreign key to customer table
            period_from date DEFAULT NULL,
            period_until date DEFAULT NULL,
            payment_status varchar(20) DEFAULT 'paid',
            primary_contact_name varchar(200) DEFAULT '', // NOTE: Retained existing for dbDelta/backward compat in older version definitions, but will be ignored for new CRUD operations.
            primary_contact_phone varchar(50) DEFAULT '', // Retained fields are removed in the logic of save_unit in next step.
            primary_contact_whatsapp varchar(50) DEFAULT '',
            primary_contact_email varchar(200) DEFAULT '',
            payment_token varchar(64) DEFAULT NULL,
            secondary_contact_name varchar(200) DEFAULT '',
            secondary_contact_phone varchar(50) DEFAULT '',
            secondary_contact_whatsapp varchar(50) DEFAULT '',
            secondary_contact_email varchar(200) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unit_name (unit_name)
        ) $charset_collate;";
        // The above SQL keeps the old fields for dbDelta's sanity, but the functional logic will shift to customer_id.

        // --- NEW: Customer table schema ---
        $customers_table = $wpdb->prefix . 'storage_customers';
        $customers_sql = "CREATE TABLE $customers_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            full_name varchar(200) NOT NULL,
            full_address text DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            whatsapp varchar(50) DEFAULT NULL,
            email varchar(200) NOT NULL,
            upload_id varchar(255) DEFAULT NULL,
            utility_bill varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) $charset_collate;";
        // ----------------------------------

        // Settings table
        $settings_table = $wpdb->prefix . 'storage_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        // --- NEW: Notification Log Table ---
    $log_table = $wpdb->prefix . 'sum_notifications_log';
    $log_sql = "CREATE TABLE {$log_table} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        customer_id bigint unsigned NOT NULL,
        type varchar(20) NOT NULL,
        target_date date NOT NULL,
        sent_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_customer_type_date (customer_id, type, target_date)
    ) {$charset_collate};";
    // --- END NEW ---

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql); // For storage_units
        dbDelta($customers_sql); // For storage_customers (NEW)
        dbDelta($settings_sql); // For storage_settings
        dbDelta($log_sql); // Run dbDelta for the new log table

        // Also ensure VAT keys after (in case create_tables is called later)
        $this->ensure_vat_settings();
    }    
public function get_units($filter = 'all') {
        global $wpdb;
        $units_table = $wpdb->prefix . 'storage_units';
        $customers_table = $wpdb->prefix . 'storage_customers';

        // Select all unit columns (u.*) and selected customer columns (c.column AS alias)
        $select = "u.*, c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.whatsapp AS customer_whatsapp";
        $join = "LEFT JOIN $customers_table c ON u.customer_id = c.id";

        $where_clause = '';
        if ($filter === 'occupied') {
            $where_clause = 'WHERE u.is_occupied = 1';
        } elseif ($filter === 'available') {
            $where_clause = 'WHERE u.is_occupied = 0';
        } elseif ($filter === 'past_due') {
            $where_clause = 'WHERE u.is_occupied = 1 AND u.period_until < CURDATE()';
        } elseif ($filter === 'unpaid') {
            $where_clause = 'WHERE u.is_occupied = 1 AND u.payment_status != "paid"';
        }

        // Use aliases in the query
        $sql = "SELECT $select FROM $units_table u $join $where_clause ORDER BY u.unit_name";

        return $wpdb->get_results($sql, ARRAY_A);
    }
    public function get_unit($unit_id) {
        global $wpdb;
        $units_table = $wpdb->prefix . 'storage_units';
        $customers_table = $wpdb->prefix . 'storage_customers';
        
         $sql = $wpdb->prepare(
        "SELECT p.*, c.full_name, c.email, c.phone, c.whatsapp, c.full_address
         FROM {$units_table} p
         LEFT JOIN {$customers_table} c ON p.customer_id = c.id
         WHERE p.id = %d",
        (int)$unit_id
    );

    return $wpdb->get_row($sql, ARRAY_A);
    
        //return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $unit_id), ARRAY_A);
    }
    
    public function get_unit_for_pdf($unit_id) {
        // This new function simply calls your existing get_unit method,
        // which already contains the correct logic to join customer data.
        return $this->get_unit($unit_id);
    }

    public function get_unit_payment_token($unit_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'storage_units';
        return (string) $wpdb->get_var($wpdb->prepare("SELECT payment_token FROM $table WHERE id=%d", (int)$unit_id));
    }

    public function ensure_unit_payment_token($unit_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'storage_units';
        $tok = $this->get_unit_payment_token($unit_id);
        if (!empty($tok)) return $tok;
        $tok = wp_generate_password(32, false, false);
        $wpdb->update($table, ['payment_token'=>$tok], ['id'=>(int)$unit_id], ['%s'], ['%d']);
        return $tok;
    }

// in /includes/class-database.php

public function save_unit($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'storage_units';

    $unit_id = intval($data['unit_id'] ?? 0);

    $unit_data = array(
        'unit_name' => sanitize_text_field($data['unit_name']),
        'size' => sanitize_text_field($data['size'] ?? ''),
        'sqm' => floatval($data['sqm'] ?? 0) ?: null,
        'monthly_price' => floatval($data['monthly_price'] ?? 0) ?: null,
        'website_name' => sanitize_text_field($data['website_name'] ?? ''),
        'is_occupied' => intval($data['is_occupied'] ?? 0),
        'customer_id' => intval($data['customer_id'] ?? 0) ?: null,
        'period_from' => sanitize_text_field($data['period_from'] ?? '') ?: null,
        'period_until' => sanitize_text_field($data['period_until'] ?? '') ?: null,
        'payment_status' => sanitize_text_field($data['payment_status'] ?? 'paid'),
        
        // --- FIX: Added primary contact fields back into the save array ---
        'primary_contact_name' => sanitize_text_field($data['primary_contact_name'] ?? ''),
        'primary_contact_phone' => sanitize_text_field($data['primary_contact_phone'] ?? ''),
        'primary_contact_whatsapp' => sanitize_text_field($data['primary_contact_whatsapp'] ?? ''),
        'primary_contact_email' => sanitize_email($data['primary_contact_email'] ?? ''),
        
        'secondary_contact_name' => sanitize_text_field($data['secondary_contact_name'] ?? ''),
        'secondary_contact_phone' => sanitize_text_field($data['secondary_contact_phone'] ?? ''),
        'secondary_contact_whatsapp' => sanitize_text_field($data['secondary_contact_whatsapp'] ?? ''),
        'secondary_contact_email' => sanitize_email($data['secondary_contact_email'] ?? '')
    );

    // --- FIX: Corrected the format array to match all 18 fields ---
    $format = array(
        '%s', '%s', '%f', '%f', '%s', '%d', '%d', '%s', 
        '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
        '%s', '%s'
    );
    
    if ($unit_id > 0) {
        // Existing unit update
        return $wpdb->update($table_name, $unit_data, array('id' => $unit_id), $format, array('%d'));
    } else {
        // New unit insert
        return $wpdb->insert($table_name, $unit_data, $format);
    }
}

public function delete_unit($unit_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storage_units';
        return $wpdb->delete($table_name, array('id' => $unit_id), array('%d'));
    }
    
     /**
     * Dedicated function to update a unit's payment status, last paid date, and rotate token.
     * @param int $unit_id ID of the unit.
     * @param string $status New payment status (e.g., 'paid').
     * @return int|false Number of rows updated or false on error.
     */
    public function update_unit_payment_details(int $unit_id, string $status = 'paid') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storage_units';

        // Rotate token for security and subsequent payment links
        $new_token = wp_generate_password(32, false, false);
        
        $data = array(
            'payment_status' => sanitize_text_field($status),
            'updated_at'     => current_time('mysql', 1), // Use UTC timestamp
            'payment_token'  => $new_token,
            // Optional: Set a flag or update period_from/until if your business logic requires it here
        );
        
        $where = array('id' => $unit_id);

        return $wpdb->update($table_name, $data, $where, array('%s', '%s', '%s'), array('%d'));
    }
    
    public function toggle_occupancy($unit_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storage_units';
        $current_status = $wpdb->get_var($wpdb->prepare("SELECT is_occupied FROM $table_name WHERE id = %d", $unit_id));
        $new_status = $current_status ? 0 : 1;

        return $wpdb->update(
            $table_name,
            array('is_occupied' => $new_status),
            array('id' => $unit_id),
            array('%d'),
            array('%d')
        );
    }

    public function bulk_add_units($prefix, $start_number, $end_number, $bulk_data = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storage_units';

        $created_count = 0;
        $errors = array();

        for ($i = $start_number; $i <= $end_number; $i++) {
            $unit_name = $prefix . $i;

            $data = array(
                'unit_name' => $unit_name,
                'size' => sanitize_text_field($bulk_data['bulk_size'] ?? ''),
                'sqm' => floatval($bulk_data['bulk_sqm'] ?? 0) ?: null,
                'monthly_price' => floatval($bulk_data['bulk_price'] ?? 0) ?: null,
                'is_occupied' => 0,
                'payment_status' => 'paid'
            );

            $format = array('%s', '%s', '%f', '%f', '%d', '%s');
            $result = $wpdb->insert($table_name, $data, $format);

            if ($result !== false) {
                $created_count++;
            } else {
                $errors[] = "Failed to create unit $unit_name";
            }
        }

        return array('created' => $created_count, 'errors' => $errors);
    }

    public function get_setting($key, $default = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storage_settings';
        $value = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $table_name WHERE setting_key = %s", $key));
        return $value !== null ? $value : $default;
    }

    public function save_setting($key, $value) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storage_settings';
        return $wpdb->replace(
            $table_name,
            array('setting_key' => $key, 'setting_value' => maybe_serialize($value)),
            array('%s', '%s')
        );
    }

    
    /**
 * units that still belong to a customer and whose period ended before today.
 */
public function get_expired_but_occupied_units(): array {
    global $wpdb;
    $t = $wpdb->prefix . 'storage_units';
    // If you track a status column, add AND status='active'
    $rows = $wpdb->get_results("
        SELECT * FROM {$t}
        WHERE customer_id IS NOT NULL
          AND period_until < CURDATE()
    ", ARRAY_A);
    return is_array($rows) ? $rows : [];
}

/**
 * Renew a pallet for the next month (inclusive period).
 * New period: (period_from = old_until + 1 day),
 *             (period_until = period_from + 1 month - 1 day)
 */
public function renew_pallet_for_next_period( int $pallet_id ) {
    global $wpdb;
    $t = $wpdb->prefix . 'storage_units';

    $current_until = $wpdb->get_var($wpdb->prepare("SELECT period_until FROM {$t} WHERE id = %d", $pallet_id));
    if (!$current_until) return false;

    try {
        $from = new DateTime($current_until, wp_timezone()); // ‘Y-m-d’
        $from->modify('+1 day');

        $until = clone $from;
        $until->modify('+1 month')->modify('-1 day'); // inclusive end

        return $wpdb->update(
            $t,
            [
                'period_from'    => $from->format('Y-m-d'),
                'period_until'   => $until->format('Y-m-d'),
                'payment_status' => 'unpaid',
            ],
            ['id' => $pallet_id],
            ['%s','%s','%s'],
            ['%d']
        );
    } catch (\Throwable $e) {
        error_log('SUM Pallet Renew error: ' . $e->getMessage());
        return false;
    }
}

    
    // --- New Customer Management Methods (Step 3) ---

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
            'upload_id'    => sanitize_text_field($data['upload_id'] ?? ''), // Storing ID upload path/reference
            'utility_bill' => sanitize_text_field($data['utility_bill'] ?? ''), // Storing utility bill path/reference
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
        
        // NOTE: In a production environment, you should add a check here 
        // to ensure no units or units are still linked to this customer
        // before performing the deletion (e.g., SELECT COUNT(*) FROM storage_units WHERE customer_id = %d).
        
        return $wpdb->delete($table_name, array('id' => $customer_id), array('%d'));
    }

    // -------------------------------------------------

} // Closes the SUM_Database class