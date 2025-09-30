<?php
/**
 * Frontend template for Customer Profile View.
 * Placeholder for future dedicated customer profiles.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sum-frontend-container">
    <div class="sum-frontend-main">
        <h1>👤 My Customer Profile</h1>
        <p>This is a dedicated page for the customer to view and potentially manage their personal details.</p>

        <?php 
        // You would typically use the current WordPress user ID to find the linked customer_id 
        // and then fetch the customer details using $this->customer_database->get_customer().
        ?>
        
        <p>Current functionality is focused on Unit and Pallet management. Future integration here can allow customers to update their address or re-upload documents.</p>
        
        <a href="<?php echo home_url('/storage-units-manager/'); ?>" class="sum-frontend-btn sum-frontend-btn-secondary">
            ← Back to Units
        </a>
    </div>
</div>