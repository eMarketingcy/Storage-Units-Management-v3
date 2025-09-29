<?php
/**
 * Frontend template for the Customer Management Page.
 *
 * This template displays the main customer dashboard, including module navigation,
 * key performance indicators (KPIs), action bar, and the customer list/grid.
 *
 * Variables expected to be available (passed by the calling module):
 * @var SUM_Customers_Module_CSSC $module The main customers module instance.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// --- 1. Data Retrieval (Assuming methods exist in your database handler) ---
// This is critical. Ensure your database handler (e.g., SUM_Customers_Database_CSSC)
// implements the required getter methods or this template will fail.
try {
    // Access the database handler via the passed $module instance
    $db = $module->get_db(); 

    // Retrieve KPIs using the methods defined in your database class
    $total_customers    = $db->get_total_customers();
    $total_units_cust   = $db->get_total_units_customers();
    $total_pallets_cust = $db->get_total_pallets_customers();
    $unpaid_cust        = $db->get_customers_with_unpaid_invoices();

} catch (Throwable $e) {
    // Fallback in case of database or module error
    $total_customers = $total_units_cust = $total_pallets_cust = $unpaid_cust = 0;
    // Log the error for debugging
    error_log('[SUM Customer Template Error] ' . $e->getMessage());
}

// --- Variables for styling consistency ---
$accent_color = '#2563eb'; // Blue accent for Customers
?>

<div class="wrap sum-frontend-wrap">
    <h1 class="wp-heading-inline">Customer Dashboard</h1>

    <div class="sum-module-navigation">
        <a href="<?php echo esc_url($units_link); ?>" class="button sum-nav-button">Units Management</a>
        <a href="<?php echo esc_url($pallets_link); ?>" class="button sum-nav-button">Pallets Management</a>
        <a href="<?php echo esc_url($current_link); ?>" class="button sum-nav-button button-primary">Customer Dashboard</a> 
    </div>
    
    <div class="sum-kpi-boxes sum-grid-4">
        
        <div class="sum-kpi-box sum-kpi-total" style="border-left-color: <?php echo $accent_color; ?>;">
            <div class="sum-kpi-icon">
                <span class="dashicons dashicons-businessman" style="color: <?php echo $accent_color; ?>;"></span>
            </div>
            <div class="sum-kpi-label">Total Customers</div>
            <div class="sum-kpi-value"><?php echo number_format_i18n($total_customers); ?></div>
        </div>
        
        <div class="sum-kpi-box sum-kpi-units" style="border-left-color: #667eea;">
            <div class="sum-kpi-icon">
                <span class="dashicons dashicons-store" style="color: #667eea;"></span>
            </div>
            <div class="sum-kpi-label">Units Customers</div>
            <div class="sum-kpi-value"><?php echo number_format_i18n($total_units_cust); ?></div>
        </div>
        
        <div class="sum-kpi-box sum-kpi-pallets" style="border-left-color: #f97316;">
            <div class="sum-kpi-icon">
                <span class="dashicons dashicons-archive" style="color: #f97316;"></span>
            </div>
            <div class="sum-kpi-label">Pallets Customers</div>
            <div class="sum-kpi-value"><?php echo number_format_i18n($total_pallets_cust); ?></div>
        </div>
        
        <div class="sum-kpi-box sum-kpi-unpaid" style="border-left-color: #ef4444;">
            <div class="sum-kpi-icon">
                <span class="dashicons dashicons-warning" style="color: #ef4444;"></span>
            </div>
            <div class="sum-kpi-label">Unpaid Invoices</div>
            <div class="sum-kpi-value"><?php echo number_format_i18n($unpaid_cust); ?></div>
        </div>
    </div>
    
    <div class="sum-action-bar">
        <div class="sum-action-left">
            <div class="sum-action-search">
                <input type="search" id="customer-search" name="s" placeholder="Search by name, email, or ID..." class="regular-text">
                <button class="button button-primary" id="customer-search-button">Search</button>
            </div>
            
            <div class="sum-action-filters">
                <select id="customer-status-filter" name="status-filter">
                    <option value="all">All Statuses</option>
                    <option value="active">Active Rentals</option>
                    <option value="unpaid">Unpaid Invoices</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <div class="sum-action-right">
            <div class="sum-action-view-toggle button-group">
                <button class="button sum-view-grid active" data-view="grid" title="Grid View">
                    <span class="dashicons dashicons-grid-view"></span>
                </button>
                <button class="button sum-view-list" data-view="list" title="List View">
                    <span class="dashicons dashicons-menu"></span>
                </button>
            </div>

            <button class="button button-secondary" id="add-new-customer">
                <span class="dashicons dashicons-plus"></span> Add New Customer
            </button>
        </div>
    </div>
    
    <div class="sum-customer-data-container" id="customer-list-view">
        <p class="sum-no-data-placeholder">Loading customers data...</p>
        
        <?php 
        // Example structure for outputting customer data (Placeholder)
        /*
        if ( ! empty($customer_list) ) {
            foreach ($customer_list as $customer) {
                // Render customer card/row here based on selected view
            }
        } else {
            echo '<p>No customers found matching the criteria.</p>';
        }
        */
        ?>
    </div>
</div>
<script>
(function($){
  const nonce = '<?php echo esc_js( wp_create_nonce('sum_customers_nonce') ); ?>';

  function loadCustomers(q='') {
    $('#sumc-status').text('Loading...');
    $.post(ajaxurl, {action:'sum_customers_get', nonce:nonce, search:q}, function(res){
      $('#sumc-status').text('');
      const $tb = $('#sumc-table tbody').empty();
      if (!res || !res.success || !Array.isArray(res.data) || res.data.length===0) {
        $tb.append('<tr><td colspan="7">No customers found.</td></tr>');
        return;
      }
      res.data.forEach(function(c){
        $tb.append(
          '<tr data-id="'+(c.id||'')+'">'+
          '<td><input class="sumc-name" type="text" value="'+(c.full_name||'')+'"></td>'+
          '<td><input class="sumc-email" type="email" value="'+(c.email||'')+'"></td>'+
          '<td><input class="sumc-phone" type="text" value="'+(c.phone||'')+'"></td>'+
          '<td><input class="sumc-wa" type="text" value="'+(c.whatsapp||'')+'"></td>'+
          '<td>'+(c.source||'')+'</td>'+
          '<td>'+(c.last_seen||'')+'</td>'+
          '<td>'+
            '<button class="button button-primary sumc-save">Save</button> '+
            '<button class="button sumc-del">Delete</button>'+
          '</td>'+
          '</tr>'
        );
      });
    });
  }

  $('#sumc-refresh').on('click', function(e){
    e.preventDefault();
    loadCustomers($('#sumc-search').val());
  });

  $('#sumc-sync').on('click', function(e){
    e.preventDefault();
    $('#sumc-status').text('Syncing...');
    $.post(ajaxurl, {action:'sum_customers_sync', nonce:nonce}, function(res){
      if (res && res.success) {
        $('#sumc-status').text('Synced: +'+(res.data.inserted||0)+' / updated '+(res.data.updated||0));
        loadCustomers($('#sumc-search').val());
      } else {
        $('#sumc-status').text('Sync failed');
      }
    });
  });

  $('#sumc-table').on('click', '.sumc-save', function(){
    const $tr = $(this).closest('tr');
    const id  = $tr.data('id')||'';
    const payload = {
      action: 'sum_customers_save', nonce: nonce,
      id: id,
      full_name: $tr.find('.sumc-name').val(),
      email:     $tr.find('.sumc-email').val(),
      phone:     $tr.find('.sumc-phone').val(),
      whatsapp:  $tr.find('.sumc-wa').val(),
      source:    'manual'
    };
    $('#sumc-status').text('Saving...');
    $.post(ajaxurl, payload, function(res){
      $('#sumc-status').text(res && res.success ? 'Saved' : 'Save failed');
      loadCustomers($('#sumc-search').val());
    });
  });

  $('#sumc-table').on('click', '.sumc-del', function(){
    if (!confirm('Delete this customer?')) return;
    const id = $(this).closest('tr').data('id')||'';
    $('#sumc-status').text('Deleting...');
    $.post(ajaxurl, {action:'sum_customers_del', nonce:nonce, id:id}, function(res){
      $('#sumc-status').text(res && res.success ? 'Deleted' : 'Delete failed');
      loadCustomers($('#sumc-search').val());
    });
  });

  // Initial
  loadCustomers();
})(jQuery);
</script>
