// assets/sum-frontend-core.js
jQuery(document).ready(function($) {
    // Global State Variables
    let units = [];
    let editingUnit = null;
    let currentViewMode = localStorage.getItem('sum_frontend_view_mode') || 'grid';
    const customerModal = $('#frontend-customer-creation-modal');
    
    // Make utility functions available globally within this scope
    // This assumes sum-ui.js and sum-render.js are loaded before this script, 
    // or their functions are declared globally/via an IIFE export.
    // We will define them directly in this main file's scope for simplicity 
    // but the file separation is for organizational purposes.
    // For a real-world plugin, you'd use a shared namespace object.
    
    // ----------------------------------------------------------------
    // Core Functions (AJAX & Data Management)
    // ----------------------------------------------------------------
    
    // Load units on page load
    loadUnits();
    loadCustomersForSelect();
    
    function loadUnits() {
        const $grid = $('#frontend-units-grid');
        const $tableBody = $('#frontend-units-table-body');
        
        $grid.html('<div class="sum-frontend-loading">Loading units...</div>');
        $tableBody.html('<tr><td colspan="8" class="sum-frontend-loading">Loading units...</td></tr>');
        
        $.ajax({
            url: sum_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_get_units_frontend',
                nonce: sum_frontend_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    units = response.data;
                    // Initial toggle and render based on saved/default mode
                    toggleViewMode(currentViewMode); 
                    updateStats(units);
                } else {
                    const msg = response.data === 'Not authenticated' || response.data === 'Access denied' 
                                ? 'Access denied. Please refresh.' 
                                : 'Failed to load units: ' + response.data;
                    showError(msg);
                }
            },
            error: function() {
                showError('Failed to load units via AJAX.');
            }
        });
    }

    function loadCustomersForSelect(selectCustomerId = null) {
        const $select = $('#frontend-customer-id');
        
        $.ajax({
            url: sum_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_get_customers_frontend',
                nonce: sum_frontend_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const currentVal = selectCustomerId || $select.val(); 
                    
                    $select.empty().append('<option value="">-- Select a Customer --</option>');
                    
                    response.data.forEach(customer => {
                        $select.append(`<option value="${customer.id}">${customer.full_name}</option>`);
                    });

                    if (currentVal) {
                        $select.val(currentVal);
                    }
                }
            }
        });
    }
    
    // AJAX Functions
    
    function saveUnit() {
        const formData = $('#frontend-unit-form').serialize();
        const isOccupied = $('#frontend-is-occupied').is(':checked') ? 1 : 0;
        
        // Show loading state
        const $saveBtn = $('#frontend-save-btn');
        const originalText = $saveBtn.html();
        $saveBtn.html('<span class="sum-frontend-btn-icon">⏳</span> Saving...').prop('disabled', true);
        
        $.ajax({
            url: sum_frontend_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=sum_save_unit_frontend&nonce=' + sum_frontend_ajax.nonce + '&is_occupied=' + isOccupied,
            success: function(response) {
                if (response.success) {
                    closeModal();
                    loadUnits();
                    showSuccess(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('Failed to save unit');
            },
            complete: function() {
                $saveBtn.html(originalText).prop('disabled', false);
            }
        });
    }

    function saveNewCustomer() {
        const customerData = {
            id: '', // New customer
            full_name: $('#frontend-new-customer-name').val(),
            email: $('#frontend-new-customer-email').val(),
            phone: $('#frontend-new-customer-phone').val(),
            whatsapp: $('#frontend-new-customer-whatsapp').val(),
            full_address: $('#frontend-new-customer-address').val(),
        };

        $.ajax({
            url: sum_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_save_customer_frontend',
                nonce: sum_frontend_ajax.nonce,
                customer_data: customerData
            },
            success: function(response) {
                if (response.success) {
                    customerModal.hide();
                    $('#frontend-customer-creation-form')[0].reset();
                    showSuccess('Customer created!');
                    loadCustomersForSelect(response.data.id);
                } else {
                    showError(response.data.message || 'Could not save customer.');
                }
            },
            error: function() {
                showError('An error occurred while saving the customer.');
            }
        });
    }
    
    function bulkAddUnits() {
        const formData = $('#frontend-bulk-add-form').serialize();
        
        // Show loading state
        const $saveBtn = $('#frontend-bulk-save-btn');
        const originalText = $saveBtn.html();
        $saveBtn.html('<span class="sum-frontend-btn-icon">⏳</span> Creating...').prop('disabled', true);
        
        $.ajax({
            url: sum_frontend_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=sum_bulk_add_units_frontend&nonce=' + sum_frontend_ajax.nonce,
            success: function(response) {
                if (response.success) {
                    closeBulkModal();
                    loadUnits();
                    showSuccess(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('Failed to create units');
            },
            complete: function() {
                $saveBtn.html(originalText).prop('disabled', false);
            }
        });
    }
    
    function deleteUnit(unitId) {
        if (!confirm('Are you sure you want to delete this storage unit?')) {
            return;
        }
        
        $.ajax({
            url: sum_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_delete_unit_frontend',
                nonce: sum_frontend_ajax.nonce,
                unit_id: unitId
            },
            success: function(response) {
                if (response.success) {
                    loadUnits();
                    showSuccess(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('Failed to delete unit');
            }
        });
    }
    
    function toggleOccupancy(unitId) {
        $.ajax({
            url: sum_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_toggle_occupancy_frontend',
                nonce: sum_frontend_ajax.nonce,
                unit_id: unitId
            },
            success: function(response) {
                if (response.success) {
                    loadUnits();
                    showSuccess(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('Failed to update occupancy status');
            }
        });
    }
    
    function sendInvoice(unitId) {
        if (!confirm('Are you sure you want to send an invoice to this customer?')) {
            return;
        }
        
        $.ajax({
            url: sum_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_send_manual_invoice_frontend',
                nonce: sum_frontend_ajax.nonce,
                unit_id: unitId
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('Failed to send invoice');
            }
        });
    }
    
    function regeneratePdf(unitId) {
        if (!confirm('Generate a new PDF invoice for this unit?')) {
            return;
        }
        
        $.ajax({
            url: sum_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_regenerate_pdf_frontend',
                nonce: sum_frontend_ajax.nonce,
                unit_id: unitId
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    
                    if (response.data.download_url) {
                        // Open PDF in a new window/tab for viewing/download
                        window.open(response.data.download_url, '_blank');
                    }
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('Failed to generate PDF');
            }
        });
    }
    
    // ----------------------------------------------------------------
    // Event Handlers
    // ----------------------------------------------------------------
    
    // Search and filter
    $('#frontend-search-units, #frontend-filter-status').on('input change', function() {
        filterUnits(units);
    });
    
    // View Toggle Handlers
    $('#frontend-view-grid').on('click', function() {
        toggleViewMode('grid');
        localStorage.setItem('sum_frontend_view_mode', 'grid');
    });
    
    $('#frontend-view-list').on('click', function() {
        toggleViewMode('list');
        localStorage.setItem('sum_frontend_view_mode', 'list');
    });
    
    // Add unit functionality
    $('#frontend-add-unit-btn, #frontend-add-first-unit-btn').on('click', function() {
        openModal();
    });
    
    $('#frontend-bulk-add-btn').on('click', function() {
        openBulkModal();
    });

    // New Customer Modal
    $('#frontend-create-customer-btn').on('click', () => customerModal.show());
    $('#frontend-customer-modal-close-btn').on('click', () => customerModal.hide());
    $('#frontend-customer-creation-form').on('submit', function(e) {
        e.preventDefault();
        saveNewCustomer();
    });
    
    // Modal events
    $('.sum-frontend-modal-close, #frontend-cancel-btn, #frontend-bulk-cancel-btn').on('click', function() {
        closeModal();
        closeBulkModal();
    });
    
    $('#frontend-unit-modal, #frontend-bulk-add-modal').on('click', function(e) {
        if ($(e.target).hasClass('sum-frontend-modal-overlay')) {
            closeModal();
            closeBulkModal();
        }
    });
    
    $('#frontend-is-occupied').on('change', toggleOccupancyDetails);
    
    $('#frontend-has-secondary-contact').on('change', toggleSecondaryContact);
    
    $('#frontend-unit-form').on('submit', function(e) {
        e.preventDefault();
        saveUnit();
    });
    
    $('#frontend-bulk-add-form').on('submit', function(e) {
        e.preventDefault();
        bulkAddUnits();
    });
    
    // Bulk add preview update
    $('#frontend-bulk-prefix, #frontend-bulk-start, #frontend-bulk-end').on('input', updateBulkPreview);
    
    // Expose functions for `sum-render.js`
    window.editUnit = function(unitId) {
        const unit = units.find(u => u.id == unitId);
        if (unit) {
            openModal(unit);
        }
    };
    window.deleteUnit = deleteUnit;
    window.toggleOccupancy = toggleOccupancy;
    window.sendInvoice = sendInvoice;
    window.regeneratePdf = regeneratePdf;
    
    // Expose state and functions for other files
    window.sumState = {
        units: units,
        editingUnit: editingUnit,
        currentViewMode: currentViewMode,
        setUnits: (newUnits) => { units = newUnits; },
        getUnits: () => units
    };
    
    window.sumActions = {
        loadUnits: loadUnits,
        editUnit: window.editUnit,
        deleteUnit: deleteUnit,
        toggleOccupancy: toggleOccupancy,
        sendInvoice: sendInvoice,
        regeneratePdf: regeneratePdf
    };
    
    // Include all functions from other files here for single-file deployment, 
    // or ensure they are properly loaded in the plugin's enqueue script logic.
    // For this example, I'll place the content of the other files below as separate code blocks, 
    // assuming they are loaded in the correct order or that a proper namespace is used.
});