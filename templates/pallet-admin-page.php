<div class="wrap">
    <h1>Pallet Storage Manager</h1>
    <p>Manage your pallet storage at selfstorage.cy</p>
    
    <!-- Stats Cards -->
    <div class="sum-stats-grid">
        <div class="sum-stat-card">
            <div class="sum-stat-icon sum-stat-total">🟠</div>
            <div class="sum-stat-content">
                <div class="sum-stat-label">Total Pallets</div>
                <div class="sum-stat-value" id="total-pallets">0</div>
            </div>
        </div>
        <div class="sum-stat-card">
            <div class="sum-stat-icon sum-stat-unpaid">⚠️</div>
            <div class="sum-stat-content">
                <div class="sum-stat-label">Unpaid</div>
                <div class="sum-stat-value" id="unpaid-pallets">0</div>
            </div>
        </div>
        <div class="sum-stat-card">
            <div class="sum-stat-icon sum-stat-available">🇪🇺</div>
            <div class="sum-stat-content">
                <div class="sum-stat-label">EU Pallets</div>
                <div class="sum-stat-value" id="eu-pallets">0</div>
            </div>
        </div>
        <div class="sum-stat-card">
            <div class="sum-stat-icon sum-stat-occupied">🇺🇸</div>
            <div class="sum-stat-content">
                <div class="sum-stat-label">US Pallets</div>
                <div class="sum-stat-value" id="us-pallets">0</div>
            </div>
        </div>
    </div>
    
    <!-- Controls -->
    <div class="sum-controls">
        <div class="sum-search-filter">
            <input type="text" id="search-pallets" placeholder="Search pallets..." class="sum-search-input">
            <select id="filter-status" class="sum-filter-select">
                <option value="all">All Pallets</option>
                <option value="eu">EU Pallets</option>
                <option value="us">US Pallets</option>
                <option value="past_due">Past Due</option>
                <option value="unpaid">Unpaid</option>
            </select>
        </div>
        <div class="sum-action-buttons">
            <button type="button" class="button button-primary" id="add-pallet-btn">
                <span class="dashicons dashicons-plus-alt"></span> Add Pallet
            </button>
        </div>
    </div>
    
    <!-- Pallets Grid -->
    <div id="pallets-grid" class="sum-units-grid">
        <!-- Pallets will be loaded here via JavaScript -->
    </div>
    
    <!-- No Pallets Message -->
    <div id="no-pallets-message" class="sum-no-units" style="display: none;">
        <div class="sum-no-units-icon">🟠</div>
        <h3>No pallets found</h3>
        <p>Get started by creating your first pallet storage.</p>
        <button type="button" class="button button-primary" id="add-first-pallet-btn">
            <span class="dashicons dashicons-plus-alt"></span> Add Your First Pallet
        </button>
    </div>
</div>

<!-- Pallet Form Modal -->
<div id="pallet-modal" class="sum-modal" style="display: none;">
    <div class="sum-modal-content">
        <div class="sum-modal-header">
            <h2 id="modal-title">Add New Pallet Storage</h2>
            <button type="button" class="sum-modal-close">&times;</button>
        </div>
        
        <form id="pallet-form" class="sum-form">
            <input type="hidden" id="pallet-id" name="pallet_id">
            
            <div class="sum-form-grid">
                <div class="sum-form-group">
                    <label for="pallet-name">Pallet Name *</label>
                    <input type="text" id="pallet-name" name="pallet_name" required placeholder="e.g., JD1, AB2">
                </div>
                
                <div class="sum-form-group">
                    <label for="pallet-type">Pallet Type *</label>
                    <select id="pallet-type" name="pallet_type" required>
                        <option value="EU">EU Pallet (1.20m × 0.80m)</option>
                        <option value="US">US Pallet (1.22m × 1.02m)</option>
                    </select>
                </div>
                
                <div class="sum-form-group">
                    <label for="actual-height">Actual Height (m) *</label>
                    <input type="number" id="actual-height" name="actual_height" step="0.01" min="0" max="2" required placeholder="e.g., 1.15">
                    <small>Price will be calculated based on charged height tier</small>
                </div>
                
                <div class="sum-form-group">
                    <label for="period-from">Period From</label>
                    <input type="date" id="period-from" name="period_from">
                </div>
                
                <div class="sum-form-group">
                    <label for="period-until">Period Until</label>
                    <input type="date" id="period-until" name="period_until">
                </div>
                
                <div class="sum-form-group">
                    <label for="payment-status">Payment Status</label>
                    <select id="payment-status" name="payment_status">
                        <option value="paid">Paid</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
            </div>
            
            <div class="sum-pallet-form-section">
                <h3>👤 Primary Customer Link</h3>
                
                <div class="sum-form-group-flex">
                    <div class="sum-form-group">
                        <label for="customer-id">Select Existing Customer *</label>
                        <select id="customer-id" name="customer_id" class="sum-select-customer" required>
                            <option value="">-- Select Customer --</option>
                            <?php 
                            // Ensure $customer_database is available (it is, due to step 1)
                            if (isset($customer_database)) {
                                $customers = $customer_database->get_customers();
                                foreach ($customers as $customer) {
                                    printf(
                                        '<option value="%d" data-email="%s">%s (ID: %d | %s)</option>',
                                        esc_attr($customer['id']),
                                        esc_attr($customer['email']),
                                        esc_html($customer['full_name']),
                                        esc_attr($customer['id']),
                                        esc_html($customer['email'])
                                    );
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <button type="button" class="button button-secondary" id="create-customer-btn-pallet" style="align-self: flex-end; margin-bottom: 3px;">
                        <span class="dashicons dashicons-plus"></span> New Customer
                    </button>
                </div>
                <p class="description">This customer will be linked to the pallet record.</p>
            </div>
            
            <div class="sum-secondary-toggle">
                <label>
                    <input type="checkbox" id="has-secondary-contact"> Add Secondary Contact
                </label>
            </div>
            
            <div id="secondary-contact-section" class="sum-contact-section" style="display: none;">
                <h3>👥 Secondary Contact</h3>
                <div class="sum-form-grid">
                    <input type="text" id="secondary-name" name="secondary_contact_name" placeholder="Full Name">
                    <input type="tel" id="secondary-phone" name="secondary_contact_phone" placeholder="Phone">
                    <input type="tel" id="secondary-whatsapp" name="secondary_contact_whatsapp" placeholder="WhatsApp">
                    <input type="email" id="secondary-email" name="secondary_contact_email" placeholder="Email">
                </div>
            </div>
            
            <div class="sum-form-actions">
                <button type="button" class="button" id="pallet-cancel-btn">Cancel</button>
                <button type="submit" class="button button-primary" id="pallet-save-btn">Save Pallet</button>
            </div>
        </form>
    </div>
</div>
<div id="customer-creation-modal" class="sum-modal" style="display: none;">
    <div class="sum-modal-content">
        <div class="sum-modal-header">
            <h2>Quick Create New Customer</h2>
            <button type="button" class="sum-modal-close customer-modal-close">&times;</button>
        </div>
        
        <form id="customer-creation-form" class="sum-form">
            <div class="sum-form-grid">
                
                <div class="sum-form-group full-width">
                    <label for="new-full-name">Full Name *</label>
                    <input type="text" id="new-full-name" name="full_name" required>
                </div>
                
                <div class="sum-form-group">
                    <label for="new-email">Email *</label>
                    <input type="email" id="new-email" name="email" required>
                </div>
                
                <div class="sum-form-group">
                    <label for="new-phone">Phone</label>
                    <input type="tel" id="new-phone" name="phone">
                </div>
                
                <div class="sum-form-group">
                    <label for="new-whatsapp">WhatsApp</label>
                    <input type="tel" id="new-whatsapp" name="whatsapp">
                </div>

                <div class="sum-form-group full-width">
                    <label for="new-full-address">Full Address</label>
                    <textarea id="new-full-address" name="full_address" rows="2"></textarea>
                </div>
                
                <div class="sum-form-group">
                    <label for="new-upload-id">ID Document URL/Path</label>
                    <input type="text" id="new-upload-id" name="upload_id">
                    <small>For attachment or link management</small>
                </div>
                
                <div class="sum-form-group">
                    <label for="new-utility-bill">Utility Bill URL/Path</label>
                    <input type="text" id="new-utility-bill" name="utility_bill">
                    <small>For attachment or link management</small>
                </div>
            </div>
            
            <div class="sum-form-actions">
                <button type="button" class="button customer-modal-close">Cancel</button>
                <button type="submit" class="button button-primary" id="save-new-customer-btn">Save & Link</button>
            </div>
        </form>
    </div>
</div>