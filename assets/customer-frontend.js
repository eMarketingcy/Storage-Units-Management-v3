// assets/customer-frontend.js
jQuery(document).ready(function($) {
    let customers = [];
    let currentViewMode = localStorage.getItem('sum_customer_view') || 'grid';

    // --- Main Functions ---
    function loadCustomers() {
        // Show loading state - use dedicated loading element
        const $loading = $('#frontend-loading-customers');
        const $list = $('#frontend-customers-list');
        const $empty = $('#frontend-empty-customers');

        $loading.show();
        $list.empty().hide();
        $empty.hide();

        $.ajax({
            url: sum_customer_frontend_ajax.ajax_url,
            type: 'POST',
            data: { action: 'sum_get_customers_frontend', nonce: sum_customer_frontend_ajax.nonce },
            success: function(response) {
                $('#frontend-loading-customers').hide();
                if (response.success) {
                    customers = response.data;
                    renderCustomers();
                    updateStats();
                } else {
                    showError('Failed to load customers.');
                    $('#frontend-empty-customers').show();
                }
            },
            error: function() {
                $('#frontend-loading-customers').hide();
                showError('Failed to load customers.');
                $('#frontend-empty-customers').show();
            }
        });
    }

    function renderCustomers() {
        const filteredCustomers = getFilteredCustomers();
        const $listView = $('#frontend-customers-list');
        const $emptyState = $('#frontend-empty-customers');

        $listView.empty();

        if (filteredCustomers.length === 0) {
            $listView.hide();
            $emptyState.show();
            return;
        }

        $listView.show();
        $emptyState.hide();

        // Render based on current view mode
        if (currentViewMode === 'list') {
            $listView.removeClass('sum-customers-grid-view').addClass('sum-customers-list-view');
            let listHtml = '<div class="sum-customer-list-header">';
            listHtml += '<div>Name</div>';
            listHtml += '<div>Contact</div>';
            listHtml += '<div>Rentals</div>';
            listHtml += '<div>Status</div>';
            listHtml += '<div>Actions</div>';
            listHtml += '</div>';

            filteredCustomers.forEach(customer => {
                listHtml += renderCustomerListItem(customer);
            });

            $listView.html(listHtml);
        } else {
            // Grid view
            $listView.removeClass('sum-customers-list-view').addClass('sum-customers-grid-view');
            let gridHtml = '<div class="sum-customers-grid">';
            filteredCustomers.forEach(customer => {
                gridHtml += renderCustomerCard(customer);
            });
            gridHtml += '</div>';
            $listView.html(gridHtml);
        }

        bindActionEvents();
    }

    function renderCustomerListItem(customer) {
        const rentalsSummary = `${customer.unit_count || 0} Units / ${customer.pallet_count || 0} Pallets`;
        const hasUnpaid = customer.unpaid_count && parseInt(customer.unpaid_count) > 0;
        const status = hasUnpaid ? 'unpaid' : (customer.unit_count > 0 || customer.pallet_count > 0 ? 'active' : 'past');
        const statusText = hasUnpaid ? 'Unpaid' : (status === 'active' ? 'Active' : 'Past');

        return `
            <div class="sum-customer-list-item" data-customer-id="${customer.id}">
                <div class="sum-customer-name-col">${customer.full_name || 'N/A'}</div>
                <div class="sum-customer-contact-col">
                    ${customer.email || 'N/A'}<br>
                    ${customer.phone || ''}
                </div>
                <div class="sum-customer-rentals-col">
                    <span class="sum-customer-rental-badge">${rentalsSummary}</span>
                </div>
                <div class="sum-customer-status-col">
                    <span class="sum-customer-status-${status}">${statusText}</span>
                </div>
                <div class="sum-customer-actions-col">
                    <button type="button" class="sum-customer-action-btn frontend-generate-invoice-pdf" title="Download PDF">📄</button>
                    <button type="button" class="sum-customer-action-btn frontend-send-customer-invoice-btn" title="Send Email">✉️</button>
                    <button type="button" class="sum-customer-action-btn frontend-edit-customer" title="Edit">✏️</button>
                    <button type="button" class="sum-customer-action-btn sum-customer-action-btn-danger frontend-delete-customer" title="Delete">🗑️</button>
                </div>
            </div>
        `;
    }

    function renderCustomerCard(customer) {
        const rentalsSummary = `${customer.unit_count || 0} Units / ${customer.pallet_count || 0} Pallets`;
        const hasUnpaid = customer.unpaid_count && parseInt(customer.unpaid_count) > 0;

        return `
            <div class="sum-customer-card" data-customer-id="${customer.id}">
                ${hasUnpaid ? '<div class="sum-unpaid-indicator">⚠️ Unpaid</div>' : ''}
                <div class="sum-customer-card-header">
                    <h3>${customer.full_name}</h3>
                </div>
                <div class="sum-customer-card-body">
                    <div class="sum-customer-detail-row">
                        <span class="sum-customer-detail-label">📧 Email</span>
                        <span class="sum-customer-detail-value">${customer.email || 'N/A'}</span>
                    </div>
                    <div class="sum-customer-detail-row">
                        <span class="sum-customer-detail-label">📞 Phone</span>
                        <span class="sum-customer-detail-value">${customer.phone || 'N/A'}</span>
                    </div>
                    <div class="sum-customer-detail-row">
                        <span class="sum-customer-detail-label">📦 Rentals</span>
                        <span class="sum-customer-detail-value">${rentalsSummary}</span>
                    </div>
                </div>
                <div class="sum-customer-card-actions">
                    <button type="button" class="sum-customer-action-btn frontend-generate-invoice-pdf" title="Download PDF">📄</button>
                    <button type="button" class="sum-customer-action-btn frontend-send-customer-invoice-btn" title="Send Email">✉️</button>
                    <button type="button" class="sum-customer-action-btn frontend-edit-customer">Edit</button>
                    <button type="button" class="sum-customer-action-btn sum-customer-action-btn-danger frontend-delete-customer">Delete</button>
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
        const statusFilter = $('#frontend-filter-status').val();

        let filtered = customers;

        // Apply status filter
        if (statusFilter !== 'all') {
            filtered = filtered.filter(c => {
                const hasUnpaid = parseInt(c.unpaid_count) > 0;
                const hasRentals = (c.unit_count > 0 || c.pallet_count > 0);

                if (statusFilter === 'unpaid') return hasUnpaid;
                if (statusFilter === 'active') return hasRentals && !hasUnpaid;
                if (statusFilter === 'past') return !hasRentals;
                return true;
            });
        }

        // Apply search filter
        if (searchTerm) {
            filtered = filtered.filter(c =>
                (c.full_name && c.full_name.toLowerCase().includes(searchTerm)) ||
                (c.email && c.email.toLowerCase().includes(searchTerm)) ||
                (c.phone && c.phone.toLowerCase().includes(searchTerm))
            );
        }

        return filtered;
    }
    
    function updateStats() {
        const totalCustomers = customers.length;
        const activeCustomers = customers.filter(c => (c.unit_count > 0 || c.pallet_count > 0)).length;
        const unpaidCustomers = customers.filter(c => parseInt(c.unpaid_count) > 0).length;
        const totalRentals = customers.reduce((sum, c) => sum + (parseInt(c.unit_count) || 0) + (parseInt(c.pallet_count) || 0), 0);

        $('#frontend-total-customers').text(totalCustomers);
        $('#frontend-active-customers').text(activeCustomers);
        $('#frontend-unpaid-customers').text(unpaidCustomers);
        $('#frontend-total-rentals').text(totalRentals);
    }

    function toggleView(mode) {
        currentViewMode = mode;
        localStorage.setItem('sum_customer_view', mode);

        // Update button states
        $('.sum-customer-view-btn').removeClass('active');
        $(`.sum-customer-view-btn[data-view="${mode}"]`).addClass('active');

        // Re-render customers with new view
        renderCustomers();
    }
    
    // --- Event Handlers ---
    $('#frontend-search-customers').on('input', renderCustomers);
    $('#frontend-filter-status').on('change', renderCustomers);

    // View toggle buttons
    $('.sum-customer-view-btn').on('click', function() {
        const view = $(this).data('view');
        toggleView(view);
    });

    // Add customer buttons
    $('#frontend-add-customer-btn, #frontend-add-first-customer-btn').on('click', () => openModal());
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
        $('.frontend-send-customer-invoice-btn').on('click', function() {
            const customerId = $(this).closest('[data-customer-id]').data('customer-id');
            sendCustomerInvoice(customerId);
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
            // --- NEW: Populate document fields ---
            $('#frontend-upload-id').val(customer.upload_id);
            $('#frontend-utility-bill').val(customer.utility_bill);
        } else {
            $('#frontend-modal-title').text('Add New Customer');
        }
        $modal.show();
    }
    
    $modal.on('click', '.sum-customer-modal-close, .sum-customer-modal-overlay, #frontend-cancel-btn', () => $modal.hide());
    
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
                    showSuccess('Customer saved successfully!');
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : 'Could not save customer.';
                    if (errorMsg.toLowerCase().includes('email') && errorMsg.toLowerCase().includes('exist')) {
                        showError('⚠️ This email address is already registered. Please use a different email.');
                    } else {
                        showError(errorMsg);
                    }
                }
            },
            error: function() {
                showError('Network error. Please try again.');
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

    function sendCustomerInvoice(customerId) {
        if (!customerId) {
            showError('Missing customer ID');
            return;
        }

        if (!confirm('Are you sure you want to send a consolidated invoice to this customer?')) {
            return;
        }

        showSuccess('Sending invoice...');

        $.ajax({
            url: sum_customer_frontend_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sum_send_customer_invoice_frontend',
                nonce: sum_customer_frontend_ajax.nonce,
                customer_id: customerId
            },
            success: function (response) {
                if (response && response.success) {
                    const msg = (response.data && response.data.message) ? response.data.message : 'Invoice sent successfully!';
                    showSuccess(msg);
                } else {
                    const err = (response && response.data && response.data.message) ? response.data.message : 'Failed to send invoice';
                    showError(err);
                }
            },
            error: function (xhr) {
                const err = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                    ? xhr.responseJSON.data.message
                    : 'Failed to send invoice';
                showError(err);
            }
        });
    }

    function generateFullInvoicePDF(customerId) {
        const downloadUrl = `${sum_customer_frontend_ajax.ajax_url}?action=sum_generate_customer_invoice_pdf&nonce=${sum_customer_frontend_ajax.nonce}&customer_id=${customerId}`;
        window.open(downloadUrl, '_blank');
    }

    function showSuccess(message) {
        // Create a modern toast notification for success (matching pallet style)
        const toast = jQuery(`
            <div class="sum-customer-toast sum-customer-toast-success">
                <div class="sum-customer-toast-icon">✅</div>
                <div class="sum-customer-toast-message">${message}</div>
            </div>
        `);

        jQuery('body').append(toast);

        setTimeout(() => { toast.addClass('sum-customer-toast-show'); }, 100);
        setTimeout(() => {
            toast.removeClass('sum-customer-toast-show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function showError(message) {
        // Create a modern toast notification for error (matching pallet style)
        const toast = jQuery(`
            <div class="sum-customer-toast sum-customer-toast-error">
                <div class="sum-customer-toast-icon">❌</div>
                <div class="sum-customer-toast-message">${message}</div>
            </div>
        `);

        jQuery('body').append(toast);

        setTimeout(() => { toast.addClass('sum-customer-toast-show'); }, 100);
        setTimeout(() => {
            toast.removeClass('sum-customer-toast-show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Set initial view mode
    $('.sum-customer-view-btn').removeClass('active');
    $(`.sum-customer-view-btn[data-view="${currentViewMode}"]`).addClass('active');

    // Initial Load
    loadCustomers();
});