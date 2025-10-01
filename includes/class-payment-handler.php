<?php
/**
 * Payment handling for Storage Unit Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class SUM_Payment_Handler {
    
    private $database;
    private $pallet_db; // NEW
    
    public function __construct($database, $pallet_database = null) {
        $this->database = $database;

        // Ensure pallet DB is available
        if ($pallet_database) {
            $this->pallet_db = $pallet_database;
        } else {
            if (!class_exists('SUM_Pallet_Database') && defined('SUM_PLUGIN_PATH')) {
                @require_once SUM_PLUGIN_PATH . 'includes/class-pallet-database.php';
            }
            $this->pallet_db = class_exists('SUM_Pallet_Database') ? new SUM_Pallet_Database() : null;
        }
    }
    
    public function init() {
        // Payment AJAX handlers
        add_action('wp_ajax_sum_process_stripe_payment', array($this, 'process_stripe_payment'));
        add_action('wp_ajax_nopriv_sum_process_stripe_payment', array($this, 'process_stripe_payment'));
        
        // Payment shortcode
        add_shortcode('storage_payment_form', array($this, 'payment_form_shortcode'));
        // Public PDF generator for payment page
        add_action('wp_ajax_sum_generate_invoice_pdf', array($this, 'ajax_generate_invoice_pdf'));
        add_action('wp_ajax_nopriv_sum_generate_invoice_pdf', array($this, 'ajax_generate_invoice_pdf'));
        // Debug: Log that handlers are registered
        error_log('SUM: Payment AJAX handlers registered');
    }
    
public function payment_form_shortcode($atts) {
    // 1. INPUT READING & INITIAL VALIDATION
    $unit_id     = isset($_GET['unit_id'])     ? absint($_GET['unit_id'])     : 0;
    $pallet_id   = isset($_GET['pallet_id'])   ? absint($_GET['pallet_id'])   : 0;
    $customer_id = isset($_GET['customer_id']) ? absint($_GET['customer_id']) : 0;

    $token_raw = isset($_GET['token']) ? wp_unslash($_GET['token']) : '';
    $token     = preg_replace('/[^A-Za-z0-9]/', '', $token_raw);

    // Determine entity type
    $is_customer = ($customer_id && !$unit_id && !$pallet_id);
    $is_pallet   = (!$customer_id && !$unit_id && $pallet_id);
    $entity_id   = $is_customer ? $customer_id : ($is_pallet ? $pallet_id : $unit_id);

    if (!$entity_id || !$token) {
        return '<div class="sum-error">Invalid payment link. Missing entity ID or token.</div>';
    }

    // 2. TOKEN VERIFICATION & DATA LOADING
    $match = false;

    if ($is_customer) {
        // Customer payment flow
        if (!class_exists('SUM_Customer_Database')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-database.php';
        }
        $customer_db = new SUM_Customer_Database();

        // Verify token from customer table
        global $wpdb;
        $db_token = $wpdb->get_var($wpdb->prepare(
            "SELECT payment_token FROM {$wpdb->prefix}storage_customers WHERE id=%d",
            $entity_id
        ));

        if ($db_token) {
            $match = function_exists('hash_equals') ? hash_equals($db_token, $token) : ($db_token === $token);
        }

        if (!$match) {
            return '<div class="sum-error">Invalid or expired payment link. Please request a new invoice.</div>';
        }

        // Load customer data
        $row = $customer_db->get_customer($entity_id);
        if (!$row) {
            return '<div class="sum-error">Customer not found.</div>';
        }

        // Get UNPAID customer rentals for aggregation (same as email)
        $rentals = $customer_db->get_customer_rentals($entity_id, true);

    } else {
        // Unit or Pallet payment flow (existing code)
        $db = $is_pallet ? $this->pallet_db : $this->database;
        $method_name = $is_pallet ? 'get_payment_token' : 'get_unit_payment_token';
        $transient_name = $is_pallet ? "sum_pallet_payment_token_{$entity_id}" : "sum_payment_token_{$entity_id}";

        if ($is_pallet && !$this->pallet_db) {
             return '<div class="sum-error">Invalid payment link (Pallet module missing).</div>';
        }

        $db_token = method_exists($db, $method_name) ? (string) $db->{$method_name}($entity_id) : '';
        $tx_token = (string) get_transient($transient_name);

        if ($db_token) {
            $match = function_exists('hash_equals') ? hash_equals($db_token, $token) : ($db_token === $token);
        }
        if (!$match && $tx_token) {
            $match = function_exists('hash_equals') ? hash_equals($tx_token, $token) : ($tx_token === $token);
        }

        if (!$match) {
            return '<div class="sum-error">Invalid or expired payment link. Please request a new invoice.</div>';
        }

        $row = $is_pallet ? $this->pallet_db->get_pallet($entity_id) : $this->database->get_unit($entity_id);
        if (!$row) {
            return '<div class="sum-error">' . ($is_pallet ? 'Pallet' : 'Unit') . ' not found.</div>';
        }
    }
    
    // Normalize data based on entity type
    if ($is_customer) {
        // Aggregate customer rental data
        $names = array();
        $sizes = array();
        $total_monthly = 0.0;
        $froms = array();
        $untils = array();

        foreach ($rentals as $r) {
            $total_monthly += (float)(isset($r['monthly_price']) ? $r['monthly_price'] : 0);
            $label = $r['name'] ? $r['name'] : ('#' . (isset($r['id']) ? $r['id'] : ''));
            $names[] = $label;

            if ((isset($r['type']) ? $r['type'] : '') === 'unit') {
                if (!empty($r['sqm'])) {
                    $sizes[] = rtrim(rtrim((string)(float)$r['sqm'], '0'), '.') . ' m²';
                } else {
                    $sizes[] = $label;
                }
            } else {
                $pt = $r['pallet_type'] ?: 'Pallet';
                $ht = ($r['charged_height'] !== null && $r['charged_height'] !== '') ? (rtrim(rtrim((string)(float)$r['charged_height'], '0'), '.') . 'm') : '';
                $sizes[] = $ht ? "{$pt} ({$ht})" : $pt;
            }

            if (!empty($r['period_from'])) $froms[] = $r['period_from'];
            if (!empty($r['period_until'])) $untils[] = $r['period_until'];
        }

        $unit_names  = implode(', ', array_filter(array_map('trim', $names))) ?: 'Multiple Units';
        $size_list   = implode(', ', array_filter(array_map('trim', $sizes))) ?: '—';

        $period_from_text = '—';
        $period_until_text = '—';
        if (count($rentals) === 1 && $froms && $untils) {
            $period_from_text  = date_i18n('Y-m-d', strtotime($froms[0]));
            $period_until_text = date_i18n('Y-m-d', strtotime($untils[0]));
        } elseif (count($rentals) > 1) {
            $all_from_same  = $froms && count(array_unique($froms)) === 1;
            $all_until_same = $untils && count(array_unique($untils)) === 1;
            if ($all_from_same && $all_until_same) {
                $period_from_text  = date_i18n('Y-m-d', strtotime($froms[0]));
                $period_until_text = date_i18n('Y-m-d', strtotime($untils[0]));
            } else {
                $period_from_text = $period_until_text = 'Mixed';
            }
        }

        $unit = array(
            'unit_name'            => $unit_names,
            'monthly_price'        => $total_monthly,
            'period_from'          => $period_from_text,
            'period_until'         => $period_until_text,
            'primary_contact_name' => isset($row['full_name']) ? $row['full_name'] : '',
            'id'                   => $entity_id,
            'payment_token'        => $token,
            'is_pallet'            => false,
            'is_customer'          => true,
            'unit_size'            => $size_list,
        );

        $entity_title  = 'Customer Invoice';
        $display_label = 'Customer';

    } else {
        // Unit or Pallet data
        $unit = array(
            'unit_name'            => isset($row['unit_name']) ? $row['unit_name'] : (isset($row['pallet_name']) ? $row['pallet_name'] : ''),
            'monthly_price'        => isset($row['monthly_price']) ? $row['monthly_price'] : '',
            'period_from'          => isset($row['period_from']) ? $row['period_from'] : '',
            'period_until'         => isset($row['period_until']) ? $row['period_until'] : '',
            'primary_contact_name' => isset($row['primary_contact_name']) ? $row['primary_contact_name'] : '',
            'id'                   => $entity_id,
            'payment_token'        => $token,
            'is_pallet'            => $is_pallet,
            'is_customer'          => false,
        );

        $entity_title  = $is_pallet ? 'Pallet Storage Invoice' : 'Storage Unit Invoice';
        $display_label = $is_pallet ? 'Pallet' : 'Unit';
    }

    // 3. CALCULATION LOGIC
    $default_price  = floatval($this->database->get_setting('default_unit_price', 100));
    $monthly_price  = floatval($unit['monthly_price'] ? $unit['monthly_price'] : $default_price);
    $payment_amount = $monthly_price;

    // Calculate billing months for customer AND individual units/pallets
    if ($is_customer) {
        // For customers, calculate total invoice amount from ALL rentals with their billing periods
        if (!function_exists('calculate_billing_months')) {
            @require_once SUM_PLUGIN_PATH . 'includes/class-rental-billing-calculator.php';
        }

        $invoice_total = 0.0;
        foreach ($rentals as $r) {
            $r_monthly = (float)(isset($r['monthly_price']) ? $r['monthly_price'] : 0);
            $r_months = 1;

            if (!empty($r['period_from']) && !empty($r['period_until']) && function_exists('calculate_billing_months')) {
                try {
                    $calc = calculate_billing_months($r['period_from'], $r['period_until'], array('monthly_price' => $r_monthly));
                    $r_months = isset($calc['occupied_months']) ? intval($calc['occupied_months']) : 1;
                    if ($r_months < 1) { $r_months = 1; }
                } catch (Exception $e) {
                    $r_months = 1;
                }
            }
            $invoice_total += $r_monthly * $r_months;
        }
        $payment_amount = $invoice_total;

    } elseif (!empty($unit['period_from']) && !empty($unit['period_until']) && $unit['period_from'] !== 'Mixed') {
        // For individual units/pallets
        if (!function_exists('calculate_billing_months')) {
            @require_once SUM_PLUGIN_PATH . 'includes/class-rental-billing-calculator.php';
        }
        if (function_exists('calculate_billing_months')) {
            try {
                $calc   = calculate_billing_months($unit['period_from'], $unit['period_until'], array('monthly_price' => $monthly_price));
                $months = isset($calc['occupied_months']) ? intval($calc['occupied_months']) : 1;
                if ($months < 1) { $months = 1; }
                $payment_amount = $monthly_price * $months;
            } catch (Exception $e) { /* Fallback to default */ }
        }
    }

    // VAT calculation
    $vat_enabled = $this->database->get_setting('vat_enabled','0') === '1';
    $vat_rate    = floatval( $this->database->get_setting('vat_rate','19') );
    $subtotal    = round( floatval($payment_amount), 2 );
    $vat_amount  = $vat_enabled ? round($subtotal * ($vat_rate/100), 2) : 0.00;
    $total_due   = round($subtotal + $vat_amount, 2);

    // Stripe key check
    $stripe_enabled         = $this->database->get_setting('stripe_enabled', '0');
    $stripe_publishable_key = $this->database->get_setting('stripe_publishable_key', '');
    if (!$stripe_enabled || !$stripe_publishable_key) {
        return '<div class="sum-error">Payment system is not configured. Please contact support.</div>';
    }


    // 4. PREPARE DATA ARRAY (The Presenter's output)
    $data = array(
        'unit'                   => array_map('esc_html', $unit),
        'entity_title'           => esc_html($entity_title),
        'display_label'          => esc_html($display_label),
        'monthly_price'          => number_format($monthly_price, 2),
        'subtotal'               => number_format($subtotal, 2),
        'vat_rate'               => $vat_enabled ? number_format($vat_rate, 2) : '0',
        'vat_amount'             => number_format($vat_amount, 2),
        'total_due'              => number_format($total_due, 2),
        'total_due_raw'          => $subtotal, // SUBTOTAL (without VAT) for JS to calculate with
        'is_pallet'              => $is_pallet,
        'is_customer'            => $is_customer,
        'stripe_publishable_key' => $stripe_publishable_key,
        // Company contact info
        'company_email'          => esc_html($this->database->get_setting('company_email', get_option('admin_email'))),
        'company_phone'          => esc_html($this->database->get_setting('company_phone', '')),
        // AJAX/Security info
        'payment_nonce'          => wp_create_nonce('sum_payment_nonce'),
        'ajax_url'               => admin_url('admin-ajax.php'),
    );

    // 5. TEMPLATE LOADING (Theme Override Implementation)
    $template_name = 'payment-form-template.php';
    
    // Check for theme override (theme-or-child-theme/storage-unit-manager/payment-form-template.php)
    $template_path = locate_template("storage-unit-manager/{$template_name}");

    if (empty($template_path)) {
        // Fallback to the plugin's default template
        $template_path = SUM_PLUGIN_PATH . 'templates/' . $template_name;
    }

    if (!file_exists($template_path)) {
        return '<div class="sum-error">Error: Payment template file not found at path: ' . esc_html($template_path) . '</div>';
    }
    
    // Extract variables into the local scope for template access (best practice for simple view inclusion)
    extract($data);
    
    ob_start();
    include $template_path; // Load the View (HTML/CSS/JS)
    return ob_get_clean();
}

/**
 * Helper to send clean JSON error (removes any buffered output)
 */
private function send_json_error($message) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    wp_send_json_error($message);
}

/**
 * Helper to send clean JSON success (removes any buffered output)
 */
private function send_json_success($data) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    wp_send_json_success($data);
}

public function process_stripe_payment() {
    // Start output buffering to catch any stray output
    if (ob_get_level() === 0) {
        ob_start();
    }

    error_log('SUM Payment: process_stripe_payment() called');
    error_log('SUM Payment: POST data: ' . print_r($_POST, true));

    // Nonce (PHP 5.6 compatible)
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'sum_payment_nonce')) {
        error_log('SUM Payment ERROR: Security check failed');
        $this->send_json_error('Security check failed');
        return;
    }

    // Inputs
    $stripe_token  = isset($_POST['stripe_token'])  ? sanitize_text_field($_POST['stripe_token'])  : '';
    $unit_id       = isset($_POST['unit_id'])       ? absint($_POST['unit_id'])       : 0;
    $pallet_id     = isset($_POST['pallet_id'])     ? absint($_POST['pallet_id'])     : 0;
    $customer_id   = isset($_POST['customer_id'])   ? absint($_POST['customer_id'])   : 0;
    $payment_months = isset($_POST['payment_months']) ? absint($_POST['payment_months']) : 1;
    $payment_token = isset($_POST['payment_token']) ? preg_replace('/[^A-Za-z0-9]/', '', (string)$_POST['payment_token']) : '';
    $amount        = isset($_POST['amount'])        ? intval($_POST['amount'])        : 0; // cents

    error_log("SUM Payment: Parsed - stripe_token={$stripe_token}, unit_id={$unit_id}, pallet_id={$pallet_id}, customer_id={$customer_id}, payment_months={$payment_months}, amount={$amount}");

    // Determine entity type
    $is_customer = ($customer_id && !$unit_id && !$pallet_id);
    $is_pallet   = (!$customer_id && !$unit_id && $pallet_id);
    $entity_id   = $is_customer ? $customer_id : ($is_pallet ? $pallet_id : $unit_id);

    // Validate
    if (empty($stripe_token) || empty($entity_id) || empty($payment_token) || $amount <= 0) {
        $missing = array();
        if (empty($stripe_token))  $missing[] = 'stripe_token';
        if (empty($entity_id))     $missing[] = $is_customer ? 'customer_id' : ($is_pallet ? 'pallet_id' : 'unit_id');
        if (empty($payment_token)) $missing[] = 'payment_token';
        if ($amount <= 0)          $missing[] = 'amount';
        error_log('SUM Payment ERROR: Missing data - ' . implode(', ', $missing));
        $this->send_json_error('Missing required payment information: ' . implode(', ', $missing));
        return;
    }

    error_log("SUM Payment: Validation passed - entity_id={$entity_id}, is_customer=" . ($is_customer ? 'yes' : 'no') . ", is_pallet=" . ($is_pallet ? 'yes' : 'no'));

    // Verify token (DB token primary; transient fallback)
    if ($is_customer) {
        // Customer payment verification
        global $wpdb;
        $db_token = $wpdb->get_var($wpdb->prepare(
            "SELECT payment_token FROM {$wpdb->prefix}storage_customers WHERE id=%d",
            $entity_id
        ));
        $match = false;
        if ($db_token) {
            $match = function_exists('hash_equals') ? hash_equals($db_token, $payment_token) : ($db_token === $payment_token);
        }
        if (!$match) { $this->send_json_error('Invalid payment token'); return; }

    } elseif ($is_pallet) {
        if (!$this->pallet_db) { $this->send_json_error('Invalid payment token'); return; }
        $db_token = method_exists($this->pallet_db, 'get_payment_token') ? (string)$this->pallet_db->get_payment_token($entity_id) : '';
        $tx_token = (string) get_transient("sum_pallet_payment_token_{$entity_id}");
        $match = false;
        if ($db_token) {
            $match = function_exists('hash_equals') ? hash_equals($db_token, $payment_token) : ($db_token === $payment_token);
        }
        if (!$match && $tx_token) {
            $match = function_exists('hash_equals') ? hash_equals($tx_token, $payment_token) : ($tx_token === $payment_token);
        }
        if (!$match) { $this->send_json_error('Invalid payment token'); return; }
    } else {
        $db_token = method_exists($this->database, 'get_unit_payment_token') ? (string)$this->database->get_unit_payment_token($entity_id) : '';
        $tx_token = (string) get_transient("sum_payment_token_{$entity_id}");
        $match = false;
        if ($db_token) {
            $match = function_exists('hash_equals') ? hash_equals($db_token, $payment_token) : ($db_token === $payment_token);
        }
        if (!$match && $tx_token) {
            $match = function_exists('hash_equals') ? hash_equals($tx_token, $payment_token) : ($tx_token === $payment_token);
        }
        if (!$match) { $this->send_json_error('Invalid payment token'); return; }
    }

    // Load record (for metadata)
    if ($is_customer) {
        if (!class_exists('SUM_Customer_Database')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-database.php';
        }
        $customer_db = new SUM_Customer_Database();
        $rec = $customer_db->get_customer($entity_id);
        if (!$rec) { $this->send_json_error('Customer not found'); return; }
        $meta_name = 'Multiple Units';
        $customer  = isset($rec['full_name']) ? $rec['full_name'] : '';
    } elseif ($is_pallet) {
        $rec = $this->pallet_db->get_pallet($entity_id);
        if (!$rec) { $this->send_json_error('Pallet not found'); return; }
        $meta_name = isset($rec['pallet_name']) ? $rec['pallet_name'] : '';
        $customer  = isset($rec['primary_contact_name']) ? $rec['primary_contact_name'] : '';
    } else {
        $rec = $this->database->get_unit($entity_id);
        if (!$rec) { $this->send_json_error('Unit not found'); return; }
        $meta_name = isset($rec['unit_name']) ? $rec['unit_name'] : '';
        $customer  = isset($rec['primary_contact_name']) ? $rec['primary_contact_name'] : '';
    }

    // Stripe secret
    $secret = $this->database->get_setting('stripe_secret_key', '');
    if (!$secret) { $this->send_json_error('Payment system not configured'); return; }

    // Build charge
    if ($is_customer) {
        $charge = array(
            'amount'      => $amount,
            'currency'    => strtolower($this->database->get_setting('currency', 'EUR')),
            'source'      => $stripe_token,
            'description' => 'Customer Storage Payment - ' . $customer,
            'metadata'    => array('customer_id' => $entity_id, 'customer_name' => $customer),
        );
    } elseif ($is_pallet) {
        $charge = array(
            'amount'      => $amount,
            'currency'    => strtolower($this->database->get_setting('currency', 'EUR')),
            'source'      => $stripe_token,
            'description' => 'Pallet Storage Payment - ' . $meta_name,
            'metadata'    => array('pallet_id' => $entity_id, 'name' => $meta_name, 'customer_name' => $customer),
        );
    } else {
        $charge = array(
            'amount'      => $amount,
            'currency'    => strtolower($this->database->get_setting('currency', 'EUR')),
            'source'      => $stripe_token,
            'description' => 'Storage Unit Payment - ' . $meta_name,
            'metadata'    => array('unit_id' => $entity_id, 'unit_name' => $meta_name, 'customer_name' => $customer),
        );
    }

    // Call Stripe
    $resp = wp_remote_post('https://api.stripe.com/v1/charges', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $secret,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ),
        'body'    => http_build_query($charge),
        'timeout' => 30,
    ));

    if (is_wp_error($resp)) { $this->send_json_error('Payment processing failed'); return; }
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    if ($code !== 200) {
        $err = json_decode($body, true);
        $this->send_json_error(isset($err['error']['message']) ? $err['error']['message'] : 'Payment failed'); return;
    }

    $result = json_decode($body, true);
    if (!$result || !isset($result['id']) || (!isset($result['status']) || $result['status'] !== 'succeeded')) {
        error_log('SUM Payment ERROR: Payment not successful - ' . json_encode($result));
        $this->send_json_error('Payment was not successful'); return;
    }

    error_log('SUM Payment: Stripe charge SUCCESS - transaction_id=' . $result['id'] . ', amount=' . ($amount/100));

    // Mark as paid, clean transients, rotate DB token
    global $wpdb;
    error_log('SUM Payment: Starting database updates - entity_id=' . $entity_id);
    if ($is_customer) {
        // For customer payments, mark ONLY UNPAID rentals as paid
        if (!class_exists('SUM_Customer_Database')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-database.php';
        }
        $customer_db = new SUM_Customer_Database();
        $rentals = $customer_db->get_customer_rentals($entity_id, true);

        foreach ($rentals as $rental) {
            if ($rental['type'] === 'pallet') {
                $wpdb->update(
                    $wpdb->prefix . 'storage_pallets',
                    array('payment_status' => 'paid'),
                    array('id' => $rental['id']),
                    array('%s'),
                    array('%d')
                );
            } else {
                $this->database->update_unit_payment_details($rental['id'], 'paid');
            }
        }

        // Rotate customer payment token
        $wpdb->update(
            $wpdb->prefix . 'storage_customers',
            array('payment_token' => wp_generate_password(32, false, false)),
            array('id' => $entity_id),
            array('%s'),
            array('%d')
        );
        $update_result = true;

    } elseif ($is_pallet) {
        $update_result = $wpdb->update(
            $wpdb->prefix . 'storage_pallets',
            array('payment_status' => 'paid'),
            array('id' => $entity_id),
            array('%s'),
            array('%d')
        );
        delete_transient("sum_pallet_payment_token_{$entity_id}");
        // Rotate token
        $wpdb->update(
            $wpdb->prefix . 'storage_pallets',
            array('payment_token' => wp_generate_password(32, false, false)),
            array('id' => $entity_id),
            array('%s'),
            array('%d')
        );
    } else {
        $update_result = $this->database->update_unit_payment_details($entity_id, 'paid');
        delete_transient("sum_payment_token_{$entity_id}");
        error_log('SUM Payment: Updated unit payment status - result=' . ($update_result ? 'success' : 'failed'));
    }

    if ($update_result === false) {
        error_log('SUM Payment ERROR: Database update failed');
        $this->send_json_error('Payment processed but failed to update records'); return;
    }

    error_log('SUM Payment: Payment status updated successfully');

    // Log payment details for debugging
    error_log("SUM Payment Processing: entity_id={$entity_id}, is_customer=" . ($is_customer ? 'yes' : 'no') . ", is_pallet=" . ($is_pallet ? 'yes' : 'no') . ", payment_months={$payment_months}");

    // Extend rental periods for advance payments (ALL payment types)
    if ($payment_months > 1) {
        error_log("SUM Payment: Advance payment detected - extending periods by {$payment_months} months");

        if ($is_customer) {
            // Customer payment - extend all their units/pallets
            if (file_exists(SUM_PLUGIN_PATH . 'includes/class-billing-automation.php')) {
                require_once SUM_PLUGIN_PATH . 'includes/class-billing-automation.php';
                $billing = new SUM_Billing_Automation();
                $billing->update_rental_periods_after_payment($entity_id, $payment_months);
            }
        } elseif ($is_pallet) {
            // Single pallet payment - extend this pallet only
            $pallet = $this->pallet_db->get_pallet($entity_id);
            if ($pallet && !empty($pallet['period_until'])) {
                $current_until = $pallet['period_until'];
                $new_until = date('Y-m-d', strtotime($current_until . ' +' . $payment_months . ' months'));

                $update_result = $wpdb->update(
                    $wpdb->prefix . 'storage_pallets',
                    array('period_until' => $new_until),
                    array('id' => $entity_id),
                    array('%s'),
                    array('%d')
                );

                if ($update_result !== false) {
                    error_log("SUM Payment SUCCESS: Extended pallet {$entity_id} from {$current_until} to {$new_until}");
                } else {
                    error_log("SUM Payment ERROR: Failed to extend pallet {$entity_id} - " . $wpdb->last_error);
                }
            } else {
                error_log("SUM Payment ERROR: Pallet {$entity_id} not found or has no period_until");
            }
        } else {
            // Single unit payment - extend this unit only
            $unit = $this->database->get_unit($entity_id);
            if ($unit && !empty($unit['period_until'])) {
                $current_until = $unit['period_until'];
                $new_until = date('Y-m-d', strtotime($current_until . ' +' . $payment_months . ' months'));

                $update_result = $wpdb->update(
                    $wpdb->prefix . 'storage_units',
                    array('period_until' => $new_until),
                    array('id' => $entity_id),
                    array('%s'),
                    array('%d')
                );

                if ($update_result !== false) {
                    error_log("SUM Payment SUCCESS: Extended unit {$entity_id} from {$current_until} to {$new_until}");
                } else {
                    error_log("SUM Payment ERROR: Failed to extend unit {$entity_id} - " . $wpdb->last_error);
                }
            } else {
                error_log("SUM Payment ERROR: Unit {$entity_id} not found or has no period_until - " . json_encode($unit));
            }
        }
    } else {
        error_log("SUM Payment: Single month payment (payment_months={$payment_months}), no period extension needed");
    }

    // Complete the pending payment history record
    error_log('SUM Payment: Completing payment history for token=' . $payment_token);
    if (!class_exists('SUM_Payment_History')) {
        require_once SUM_PLUGIN_PATH . 'includes/class-payment-history.php';
    }
    $history = new SUM_Payment_History();
    $history_result = $history->complete_payment($payment_token, $result['id'], $amount / 100, $payment_months);
    error_log('SUM Payment: History completed - result=' . ($history_result ? 'success' : 'failed') . ", months={$payment_months}");

    // Send payment confirmation email with receipt
    error_log('SUM Payment: Sending receipt email');
    $this->send_payment_receipt_email($entity_id, $is_customer, $is_pallet, $result, $amount, $payment_months);
    error_log('SUM Payment: Receipt email sent');

    error_log('SUM Payment: COMPLETE - All operations finished successfully');
    $this->send_json_success('Payment processed successfully');
}

/**
 * Record payment in history database
 */
private function record_payment_in_history($entity_id, $is_customer, $is_pallet, $transaction_id, $amount, $payment_months = 1) {
    global $wpdb;

    if (!class_exists('SUM_Payment_History')) {
        require_once SUM_PLUGIN_PATH . 'includes/class-payment-history.php';
    }

    $history = new SUM_Payment_History();
    $currency = strtoupper($this->database->get_setting('currency', 'EUR'));

    // Get customer info and items based on payment type
    $customer_name = '';
    $customer_id_for_history = 0;
    $items_paid = array();

    if ($is_customer) {
        // Customer payment - get all their rentals
        if (!class_exists('SUM_Customer_Database')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-database.php';
        }
        $customer_db = new SUM_Customer_Database();
        $customer = $customer_db->get_customer($entity_id);

        if (!$customer) {
            error_log("SUM Payment History: Customer {$entity_id} not found");
            return false;
        }

        $customer_name = $customer['full_name'];
        $customer_id_for_history = $entity_id;

        // Get FRESH rental data after period extension
        $rentals = $customer_db->get_customer_rentals($entity_id, true);

        foreach ($rentals as $rental) {
            // Get FRESH period_until from database
            if ($rental['type'] === 'pallet') {
                $fresh_until = $wpdb->get_var($wpdb->prepare(
                    "SELECT period_until FROM {$wpdb->prefix}storage_pallets WHERE id = %d",
                    $rental['id']
                ));
            } else {
                $fresh_until = $wpdb->get_var($wpdb->prepare(
                    "SELECT period_until FROM {$wpdb->prefix}storage_units WHERE id = %d",
                    $rental['id']
                ));
            }

            $items_paid[] = array(
                'type' => $rental['type'],
                'name' => isset($rental['name']) ? $rental['name'] : 'Unknown',
                'period_until' => $fresh_until ? $fresh_until : (isset($rental['period_until']) ? $rental['period_until'] : null),
                'monthly_price' => isset($rental['monthly_price']) ? $rental['monthly_price'] : 0
            );
        }

    } elseif ($is_pallet) {
        // Single pallet payment
        $pallet = $this->pallet_db->get_pallet($entity_id);
        if (!$pallet) {
            error_log("SUM Payment History: Pallet {$entity_id} not found");
            return false;
        }

        $customer_name = isset($pallet['primary_contact_name']) ? $pallet['primary_contact_name'] : 'Pallet Customer';
        $customer_id_for_history = 0; // No customer ID for direct pallet payment

        // Get FRESH period_until
        $fresh_until = $wpdb->get_var($wpdb->prepare(
            "SELECT period_until FROM {$wpdb->prefix}storage_pallets WHERE id = %d",
            $entity_id
        ));

        $items_paid[] = array(
            'type' => 'pallet',
            'name' => isset($pallet['pallet_name']) ? $pallet['pallet_name'] : 'Unknown',
            'period_until' => $fresh_until ? $fresh_until : (isset($pallet['period_until']) ? $pallet['period_until'] : null),
            'monthly_price' => isset($pallet['monthly_price']) ? $pallet['monthly_price'] : 0
        );

    } else {
        // Single unit payment
        $unit = $this->database->get_unit($entity_id);
        if (!$unit) {
            error_log("SUM Payment History: Unit {$entity_id} not found");
            return false;
        }

        $customer_name = isset($unit['primary_contact_name']) ? $unit['primary_contact_name'] : 'Unit Customer';
        $customer_id_for_history = 0; // No customer ID for direct unit payment

        // Get FRESH period_until
        $fresh_until = $wpdb->get_var($wpdb->prepare(
            "SELECT period_until FROM {$wpdb->prefix}storage_units WHERE id = %d",
            $entity_id
        ));

        $items_paid[] = array(
            'type' => 'unit',
            'name' => isset($unit['unit_name']) ? $unit['unit_name'] : 'Unknown',
            'period_until' => $fresh_until ? $fresh_until : (isset($unit['period_until']) ? $unit['period_until'] : null),
            'monthly_price' => isset($unit['monthly_price']) ? $unit['monthly_price'] : 0
        );
    }

    error_log("SUM Payment History: Recording payment - customer: {$customer_name}, items: " . count($items_paid) . ", amount: {$currency} {$amount}");

    return $history->record_payment(
        $customer_id_for_history,
        $customer_name,
        $transaction_id,
        $amount,
        $currency,
        $payment_months,
        $items_paid
    );
}

/**
 * Send payment receipt email to customer and admin after successful payment
 */
private function send_payment_receipt_email($entity_id, $is_customer, $is_pallet, $stripe_result, $amount_cents, $payment_months = 1) {
    global $wpdb;

    $amount = $amount_cents / 100;
    $currency = strtoupper($this->database->get_setting('currency', 'EUR'));
    $transaction_id = isset($stripe_result['id']) ? $stripe_result['id'] : '';
    $payment_date = date_i18n('F j, Y g:i A');

    // Get customer info and rentals based on entity type
    if ($is_customer) {
        if (!class_exists('SUM_Customer_Database')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-database.php';
        }
        $customer_db = new SUM_Customer_Database();
        $customer = $customer_db->get_customer($entity_id);
        $rentals = $customer_db->get_customer_rentals($entity_id, true);

        if (!$customer || empty($customer['email'])) return;

        $customer_email = $customer['email'];
        $customer_name = isset($customer['full_name']) ? $customer['full_name'] : 'Customer';
        $entity_type = 'Customer';

    } elseif ($is_pallet) {
        if (!$this->pallet_db) return;
        $pallet = $this->pallet_db->get_pallet($entity_id);
        if (!$pallet || empty($pallet['primary_contact_email'])) return;

        $customer_email = $pallet['primary_contact_email'];
        $customer_name = isset($pallet['primary_contact_name']) ? $pallet['primary_contact_name'] : 'Customer';
        $entity_type = 'Pallet';

        // Get FRESH period_until from database (in case it was just extended)
        $fresh_period_until = $wpdb->get_var($wpdb->prepare(
            "SELECT period_until FROM {$wpdb->prefix}storage_pallets WHERE id = %d",
            $entity_id
        ));

        $rentals = [[
            'type' => 'pallet',
            'name' => $pallet['pallet_name'],
            'monthly_price' => $pallet['monthly_price'],
            'period_from' => isset($pallet['period_from']) ? $pallet['period_from'] : null,
            'period_until' => $fresh_period_until ? $fresh_period_until : (isset($pallet['period_until']) ? $pallet['period_until'] : null)
        ]];

    } else {
        // Fetch unit data (clear any cache first)
        wp_cache_delete($entity_id, 'storage_units');
        $unit = $this->database->get_unit($entity_id);
        if (!$unit || empty($unit['primary_contact_email'])) return;

        $customer_email = $unit['primary_contact_email'];
        $customer_name = isset($unit['primary_contact_name']) ? $unit['primary_contact_name'] : 'Customer';
        $entity_type = 'Unit';

        // Get FRESH period_until from database (in case it was just extended)
        $fresh_period_until = $wpdb->get_var($wpdb->prepare(
            "SELECT period_until FROM {$wpdb->prefix}storage_units WHERE id = %d",
            $entity_id
        ));

        $rentals = [[
            'type' => 'unit',
            'name' => $unit['unit_name'],
            'monthly_price' => $unit['monthly_price'],
            'period_from' => isset($unit['period_from']) ? $unit['period_from'] : null,
            'period_until' => $fresh_period_until ? $fresh_period_until : (isset($unit['period_until']) ? $unit['period_until'] : null)
        ]];
    }

    // Generate receipt PDF
    $pdf_path = $this->generate_receipt_pdf($entity_id, $is_customer, $is_pallet, $rentals, $amount, $currency, $transaction_id, $payment_date, $customer_name, $payment_months);

    // Get email settings
    $company_name = $this->database->get_setting('company_name', 'Self Storage Cyprus');
    $company_email = $this->database->get_setting('company_email', get_option('admin_email'));
    $admin_email = $this->database->get_setting('admin_email', get_option('admin_email'));
    $logo_url = $this->database->get_setting('company_logo', '');

    // Build rentals list for email
    $rentals_html = '<table style="width:100%;border-collapse:collapse;margin:16px 0;">';
    $rentals_html .= '<thead><tr style="background:#f8fafc;"><th style="padding:12px;text-align:left;border-bottom:2px solid #e2e8f0;">Item</th><th style="padding:12px;text-align:right;border-bottom:2px solid #e2e8f0;">Amount</th></tr></thead>';
    $rentals_html .= '<tbody>';

    foreach ($rentals as $rental) {
        $type = isset($rental['type']) ? ucfirst($rental['type']) : 'Item';
        $name = isset($rental['name']) ? $rental['name'] : '';
        $price = isset($rental['monthly_price']) ? number_format((float)$rental['monthly_price'], 2) : '0.00';
        $rentals_html .= '<tr><td style="padding:12px;border-bottom:1px solid #e2e8f0;">' . esc_html($type . ' ' . $name) . '</td><td style="padding:12px;text-align:right;border-bottom:1px solid #e2e8f0;">' . $currency . ' ' . $price . '</td></tr>';
    }

    $rentals_html .= '</tbody>';
    $rentals_html .= '<tfoot><tr><td style="padding:12px;font-weight:bold;border-top:2px solid #e2e8f0;">Total Paid</td><td style="padding:12px;text-align:right;font-weight:bold;border-top:2px solid #e2e8f0;color:#10b981;">' . $currency . ' ' . number_format($amount, 2) . '</td></tr></tfoot>';
    $rentals_html .= '</table>';

    // Logo HTML
    $logo_html = '';
    if ($logo_url) {
        $logo_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($company_name) . '" style="max-width:150px;height:auto;display:block;margin:0 auto 24px;" />';
    }

    // Email subject
    $subject = 'Payment Confirmation - ' . $company_name;

    // Email body
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin:0;padding:0;font-family:Arial,sans-serif;background:#f8fafc;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8fafc;padding:24px 16px;">
            <tr>
                <td align="center">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                        <tr>
                            <td style="padding:32px 24px;text-align:center;background:#10b981;">
                                ' . $logo_html . '
                                <h1 style="margin:0;color:#ffffff;font-size:24px;">Payment Successful!</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:32px 24px;">
                                <p style="margin:0 0 16px;font-size:16px;color:#1e293b;">Dear ' . esc_html($customer_name) . ',</p>
                                <p style="margin:0 0 16px;font-size:16px;color:#1e293b;">Thank you for your payment. We have successfully received your payment and your receipt is attached to this email.</p>

                                <div style="background:#f8fafc;border-radius:8px;padding:16px;margin:24px 0;">
                                    <h2 style="margin:0 0 16px;font-size:18px;color:#1e293b;">Payment Details</h2>
                                    <table style="width:100%;">
                                        <tr>
                                            <td style="padding:8px 0;color:#64748b;">Transaction ID:</td>
                                            <td style="padding:8px 0;text-align:right;color:#1e293b;font-weight:600;">' . esc_html($transaction_id) . '</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 0;color:#64748b;">Payment Date:</td>
                                            <td style="padding:8px 0;text-align:right;color:#1e293b;font-weight:600;">' . esc_html($payment_date) . '</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 0;color:#64748b;">Amount Paid:</td>
                                            <td style="padding:8px 0;text-align:right;color:#10b981;font-weight:700;font-size:20px;">' . $currency . ' ' . number_format($amount, 2) . '</td>
                                        </tr>
                                    </table>
                                </div>

                                <h3 style="margin:24px 0 8px;font-size:16px;color:#1e293b;">Items Paid</h3>
                                ' . $rentals_html;

    // Add advance payment info to email if payment_months > 1
    if ($payment_months > 1 && !empty($rentals)) {
        // Show all items with their new paid-until dates
        $items_html = '';
        foreach ($rentals as $rental) {
            $item_name = isset($rental['name']) ? $rental['name'] : 'Item';
            $item_type = isset($rental['type']) ? ucfirst($rental['type']) : '';
            $paid_until = isset($rental['period_until']) ? date_i18n('F j, Y', strtotime($rental['period_until'])) : '—';
            $items_html .= '<p style="margin:4px 0 0;color:#475569;font-size:14px;">• ' . esc_html($item_type . ' ' . $item_name) . ': <strong>' . esc_html($paid_until) . '</strong></p>';
        }

        $body .= '
                                <div style="background:#e0f2fe;border-left:5px solid #3b82f6;padding:20px;border-radius:8px;margin:24px 0;">
                                    <h3 style="margin:0 0 12px;color:#1e40af;font-size:16px;">📅 Advance Payment Confirmation</h3>
                                    <p style="margin:0 0 8px;color:#1e3a8a;font-size:15px;"><strong>Payment Period:</strong> ' . esc_html($payment_months) . ' month(s)</p>
                                    <p style="margin:0 0 8px;color:#1e3a8a;font-size:15px;"><strong>Items Paid Until:</strong></p>
                                    ' . $items_html . '
                                    <p style="margin:12px 0 0;color:#475569;font-size:14px;">✓ Your rental period has been extended automatically.</p>
                                </div>';
    }

    $body .= '
                                <p style="margin:24px 0 0;font-size:14px;color:#64748b;line-height:1.6;">
                                    If you have any questions about this payment, please contact us at ' . esc_html($company_email) . '.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px;text-align:center;background:#f8fafc;border-top:1px solid #e2e8f0;">
                                <p style="margin:0;font-size:14px;color:#64748b;">' . esc_html($company_name) . '</p>
                                <p style="margin:8px 0 0;font-size:12px;color:#94a3b8;">This is an automated email. Please do not reply.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    // Send email to customer
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $attachments = [];
    if ($pdf_path && file_exists($pdf_path)) {
        $attachments[] = $pdf_path;
    }

    wp_mail($customer_email, $subject, $body, $headers, $attachments);

    // Send copy to admin
    if ($admin_email && strcasecmp($admin_email, $customer_email) !== 0) {
        wp_mail($admin_email, '[Admin Copy] ' . $subject, $body, $headers, $attachments);
    }

    // Cleanup PDF after sending
    if ($pdf_path && file_exists($pdf_path)) {
        $uploads = wp_upload_dir();
        if (!empty($uploads['basedir']) && strpos($pdf_path, $uploads['basedir']) === 0) {
            @unlink($pdf_path);
        }
    }
}

/**
 * Generate receipt PDF for successful payment
 */
private function generate_receipt_pdf($entity_id, $is_customer, $is_pallet, $rentals, $amount, $currency, $transaction_id, $payment_date, $customer_name, $payment_months = 1) {
    // For all entity types, use the comprehensive receipt generator
    return $this->generate_simple_receipt_pdf($entity_id, $is_pallet, $rentals, $amount, $currency, $transaction_id, $payment_date, $customer_name, $payment_months);
}

/**
 * Generate simple receipt PDF for units and pallets
 */
private function generate_simple_receipt_pdf($entity_id, $is_pallet, $rentals, $amount, $currency, $transaction_id, $payment_date, $customer_name, $payment_months = 1) {
    global $wpdb;

    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/receipts';
    if (!file_exists($pdf_dir)) { wp_mkdir_p($pdf_dir); }

    // Generate filename with all items
    $filename_items = array();
    foreach ($rentals as $rental) {
        $type = isset($rental['type']) ? strtolower($rental['type']) : 'item';
        $name = isset($rental['name']) ? preg_replace('/[^a-zA-Z0-9]/', '', $rental['name']) : 'unknown';
        $filename_items[] = $type . $name;
    }
    $items_str = !empty($filename_items) ? implode('-', $filename_items) : 'receipt';
    $pdf_filename = 'receipt-' . $items_str . '-' . date('Y-m-d-H-i-s') . '.pdf';
    $pdf_filepath = trailingslashit($pdf_dir) . $pdf_filename;

    // Get company settings
    $company_name = $this->database->get_setting('company_name', 'Self Storage Cyprus');
    $company_email = $this->database->get_setting('company_email', get_option('admin_email'));
    $company_phone = $this->database->get_setting('company_phone', '');
    $logo_url = $this->database->get_setting('company_logo', '');
    $vat_enabled = ($this->database->get_setting('vat_enabled', '0') === '1');
    $vat_rate = (float)$this->database->get_setting('vat_rate', '0');

    // Load billing calculator
    if (!function_exists('calculate_billing_months')) {
        require_once SUM_PLUGIN_PATH . 'includes/class-rental-billing-calculator.php';
    }

    // Build rentals rows with proper calculations
    $rentals_rows = '';
    $subtotal = 0.0;

    foreach ($rentals as $rental) {
        $type = isset($rental['type']) ? ucfirst($rental['type']) : 'Item';
        $name = isset($rental['name']) ? $rental['name'] : '';
        $monthly_price = isset($rental['monthly_price']) ? (float)$rental['monthly_price'] : 0.0;

        // Calculate billing if period is available
        $period_text = '—';
        $rate_text = $currency . ' ' . number_format($monthly_price, 2) . '/month';
        $line_amount = $monthly_price;

        if (!empty($rental['period_from']) && !empty($rental['period_until'])) {
            try {
                $billing = calculate_billing_months($rental['period_from'], $rental['period_until'], ['monthly_price' => $monthly_price]);
                $line_amount = isset($billing['totals']['prorated_subtotal']) ? $billing['totals']['prorated_subtotal'] : $monthly_price;
                $period_text = date_i18n('M j, Y', strtotime($rental['period_from'])) . ' - ' . date_i18n('M j, Y', strtotime($rental['period_until']));
            } catch (Exception $e) {
                $line_amount = $monthly_price;
                if (!empty($rental['period_from'])) {
                    $period_text = date_i18n('M j, Y', strtotime($rental['period_from']));
                }
            }
        }

        $subtotal += $line_amount;

        $rentals_rows .= '<tr>
            <td style="padding:12px;border-bottom:1px solid #e2e8f0;">' . esc_html($type . ' ' . $name) . '</td>
            <td style="padding:12px;border-bottom:1px solid #e2e8f0;text-align:center;font-size:11px;">' . esc_html($period_text) . '</td>
            <td style="padding:12px;border-bottom:1px solid #e2e8f0;text-align:right;">' . $rate_text . '</td>
            <td style="padding:12px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">' . $currency . ' ' . number_format($line_amount, 2) . '</td>
        </tr>';
    }

    // Calculate VAT and total
    $vat_amount = $vat_enabled ? ($subtotal * ($vat_rate / 100)) : 0.0;
    $total = $subtotal + $vat_amount;

    // Payment history - get previous payments
    $payment_history_rows = '';
    if ($is_pallet) {
        // For pallets - this payment only
        $payment_history_rows = '<tr>
            <td style="padding:12px;border-bottom:1px solid #e2e8f0;">' . esc_html($payment_date) . '</td>
            <td style="padding:12px;border-bottom:1px solid #e2e8f0;">' . esc_html($transaction_id) . '</td>
            <td style="padding:12px;border-bottom:1px solid #e2e8f0;text-align:right;color:#10b981;font-weight:600;">' . $currency . ' ' . number_format($amount, 2) . '</td>
        </tr>';
    } else {
        // For units - this payment only
        $payment_history_rows = '<tr>
            <td style="padding:12px;border-bottom:1px solid #e2e8f0;">' . esc_html($payment_date) . '</td>
            <td style="padding:12px;border-bottom:1px solid #e2e8f0;">' . esc_html($transaction_id) . '</td>
            <td style="padding:12px;border-bottom:1px solid #e2e8f0;text-align:right;color:#10b981;font-weight:600;">' . $currency . ' ' . number_format($amount, 2) . '</td>
        </tr>';
    }

    // Check for remaining balance
    $remaining_balance = 0.0;
    $remaining_items = '';
    // Since we only process unpaid items, there's no remaining balance for this entity after payment

    // Generate HTML
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; color: #1e293b; font-size: 14px; }
            .header { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 3px solid #10b981; }
            .header h1 { margin: 0; color: #10b981; font-size: 28px; }
            .header p { margin: 5px 0; color: #64748b; }
            .receipt-badge { display: inline-block; background: #10b981; color: white; padding: 8px 16px; border-radius: 4px; font-weight: bold; margin: 16px 0; }
            .info-box { background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .info-row { display: flex; justify-content: space-between; padding: 8px 0; }
            .info-label { color: #64748b; }
            .info-value { font-weight: 600; color: #1e293b; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #f8fafc; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; color: #1e293b; font-weight: 600; font-size: 12px; }
            td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
            .subtotal-row { background: #fafafa; }
            .vat-row { background: #fafafa; }
            .total-row { font-weight: bold; font-size: 16px; background: #f8fafc; }
            .total-amount { color: #10b981; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 12px; }
            .section-title { margin: 32px 0 12px; color: #1e293b; font-size: 16px; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="header">
            ' . ($logo_url ? '<img src="' . esc_url($logo_url) . '" alt="Logo" style="max-width:150px;height:auto;margin-bottom:16px;" />' : '') . '
            <h1>' . esc_html($company_name) . '</h1>
            <p>' . esc_html($company_email) . ($company_phone ? ' | ' . esc_html($company_phone) : '') . '</p>
            <div class="receipt-badge">PAYMENT RECEIPT</div>
            <p style="margin-top:16px;font-size:13px;color:#64748b;">Receipt Date: ' . esc_html(date_i18n('F j, Y')) . '</p>
        </div>

        <div class="info-box">
            <h2 style="margin:0 0 16px;color:#1e293b;font-size:18px;">Payment Information</h2>
            <div class="info-row">
                <span class="info-label">Customer:</span>
                <span class="info-value">' . esc_html($customer_name) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Transaction ID:</span>
                <span class="info-value">' . esc_html($transaction_id) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Date:</span>
                <span class="info-value">' . esc_html($payment_date) . '</span>
            </div>
        </div>

        <div style="background:#f8fafc;padding:24px;border-radius:8px;margin:24px 0;text-align:center;">
            <p style="margin:0 0 8px;color:#64748b;font-size:14px;">Total Amount Paid</p>
            <p style="margin:0;color:#10b981;font-size:32px;font-weight:700;">' . $currency . ' ' . number_format($amount, 2) . '</p>
        </div>';

    // Add payment period information if paying for multiple months
    if ($payment_months > 1 && !empty($rentals)) {
        // Show all items with their new paid-until dates
        $items_list = '';
        foreach ($rentals as $rental) {
            $item_name = isset($rental['name']) ? $rental['name'] : 'Item';
            $item_type = isset($rental['type']) ? ucfirst($rental['type']) : '';
            $paid_until = isset($rental['period_until']) ? date_i18n('F j, Y', strtotime($rental['period_until'])) : '—';
            $items_list .= '<p style="margin:4px 0 0;color:#475569;font-size:13px;">• ' . esc_html($item_type . ' ' . $item_name) . ': <strong>' . esc_html($paid_until) . '</strong></p>';
        }

        $html .= '
        <div style="background:#e0f2fe;padding:20px;border-radius:8px;margin:24px 0;border-left:5px solid #3b82f6;">
            <h3 style="margin:0 0 12px;color:#1e40af;font-size:16px;">📅 Advance Payment</h3>
            <p style="margin:0 0 8px;color:#1e3a8a;"><strong>Payment Period:</strong> ' . esc_html($payment_months) . ' month(s)</p>
            <p style="margin:0 0 8px;color:#1e3a8a;"><strong>Items Paid Until:</strong></p>
            ' . $items_list . '
            <p style="margin:12px 0 0;color:#475569;font-size:13px;">✓ Your rental period has been extended automatically.</p>
        </div>';
    }

    $html .= '
        <h3 class="section-title">Payment History</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Transaction ID</th>
                    <th style="text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                ' . $payment_history_rows . '
            </tbody>
        </table>

        <div style="background:#d1fae5;padding:16px;border-radius:8px;margin:24px 0;">
            <p style="margin:0;color:#065f46;font-weight:600;">✓ Status: PAID IN FULL</p>
            <p style="margin:8px 0 0;color:#059669;font-size:13px;">All items on this receipt have been paid. Thank you!</p>
        </div>

        <div class="footer">
            <p>Thank you for your payment!</p>
            <p>This is an official payment receipt from ' . esc_html($company_name) . '</p>
            <p style="margin-top:12px;">For any questions, please contact us at ' . esc_html($company_email) . '</p>
        </div>
    </body>
    </html>';

    // Try to generate PDF using Dompdf
    if (function_exists('sum_load_dompdf') && sum_load_dompdf()) {
        try {
            $opts = new \Dompdf\Options();
            $opts->set('isRemoteEnabled', true);
            $opts->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($opts);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($html);
            $dompdf->render();
            if (false !== file_put_contents($pdf_filepath, $dompdf->output())) {
                return $pdf_filepath;
            }
        } catch (\Throwable $e) {
            error_log('SUM Receipt PDF error: ' . $e->getMessage());
        }
    }

    // Fallback to HTML
    $html_filepath = str_replace('.pdf', '.html', $pdf_filepath);
    if (false !== file_put_contents($html_filepath, $html)) {
        return $html_filepath;
    }

    return null;
}

public function ajax_generate_invoice_pdf() {
    // Nonce
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'sum_payment_nonce')) {
        wp_send_json_error('Security check failed'); return;
    }

    // Inputs
    $unit_id       = isset($_POST['unit_id'])     ? absint($_POST['unit_id'])     : 0;
    $pallet_id     = isset($_POST['pallet_id'])   ? absint($_POST['pallet_id'])   : 0;
    $customer_id   = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
    $payment_token = isset($_POST['payment_token']) ? preg_replace('/[^A-Za-z0-9]/', '', (string)$_POST['payment_token']) : '';

    $is_customer = ($customer_id && !$unit_id && !$pallet_id);
    $is_pallet   = (!$customer_id && !$unit_id && $pallet_id);
    $entity_id   = $is_customer ? $customer_id : ($is_pallet ? $pallet_id : $unit_id);

    if (!$entity_id || !$payment_token) {
        wp_send_json_error('Missing entity id or token'); return;
    }

    // Verify token (same logic as payment)
    if ($is_customer) {
        // Customer PDF generation
        global $wpdb;
        $db_token = $wpdb->get_var($wpdb->prepare(
            "SELECT payment_token FROM {$wpdb->prefix}storage_customers WHERE id=%d",
            $entity_id
        ));
        $match = false;
        if ($db_token) { $match = function_exists('hash_equals') ? hash_equals($db_token, $payment_token) : ($db_token === $payment_token); }
        if (!$match) { wp_send_json_error('Invalid or expired payment link'); return; }

        // Load customer data
        if (!class_exists('SUM_Customer_Database')) {
            require_once SUM_PLUGIN_PATH . 'includes/class-customer-database.php';
        }
        $customer_db = new SUM_Customer_Database();
        $customer = $customer_db->get_customer($entity_id);
        if (!$customer) { wp_send_json_error('Customer not found'); return; }

        // Generate PDF
        if (!class_exists('SUM_Customer_PDF_Generator')) {
            @require_once SUM_PLUGIN_PATH . 'includes/class-customer-pdf-generator.php';
        }
        if (!class_exists('SUM_Customer_PDF_Generator')) { wp_send_json_error('PDF generator not available'); return; }

        $pdf_gen = new SUM_Customer_PDF_Generator($customer_db);
        $pdf_path = $pdf_gen->generate_invoice($entity_id);

    } elseif ($is_pallet) {
        if (!$this->pallet_db) { wp_send_json_error('Invalid payment link'); return; }
        $db_token = method_exists($this->pallet_db, 'get_payment_token') ? (string)$this->pallet_db->get_payment_token($entity_id) : '';
        $tx_token = (string) get_transient("sum_pallet_payment_token_{$entity_id}");
        $match = false;
        if ($db_token) { $match = function_exists('hash_equals') ? hash_equals($db_token, $payment_token) : ($db_token === $payment_token); }
        if (!$match && $tx_token) { $match = function_exists('hash_equals') ? hash_equals($tx_token, $payment_token) : ($tx_token === $payment_token); }
        if (!$match) { wp_send_json_error('Invalid or expired payment link'); return; }

        // Load record
        $rec = $this->pallet_db->get_pallet($entity_id);
        if (!$rec) { wp_send_json_error('Pallet not found'); return; }

        // Generate PDF
        if (!class_exists('SUM_Pallet_PDF_Generator') && defined('SUM_PLUGIN_PATH')) {
            @require_once SUM_PLUGIN_PATH . 'includes/class-pallet-pdf-generator.php';
        }
        if (!class_exists('SUM_Pallet_PDF_Generator')) { wp_send_json_error('PDF generator not available'); return; }
        $gen = new SUM_Pallet_PDF_Generator($this->pallet_db);
        $pdf_path = $gen->generate_invoice_pdf($rec);
    } else {
        $db_token = method_exists($this->database, 'get_unit_payment_token') ? (string)$this->database->get_unit_payment_token($entity_id) : '';
        $tx_token = (string) get_transient("sum_payment_token_{$entity_id}");
        $match = false;
        if ($db_token) { $match = function_exists('hash_equals') ? hash_equals($db_token, $payment_token) : ($db_token === $payment_token); }
        if (!$match && $tx_token) { $match = function_exists('hash_equals') ? hash_equals($tx_token, $payment_token) : ($tx_token === $payment_token); }
        if (!$match) { wp_send_json_error('Invalid or expired payment link'); return; }

        // Load record
        $rec = $this->database->get_unit($entity_id);
        if (!$rec) { wp_send_json_error('Unit not found'); return; }

        // Generate PDF
        if (!class_exists('SUM_PDF_Generator') && defined('SUM_PLUGIN_PATH')) {
            @require_once SUM_PLUGIN_PATH . 'includes/class-pdf-generator.php';
        }
        if (!class_exists('SUM_PDF_Generator')) { wp_send_json_error('PDF generator not available'); return; }
        $gen = new SUM_PDF_Generator($this->database);
        $pdf_path = $gen->generate_invoice_pdf($rec);
    }

    if (!$pdf_path || !file_exists($pdf_path)) {
        wp_send_json_error('Failed to generate PDF'); return;
    }

    // Build URL from path
    $uploads = wp_upload_dir();
    $baseurl = isset($uploads['baseurl']) ? $uploads['baseurl'] : '';
    $basedir = isset($uploads['basedir']) ? $uploads['basedir'] : '';
    $download_url = $baseurl && $basedir ? str_replace($basedir, $baseurl, $pdf_path) : '';

    if (!$download_url) {
        wp_send_json_error('Could not expose PDF URL'); return;
    }

    $resp = array(
        'download_url' => $download_url,
        'filename'     => basename($pdf_path),
        'file_size'    => @filesize($pdf_path),
    );
    wp_send_json_success($resp);
}

}