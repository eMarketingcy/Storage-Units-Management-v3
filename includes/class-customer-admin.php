<?php
if ( ! defined('ABSPATH') ) exit;

class SUM_Customer_Admin {

    /** @var SUM_Customer_Database */
    protected $db;

    public function __construct() {
        $this->customer_db = new SUM_Customer_Database();
        
        add_action('admin_menu', array($this, 'add_customer_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_sum_get_customer_list', array($this, 'ajax_get_customer_list'));
        add_action('wp_ajax_sum_delete_customer', array($this, 'ajax_delete_customer'));
        
        // Note: 'sum_save_customer' is assumed to be handled in a central AJAX handler class.
        // If it's not, you would add its registration and handler function here as well.
    }

    public function init() {
        add_action('admin_menu', array($this, 'register_menu'));
    }

    public function register_menu() {
        add_menu_page(
            'Customers', 'Customers', 'manage_options', 'sum-customers',
            array($this, 'render_customers_list'),
            'dashicons-groups', 55
        );
        add_submenu_page(
            'sum-customers', 'All Customers', 'All Customers', 'manage_options', 'sum-customers',
            array($this, 'render_customers_list')
        );
        add_submenu_page(
            'sum-customers', 'Customer Detail', 'Customer Detail', 'manage_options', 'sum-customer-detail',
            array($this, 'render_customer_detail')
        );
    }

    public function render_customer_page() {
        include_once SUM_PATH . 'templates/customers-page.php';
    }
    
     /**
     * Enqueues admin scripts and styles for the customer page.
     */
    public function enqueue_scripts($hook) {
        // Only load on our specific customer page
        if ('toplevel_page_sum-customers' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'sum-customers-admin-js',
            SUM_URL . 'modules/customers/assets/customers-admin.js',
            array('jquery'),
            SUM_VERSION,
            true
        );

        // Pass data to JavaScript
        wp_localize_script('sum-customers-admin-js', 'sum_customer_admin_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('sum_customer_admin_nonce')
        ));
    }
    
    /**
     * AJAX handler to fetch all customers.
     */
    public function ajax_get_customer_list() {
        check_ajax_referer('sum_customer_admin_nonce', 'nonce');
        
        $customers = $this->customer_db->get_all_customers();
        
        if (is_array($customers)) {
            wp_send_json_success($customers);
        } else {
            wp_send_json_error(array('message' => 'Failed to retrieve customers.'));
        }
    }

    /**
     * AJAX handler to delete a customer.
     */
    public function ajax_delete_customer() {
        check_ajax_referer('sum_customer_admin_nonce', 'nonce');

        if (!isset($_POST['customer_id']) || !is_numeric($_POST['customer_id'])) {
            wp_send_json_error(array('message' => 'Invalid customer ID.'));
        }
        
        $customer_id = intval($_POST['customer_id']);
        $result = $this->customer_db->delete_customer($customer_id);

        if ($result) {
            wp_send_json_success(array('message' => 'Customer deleted successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete customer.'));
        }
    }


    public function render_customer_detail() {
        if ( ! current_user_can('manage_options') ) return;
        $id = isset($_GET['customer_id']) ? absint($_GET['customer_id']) : 0;
        if (!$id) { echo '<div class="notice notice-error"><p>Missing customer_id.</p></div>'; return; }

        $c = $this->db->get_customer($id);
        if (!$c) { echo '<div class="notice notice-error"><p>Customer not found.</p></div>'; return; }

        $active    = $this->db->list_assignments($id, true);
        $history   = $this->db->list_assignments($id, false);
        $invoices  = $this->db->list_invoices($id);
        $payments  = $this->db->list_payments($id);
        $balance   = $this->db->get_open_balance($id);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($c['name']); ?></h1>
            <p><strong>Email:</strong> <?php echo esc_html($c['email']); ?> &nbsp;
               <strong>Phone:</strong> <?php echo esc_html($c['phone']); ?> &nbsp;
               <strong>VAT:</strong> <?php echo esc_html($c['vat_number']); ?></p>
            <p><strong>Open Balance:</strong> €<?php echo number_format($balance, 2); ?></p>

            <h2>Active Rentals</h2>
            <?php $this->table_assignments($active); ?>

            <h2>History (Units & Pallets)</h2>
            <?php $this->table_assignments($history); ?>

            <h2>Invoices</h2>
            <?php $this->table_invoices($invoices); ?>

            <h2>Payments</h2>
            <?php $this->table_payments($payments); ?>
        </div>
        <?php
    }

    protected function table_assignments($rows) {
        ?>
        <table class="widefat striped">
            <thead><tr>
                <th>Type</th><th>Name</th><th>Period</th><th>Monthly</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo esc_html(ucfirst($r['entity_type'])); ?></td>
                    <td><?php echo esc_html($r['entity_name']); ?></td>
                    <td><?php echo esc_html($r['period_from']); ?> → <?php echo esc_html($r['period_until']); ?></td>
                    <td>€<?php echo number_format($r['monthly_price'], 2); ?></td>
                    <td><?php echo esc_html($r['status']); ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5">No records.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    protected function table_invoices($rows) {
        ?>
        <table class="widefat striped">
            <thead><tr>
                <th>No.</th><th>Type</th><th>Name</th><th>Period</th><th>Status</th><th>Total</th>
            </tr></thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['invoice_no']); ?></td>
                    <td><?php echo esc_html(ucfirst($r['entity_type'])); ?></td>
                    <td><?php echo esc_html($r['entity_name']); ?></td>
                    <td><?php echo esc_html($r['period_from']); ?> → <?php echo esc_html($r['period_until']); ?></td>
                    <td><?php echo esc_html($r['status']); ?></td>
                    <td>€<?php echo number_format($r['total'], 2); ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6">No invoices.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    protected function table_payments($rows) {
        ?>
        <table class="widefat striped">
            <thead><tr>
                <th>Date</th><th>Type</th><th>Name</th><th>Method</th><th>Amount</th>
            </tr></thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['paid_at']); ?></td>
                    <td><?php echo esc_html(ucfirst($r['entity_type'])); ?></td>
                    <td><?php echo esc_html($r['entity_name']); ?></td>
                    <td><?php echo esc_html($r['method']); ?></td>
                    <td>€<?php echo number_format($r['amount'], 2); ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5">No payments.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
}

new SUM_Customer_Admin();