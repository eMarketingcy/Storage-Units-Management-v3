<?php
// templates/customer-frontend-page.php

// --- Permission Checks ---
if (!is_user_logged_in()) {
    echo '<div class="sum-frontend-login-required">...Login form here...</div>';
    return;
}
$current_user = wp_get_current_user();
// You should have a function to get settings, assuming it's available in the class context.
$allowed_roles = explode(',', $this->get_setting('allowed_roles', 'administrator,storage_manager'));
$allowed_roles = array_map('trim', $allowed_roles);
$user_has_access = !empty(array_intersect($current_user->roles, $allowed_roles));

if (!$user_has_access) {
    echo '<div class="sum-frontend-access-denied">...Access Denied message here...</div>';
    return;
}
?>

<div id="sum-customer-frontend-container">
    <div class="sum-frontend-main">
        <div class="sum-frontend-header-content">
            <div class="sum-frontend-title-section">
                <div class="sum-frontend-icon">👪</div>
                <div class="sum-frontend-title-text">
                    <h1>Customer Manager</h1>
                    <p>View, edit, and manage all your customers</p>
                </div>
            </div>
             <div class="sum-frontend-user-section">
                    <div class="sum-frontend-user-info">
                        <span class="sum-frontend-user-name"><?php echo esc_html($current_user->display_name); ?></span>
                        <span class="sum-frontend-user-role"><?php echo esc_html(implode(', ', $current_user->roles)); ?></span>
                    </div>
                    <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="sum-frontend-logout-btn">
                        <span class="sum-frontend-logout-icon">🚪</span>
                        Logout
                    </a>
                </div>
        </div>

        <div class="sum-frontend-navigation">
            <a href="<?php echo home_url('/storage-units-manager/'); ?>" class="sum-frontend-nav-item">
                <span class="sum-frontend-nav-icon">📦</span>
                <span>Storage Units</span>
            </a>
            <a href="<?php echo home_url('/storage-pallets-manager/'); ?>" class="sum-frontend-nav-item">
                <span class="sum-frontend-nav-icon">🟠</span>
                <span>Pallet Storage</span>
            </a>
            <div class="sum-frontend-nav-item sum-frontend-nav-active">
                <span class="sum-frontend-nav-icon">👪</span>
                <span>Customers</span>
            </div>
        </div>
        
        <div class="sum-frontend-stats-grid" id="customer-stats-grid">
            </div>

        <div class="sum-frontend-controls">
            <div class="sum-frontend-search-section">
                <input type="text" id="frontend-search-customers" placeholder="Search customers..." class="sum-frontend-search-input">
            </div>
            <div class="sum-frontend-action-group">
                 <div class="sum-frontend-view-toggle">
                    <button type="button" class="sum-frontend-btn sum-view-grid active" id="frontend-view-grid" title="Grid View">▦</button>
                    <button type="button" class="sum-frontend-btn sum-view-list" id="frontend-view-list" title="List View">📋</button>
                </div>
                <button type="button" class="sum-frontend-btn sum-frontend-btn-primary" id="frontend-add-customer-btn">
                    <span>➕</span> Add Customer
                </button>
            </div>
        </div>
        
        <div id="frontend-customers-view-wrapper" class="sum-frontend-view-wrapper sum-view-mode-grid">
            <div id="frontend-customers-grid" class="sum-frontend-grid"></div>
            <div id="frontend-customers-table" class="sum-frontend-table-container" style="display:none;">
                <table class="sum-frontend-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Rentals</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="frontend-customers-table-body"></tbody>
                </table>
            </div>
        </div>

        <div id="frontend-no-customers" class="sum-frontend-empty-state" style="display: none;">
            <h3>No customers found</h3>
            <p>Add your first customer to get started.</p>
        </div>
    </div>
</div>

<div id="frontend-customer-modal" class="sum-frontend-modal" style="display: none;">
    <div class="sum-frontend-modal-overlay"></div>
    <div class="sum-frontend-modal-content">
        <div class="sum-frontend-modal-header">
            <h2 id="frontend-modal-title">Add New Customer</h2>
            <button type="button" class="sum-frontend-modal-close">✕</button>
        </div>
        <form id="frontend-customer-form" class="sum-frontend-form">
            <input type="hidden" id="frontend-customer-id" name="id">
            <div class="sum-frontend-form-grid">
                <div class="sum-frontend-form-group"><label for="frontend-full-name">Full Name *</label><input type="text" id="frontend-full-name" name="full_name" required></div>
                <div class="sum-frontend-form-group"><label for="frontend-email">Email *</label><input type="email" id="frontend-email" name="email" required></div>
                <div class="sum-frontend-form-group"><label for="frontend-phone">Phone</label><input type="tel" id="frontend-phone" name="phone"></div>
                <div class="sum-frontend-form-group"><label for="frontend-whatsapp">WhatsApp</label><input type="tel" id="frontend-whatsapp" name="whatsapp"></div>
            </div>
            <div class="sum-frontend-form-group"><label for="frontend-full-address">Full Address</label><textarea id="frontend-full-address" name="full_address" rows="3"></textarea></div>
            <div class="sum-frontend-form-actions">
                <button type="button" class="sum-frontend-btn sum-frontend-btn-secondary" id="frontend-cancel-btn">Cancel</button>
                <button type="submit" class="sum-frontend-btn sum-frontend-btn-primary">Save Customer</button>
            </div>
        </form>
    </div>
</div>