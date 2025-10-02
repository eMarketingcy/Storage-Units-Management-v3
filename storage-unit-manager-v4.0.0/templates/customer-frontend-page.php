<?php
// Check if user is logged in and has proper role
if (!is_user_logged_in()) {
    echo '<div class="sum-customer-login-required">';
    echo '<div class="sum-customer-login-card">';
    echo '<div class="sum-customer-login-icon">🔐</div>';
    echo '<h2>Login Required</h2>';
    echo '<p>You must be logged in to access the Customer Manager.</p>';
    echo '<a href="' . wp_login_url(get_permalink()) . '" class="sum-customer-btn sum-customer-btn-primary">Login</a>';
    echo '</div>';
    echo '</div>';
    return;
}

$current_user = wp_get_current_user();
$allowed_roles = explode(',', $this->get_setting('allowed_roles', 'administrator,storage_manager'));
$allowed_roles = array_map('trim', $allowed_roles);

$user_has_access = false;
foreach ($allowed_roles as $role) {
    if (in_array($role, $current_user->roles)) {
        $user_has_access = true;
        break;
    }
}

if (!$user_has_access) {
    echo '<div class="sum-customer-access-denied">';
    echo '<div class="sum-customer-access-card">';
    echo '<div class="sum-customer-access-icon">⚠️</div>';
    echo '<h2>Access Denied</h2>';
    echo '<p>You do not have permission to access the Customer Manager.</p>';
    echo '<p class="sum-customer-roles">Required roles: ' . implode(', ', $allowed_roles) . '</p>';
    echo '<p class="sum-customer-roles">Your roles: ' . implode(', ', $current_user->roles) . '</p>';
    echo '</div>';
    echo '</div>';
    return;
}
?>

<div id="sum-customer-frontend-container">
    <!-- Main Interface -->
    <div id="sum-customer-frontend-main" class="sum-customer-frontend-main">
        <!-- Header -->
        <div class="sum-customer-header">
            <div class="sum-customer-header-content">
                <div class="sum-customer-title-section">
                    <div class="sum-customer-icon">👪</div>
                    <div class="sum-customer-title-text">
                        <h1>Customer Management</h1>
                        <p>Manage your customers efficiently</p>
                    </div>
                </div>
                <div class="sum-customer-user-section">
                    <div class="sum-customer-user-info">
                        <span class="sum-customer-user-name"><?php echo esc_html($current_user->display_name); ?></span>
                        <span class="sum-customer-user-role"><?php echo esc_html(implode(', ', $current_user->roles)); ?></span>
                    </div>
                    <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="sum-customer-logout-btn">
                        <span class="sum-customer-logout-icon">🚪</span>
                        Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <div class="sum-customer-navigation">
            <a href="<?php echo home_url('/storage-units-manager/'); ?>" class="sum-customer-nav-item">
                <span class="sum-customer-nav-icon">📦</span>
                <span>Storage Units</span>
            </a>
            <a href="<?php echo home_url('/storage-pallets-manager/'); ?>" class="sum-customer-nav-item">
                <span class="sum-customer-nav-icon">🟠</span>
                <span>Pallet Storage</span>
            </a>
            <div class="sum-customer-nav-item sum-customer-nav-active">
                <span class="sum-customer-nav-icon">👪</span>
                <span>Customers</span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="sum-customer-stats-grid">
            <div class="sum-customer-stat-card sum-customer-stat-total">
                <div class="sum-customer-stat-icon">👪</div>
                <div class="sum-customer-stat-content">
                    <div class="sum-customer-stat-value" id="frontend-total-customers">0</div>
                    <div class="sum-customer-stat-label">Total Customers</div>
                </div>
            </div>
            <div class="sum-customer-stat-card sum-customer-stat-active">
                <div class="sum-customer-stat-icon">✅</div>
                <div class="sum-customer-stat-content">
                    <div class="sum-customer-stat-value" id="frontend-active-customers">0</div>
                    <div class="sum-customer-stat-label">Active</div>
                </div>
            </div>
            <div class="sum-customer-stat-card sum-customer-stat-unpaid">
                <div class="sum-customer-stat-icon">⚠️</div>
                <div class="sum-customer-stat-content">
                    <div class="sum-customer-stat-value" id="frontend-unpaid-customers">0</div>
                    <div class="sum-customer-stat-label">Unpaid</div>
                </div>
            </div>
            <div class="sum-customer-stat-card sum-customer-stat-units">
                <div class="sum-customer-stat-icon">📦</div>
                <div class="sum-customer-stat-content">
                    <div class="sum-customer-stat-value" id="frontend-total-rentals">0</div>
                    <div class="sum-customer-stat-label">Total Rentals</div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="sum-customer-controls">
            <div class="sum-customer-search-bar">
                <input
                    type="search"
                    id="frontend-search-customers"
                    placeholder="Search customers by name, email, phone..."
                    class="sum-customer-search-input"
                />
                <button type="button" class="sum-customer-search-btn" id="frontend-search-btn">
                    🔍
                </button>
            </div>

            <div class="sum-customer-filters">
                <select id="frontend-filter-status" class="sum-customer-filter-select">
                    <option value="all">All Customers</option>
                    <option value="active">Active</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="past">Past Customers</option>
                </select>
            </div>

            <div class="sum-customer-actions">
                <div class="sum-customer-view-toggle">
                    <button
                        type="button"
                        class="sum-customer-view-btn"
                        data-view="grid"
                        title="Grid View"
                    >
                        ▦
                    </button>
                    <button
                        type="button"
                        class="sum-customer-view-btn active"
                        data-view="list"
                        title="List View"
                    >
                        ☰
                    </button>
                </div>
                <button type="button" class="sum-customer-btn sum-customer-btn-primary" id="frontend-add-customer-btn">
                    <span>➕</span> Add Customer
                </button>
            </div>
        </div>

        <!-- Customers List (Default View) -->
        <div id="frontend-customers-list" class="sum-customers-list-view">
            <!-- List will be populated by JavaScript -->
        </div>

        <!-- Empty State -->
        <div id="frontend-empty-customers" class="sum-customer-empty-state" style="display: none;">
            <div class="sum-customer-empty-icon">👤</div>
            <h3>No Customers Yet</h3>
            <p>Get started by adding your first customer</p>
            <button type="button" class="sum-customer-btn sum-customer-btn-primary" id="frontend-add-first-customer-btn">
                <span>➕</span> Add First Customer
            </button>
        </div>

        <!-- Loading State -->
        <div id="frontend-loading-customers" class="sum-customer-loading">
            Loading customers...
        </div>
    </div>
</div>

<!-- Customer Modal -->
<div id="frontend-customer-modal" class="sum-customer-modal" style="display: none;">
    <div class="sum-customer-modal-overlay"></div>
    <div class="sum-customer-modal-content">
        <div class="sum-customer-modal-header">
            <h2 id="frontend-modal-title">Add New Customer</h2>
            <button type="button" class="sum-customer-modal-close">✕</button>
        </div>
        <div class="sum-customer-modal-body">
            <form id="frontend-customer-form">
                <input type="hidden" id="frontend-customer-id" name="id">

                <!-- Primary Details -->
                <div class="sum-customer-form-section">
                    <h3 class="sum-customer-section-title">👤 Customer Details</h3>
                    <div class="sum-customer-form-grid">
                        <div class="sum-customer-form-group">
                            <label for="frontend-full-name">Full Name *</label>
                            <input type="text" id="frontend-full-name" name="full_name" required>
                        </div>
                        <div class="sum-customer-form-group">
                            <label for="frontend-email">Email *</label>
                            <input type="email" id="frontend-email" name="email" required>
                        </div>
                        <div class="sum-customer-form-group">
                            <label for="frontend-phone">Phone</label>
                            <input type="tel" id="frontend-phone" name="phone">
                        </div>
                        <div class="sum-customer-form-group">
                            <label for="frontend-whatsapp">WhatsApp</label>
                            <input type="tel" id="frontend-whatsapp" name="whatsapp">
                        </div>
                    </div>
                    <div class="sum-customer-form-group">
                        <label for="frontend-full-address">Full Address</label>
                        <textarea id="frontend-full-address" name="full_address" rows="2"></textarea>
                    </div>
                </div>

                <!-- Documents -->
                <div class="sum-customer-form-section">
                    <h3 class="sum-customer-section-title">📄 Documentation</h3>
                    <div class="sum-customer-form-grid">
                        <div class="sum-customer-form-group">
                            <label for="frontend-upload-id">ID Document URL</label>
                            <input type="text" id="frontend-upload-id" name="upload_id" placeholder="Paste link to ID...">
                        </div>
                        <div class="sum-customer-form-group">
                            <label for="frontend-utility-bill">Utility Bill URL</label>
                            <input type="text" id="frontend-utility-bill" name="utility_bill" placeholder="Paste link to bill...">
                        </div>
                    </div>
                    <small class="sum-customer-form-help">Upload files to WordPress Media Library and paste URLs here.</small>
                </div>

                <!-- Form Actions -->
                <div class="sum-customer-form-actions">
                    <button type="button" class="sum-customer-btn sum-customer-btn-secondary" id="frontend-cancel-btn">Cancel</button>
                    <button type="submit" class="sum-customer-btn sum-customer-btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Customer Details Modal -->
<div id="frontend-customer-details-modal" class="sum-customer-modal" style="display: none;">
    <div class="sum-customer-modal-overlay"></div>
    <div class="sum-customer-modal-content sum-customer-modal-large">
        <div class="sum-customer-modal-header">
            <h2 id="frontend-details-modal-title">Customer Details</h2>
            <button type="button" class="sum-customer-modal-close">✕</button>
        </div>
        <div class="sum-customer-modal-body">
            <div id="frontend-customer-details-content">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>
