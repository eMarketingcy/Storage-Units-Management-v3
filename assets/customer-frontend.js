// assets/customer-frontend.js
jQuery(document).ready(function($) {
    let customers = [];
    let currentViewMode = 'grid';

    // --- Main Functions ---
    function loadCustomers() {
        $('#frontend-customers-grid').html('<div class="sum-frontend-loading">Loading...</div>');
        $.ajax({
            url: sum_customer_frontend_ajax.ajax_url,
            type: 'POST',
            data: { action: 'sum_get_customers_frontend', nonce: sum_customer_frontend_ajax.nonce },
            success: function(response) {
                if (response.success) {
                    customers = response.data;
                    renderCustomers();
                    updateStats();
                } else {
                    showError('Failed to load customers.');
                }
            }
        });
    }

    function renderCustomers() {
        const filteredCustomers = getFilteredCustomers();
        const $grid = $('#frontend-customers-grid');
        const $tableBody = $('#frontend-customers-table-body');
        const $noCustomers = $('#frontend-no-customers');

        $grid.empty();
        $tableBody.empty();

        if (filteredCustomers.length === 0) {
            $('#frontend-customers-view-wrapper').hide();
            $noCustomers.show();
            return;
        }
        
        $('#frontend-customers-view-wrapper').show();
        $noCustomers.hide();

        let gridHtml = '';
        let tableHtml = '';
        filteredCustomers.forEach(customer => {
            gridHtml += renderCustomerCard(customer);
            tableHtml += renderCustomerRow(customer);
        });

        $grid.html(gridHtml);
        $tableBody.html(tableHtml);
        bindActionEvents();
    }

function renderCustomerCard(customer) {
    const rentalsSummary = `${customer.unit_count || 0} Units / ${customer.pallet_count || 0} Pallets`;
    
    // --- NEW: Check for unpaid rentals ---
    const hasUnpaid = customer.unpaid_count && parseInt(customer.unpaid_count) > 0;
    
    return `
        <div class="sum-frontend-card" data-customer-id="${customer.id}">
            
            ${hasUnpaid ? '<div class="sum-frontend-card-unpaid-indicator"></div>' : ''}

            <div class="sum-frontend-card-header">
                <div class="sum-frontend-card-title"><h3>${customer.full_name}</h3></div>
            </div>
            <div class="sum-frontend-card-body">
                <div class="sum-frontend-detail-row">
                    <span class="sum-frontend-detail-label">📧 Email</span>
                    <span class="sum-frontend-detail-value">${customer.email || 'N/A'}</span>
                </div>
                <div class="sum-frontend-detail-row">
                    <span class="sum-frontend-detail-label">📞 Phone</span>
                    <span class="sum-frontend-detail-value">${customer.phone || 'N/A'}</span>
                </div>
                <div class="sum-frontend-detail-row">
                    <span class="sum-frontend-detail-label">📦 Rentals</span>
                    <span class="sum-frontend-detail-value">${rentalsSummary}</span>
                </div>
            </div>
            <div class="sum-frontend-card-actions">
                <button type="button" class="sum-frontend-btn sum-frontend-btn-icon frontend-generate-invoice-pdf" title="Download Full Invoice">📄</button>
                <button type="button" class="sum-frontend-btn sum-frontend-btn-icon frontend-send-invoice-email" title="Send Full Invoice">✉️</button>
                <button type="button" class="sum-frontend-btn sum-frontend-btn-secondary frontend-edit-customer">Edit</button>
                <button type="button" class="sum-frontend-btn sum-frontend-btn-danger frontend-delete-customer">Delete</button>
            </div>
        </div>
    `;
}
    function renderCustomerRow(customer) {
        const rentalsSummary = `${customer.unit_count || 0} Units / ${customer.pallet_count || 0} Pallets`;
        return `
            <tr data-customer-id="${customer.id}">
                <td>${customer.full_name}</td>
                <td>${customer.email || 'N/A'}<br>${customer.phone || ''}</td>
                <td>${rentalsSummary}</td>
                <td class="sum-frontend-table-actions">
                    <button type="button" class="sum-frontend-btn sum-frontend-btn-icon frontend-generate-invoice-pdf" title="Download Full Invoice">📄</button>
                    <button type="button" class="sum-frontend-btn sum-frontend-btn-icon frontend-send-invoice-email" title="Send Full Invoice">✉️</button>
                    <button type="button" class="sum-frontend-btn sum-frontend-btn-icon frontend-edit-customer" title="Edit">✏️</button>
                    <button type="button" class="sum-frontend-btn sum-frontend-btn-icon frontend-delete-customer" title="Delete">🗑️</button>
                </td>
            </tr>
        `;
    }

    function getFilteredCustomers() {
        const searchTerm = $('#frontend-search-customers').val().toLowerCase();
        if (!searchTerm) return customers;
        return customers.filter(c =>
            (c.full_name && c.full_name.toLowerCase().includes(searchTerm)) ||
            (c.email && c.email.toLowerCase().includes(searchTerm))
        );
    }
    
    function updateStats() {
        const totalCustomers = customers.length;
        const withUnits = customers.filter(c => c.unit_count > 0).length;
        const withPallets = customers.filter(c => c.pallet_count > 0).length;
        const totalUnpaid = customers.reduce((sum, customer) => sum + (parseInt(customer.unpaid_count) || 0), 0);

        
        let statsHtml = `
            <div class="sum-frontend-stat-card total-customers"><div class="sum-frontend-stat-content"><div class="sum-frontend-stat-value">${totalCustomers}</div><div class="sum-frontend-stat-label">Total Customers</div></div></div>
            <div class="sum-frontend-stat-card with-units"><div class="sum-frontend-stat-content"><div class="sum-frontend-stat-value">${withUnits}</div><div class="sum-frontend-stat-label">With Units</div></div></div>
            <div class="sum-frontend-stat-card with-pallets"><div class="sum-frontend-stat-content"><div class="sum-frontend-stat-value">${withPallets}</div><div class="sum-frontend-stat-label">With Pallets</div></div></div>
            <div class="sum-frontend-stat-card unpaid-invoices"><div class="sum-frontend-stat-content"><div class="sum-frontend-stat-value">${totalUnpaid}</div><div class="sum-frontend-stat-label">Unpaid Rentals</div></div></div>
        `;
        $('#customer-stats-grid').html(statsHtml);
    }
    
    // --- Event Handlers ---
    $('#frontend-search-customers').on('input', renderCustomers);
    $('#frontend-view-grid').on('click', () => toggleView('grid'));
    $('#frontend-view-list').on('click', () => toggleView('list'));
    $('#frontend-add-customer-btn').on('click', () => openModal());
    $('#frontend-customer-form').on('submit', saveCustomer);
    
    function bindActionEvents() {
        $('.frontend-edit-customer').on('click', function() {
            const customerId = $(this).closest('[data-customer-id]').data('customer-id');
            const customer = customers.find(c => c.id == customerId);
            openModal(customer);
        });
        $('.frontend-delete-customer').on('click', function() {
            const customerId = $(this).closest('[data-customer-id]').data('customer-id');
            if (confirm('Delete this customer? This cannot be undone.')) deleteCustomer(customerId);
        });
        $('.frontend-send-invoice-email').on('click', function() {
            const customerId = $(this).closest('[data-customer-id]').data('customer-id');
            if (confirm('Send a full invoice to this customer?')) sendFullInvoice(customerId);
        });
         $('.frontend-generate-invoice-pdf').on('click', function() {
            const customerId = $(this).closest('[data-customer-id]').data('customer-id');
            generateFullInvoicePDF(customerId);
        });
    }

    // --- Modal & AJAX Functions ---
    const $modal = $('#frontend-customer-modal');
    function openModal(customer = null) {
        $('#frontend-customer-form')[0].reset();
        if (customer) {
            $('#frontend-modal-title').text('Edit Customer');
            $('#frontend-customer-id').val(customer.id);
            $('#frontend-full-name').val(customer.full_name);
            $('#frontend-email').val(customer.email);
            $('#frontend-phone').val(customer.phone);
            $('#frontend-whatsapp').val(customer.whatsapp);
            $('#frontend-full-address').val(customer.full_address);
        } else {
            $('#frontend-modal-title').text('Add New Customer');
        }
        $modal.show();
    }
    
    $modal.on('click', '.sum-frontend-modal-close, .sum-frontend-modal-overlay, #frontend-cancel-btn', () => $modal.hide());
    
    function saveCustomer(e) {
        e.preventDefault();
        const customerData = $(this).serializeArray().reduce((obj, item) => {
            obj[item.name] = item.value;
            return obj;
        }, {});

        $.ajax({
            url: sum_customer_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_save_customer_frontend',
                nonce: sum_customer_frontend_ajax.nonce,
                customer_data: customerData
            },
            success: function(response) {
                if (response.success) {
                    $modal.hide();
                    loadCustomers();
                    showSuccess('Customer saved!');
                } else {
                    showError(response.data.message || 'Could not save customer.');
                }
            }
        });
    }

    function deleteCustomer(customerId) {
        $.ajax({
            url: sum_customer_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_delete_customer_frontend',
                nonce: sum_customer_frontend_ajax.nonce,
                customer_id: customerId
            },
            success: function(response) {
                if (response.success) {
                    loadCustomers();
                    showSuccess('Customer deleted.');
                } else {
                    showError(response.data.message || 'Could not delete customer.');
                }
            }
        });
    }

    function sendFullInvoice(customerId) {
        showSuccess('Sending invoice...');
        $.ajax({
            url: sum_customer_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_send_customer_invoice_frontend',
                nonce: sum_customer_frontend_ajax.nonce,
                customer_id: customerId
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Full invoice sent successfully!');
                } else {
                    showError(response.data.message || 'Failed to send invoice.');
                }
            }
        });
    }

    function generateFullInvoicePDF(customerId) {
        const downloadUrl = `${sum_customer_frontend_ajax.ajax_url}?action=sum_generate_customer_invoice_pdf&nonce=${sum_customer_frontend_ajax.nonce}&customer_id=${customerId}`;
        window.open(downloadUrl, '_blank');
    }

    function toggleView(mode) {
        currentViewMode = mode;
        const $wrapper = $('#frontend-customers-view-wrapper');
        if (mode === 'list') {
            $wrapper.removeClass('sum-view-mode-grid').addClass('sum-view-mode-list');
            $('#frontend-customers-table').show();
            $('#frontend-customers-grid').hide();
            $('#frontend-view-list').addClass('active');
            $('#frontend-view-grid').removeClass('active');
        } else {
            $wrapper.removeClass('sum-view-mode-list').addClass('sum-view-mode-grid');
            $('#frontend-customers-grid').show();
            $('#frontend-customers-table').hide();
            $('#frontend-view-grid').addClass('active');
            $('#frontend-view-list').removeClass('active');
        }
    }

    function showSuccess(message) { /* Add your toast notification here */ console.log("SUCCESS:", message); }
    function showError(message) { /* Add your toast notification here */ console.error("ERROR:", message); }

    // Initial Load
    loadCustomers();
});