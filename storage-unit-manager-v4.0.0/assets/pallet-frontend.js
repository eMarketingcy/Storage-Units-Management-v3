jQuery(document).ready(function($) {
    let pallets = [];
    let editingPallet = null;
    
    // --- New Customer Modal Elements ---
    const customerModal = $('#frontend-customer-creation-modal');

    // Initialize
    loadPallets();
    loadCustomersForSelect(); // Load customers on page start
    
    // Event listeners
    $('#frontend-add-pallet-btn, #frontend-add-first-pallet-btn').on('click', function() {
        openModal();
    });
    
    $('.sum-pallet-modal-close, #frontend-pallet-cancel-btn').on('click', function() {
        closeModal();
    });
    
    $('.sum-pallet-modal-overlay').on('click', function(e) {
        if ($(e.target).is('.sum-pallet-modal-overlay')) {
             closeModal();
        }
    });
    
    $('#frontend-pallet-form').on('submit', function(e) {
        e.preventDefault();
        savePallet();
    });
    
    $('#frontend-search-pallets').on('input', function() {
        filterPallets();
    });
    
    $('#frontend-filter-status').on('change', function() {
        filterPallets();
    });
    
    // --- REVISED event listener for pallet name generation ---
    $('#frontend-pallet-customer-id').on('change', function() {
        const palletId = $('#frontend-pallet-id').val();
        const selectedCustomerName = $(this).find('option:selected').text();

        // Only generate for new pallets when a valid customer is chosen
        if (palletId === '' && $(this).val() !== '') {
            generatePalletName(selectedCustomerName);
        }
    });

    // --- New Event Handlers for Customer Modal ---
    $('#frontend-pallet-add-customer-btn').on('click', () => customerModal.show());
    $('#frontend-customer-modal-close-btn').on('click', () => customerModal.hide());
    
    $('#frontend-customer-creation-form').on('submit', function(e) {
        e.preventDefault();
        saveNewCustomer();
    });
    
    // Toggle secondary contact
    $('#frontend-has-secondary-contact').on('change', function() {
        toggleSecondaryContact();
    });
    
    // Functions
    function loadPallets() {
        $('#frontend-pallets-grid').html('<div class="sum-pallet-loading">Loading pallets...</div>');
        
        const filterValue = $('#frontend-filter-status').val();
        
        $.ajax({
            url: sum_pallet_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_get_pallets_frontend',
                nonce: sum_pallet_frontend_ajax.nonce,
                filter: filterValue
            },
            success: function(response) {
                if (response.success) {
                    pallets = response.data;
                    renderPallets();
                    updateStats();
                } else {
                    if (response.data === 'Not authenticated' || response.data === 'Access denied') {
                        showError('Access denied. Please refresh the page and try again.');
                    } else {
                        showError('Failed to load pallets');
                    }
                }
            },
            error: function() {
                showError('Failed to load pallets');
            }
        });
    }
    
    function renderPallets() {
        const filteredPallets = getFilteredPallets();
        const $grid = $('#frontend-pallets-grid');
        const $noPallets = $('#frontend-no-pallets');
        
        if (filteredPallets.length === 0) {
            $grid.hide();
            $noPallets.show();
            return;
        }
        
        $noPallets.hide();
        $grid.show();
        
        let html = '';
        filteredPallets.forEach(function(pallet) {
            html += renderPalletCard(pallet);
        });
        
        $grid.html(html);
        
        // Bind events
        $('.frontend-edit-pallet').on('click', function() {
            const palletId = $(this).data('pallet-id');
            editPallet(palletId);
        });
        
        $('.frontend-delete-pallet').on('click', function() {
            const palletId = $(this).data('pallet-id');
            deletePallet(palletId);
        });
        
        $('.frontend-send-pallet-invoice-btn').on('click', function() {
            const palletId = $(this).data('pallet-id');
            sendPalletInvoice(palletId);
        });
        
        $('.frontend-regenerate-pallet-pdf-btn').on('click', function() {
            const palletId = $(this).data('pallet-id');
            regeneratePalletPdf(palletId);
        });
    }
    
/**
 * Calculates the number of occupied months between two dates.
 * Any part of a month is counted as one occupied month.
 * e.g., Sept 29 to Oct 01 is 2 months (September and October).
 */
function calculateOccupiedMonths(startDateStr, endDateStr) {
    if (!startDateStr || !endDateStr) {
        return 0;
    }
    
    try {
        const startDate = new Date(startDateStr);
        const endDate = new Date(endDateStr);
        
        // Add a few hours to the dates to avoid timezone issues with exact midnight.
        startDate.setHours(12);
        endDate.setHours(12);

        if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || endDate < startDate) {
            return 0;
        }

        const startYear = startDate.getFullYear();
        const startMonth = startDate.getMonth();
        const endYear = endDate.getFullYear();
        const endMonth = endDate.getMonth();
        
        // Calculate the total number of months spanned.
        const months = (endYear - startYear) * 12 + (endMonth - startMonth) + 1;
        return months;
    } catch (e) {
        return 0;
    }
}


function renderPalletCard(pallet) {
    // Determine if the pallet is assigned to a customer.
    const isAssigned = pallet.customer_id && parseInt(pallet.customer_id) > 0;

    // --- NEW: Calculate total months ---
    const totalMonths = calculateOccupiedMonths(pallet.period_from, pallet.period_until);

    // --- NEW: Format dates for display ---
    const formatDate = (dateStr) => {
        if (!dateStr) return '—';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    // Set the badge text and class based on assignment and payment status.
    let statusText = 'Available';
    let statusClass = 'available'; // Green for available

    if (isAssigned) {
        statusText = pallet.payment_status ? pallet.payment_status.replace('_', ' ') : 'Assigned';
        switch (pallet.payment_status) {
            case 'paid':
                statusClass = 'paid'; // Blue for paid
                break;
            case 'unpaid':
                statusClass = 'unpaid'; // Yellow for unpaid
                break;
            case 'overdue':
                statusClass = 'overdue'; // Red for overdue
                break;
            default:
                statusClass = 'assigned'; // Default grey for assigned
        }
    }

    const card = `
        <div class="sum-pallet-card" data-pallet-id="${pallet.id}">
            <div class="sum-pallet-card-header">
                <div class="sum-pallet-card-title">
                    <span class="sum-pallet-icon">${pallet.pallet_type === 'EU' ? '🇪🇺' : '🇺🇸'}</span>
                    <h3>${pallet.pallet_name}</h3>
                </div>
                <div class="sum-pallet-card-status-badge ${statusClass}">
                    ${statusText}
                </div>
            </div>

            <div class="sum-pallet-card-body">
                <div class="sum-pallet-detail-row">
                    <span class="sum-pallet-detail-label">Customer</span>
                    <span class="sum-pallet-detail-value">${isAssigned ? (pallet.customer_name || 'N/A') : '—'}</span>
                </div>

                <div class="sum-pallet-period-row">
                    <div class="sum-pallet-period-item">
                        <span class="sum-pallet-detail-label">From</span>
                        <span class="sum-pallet-detail-value">${formatDate(pallet.period_from)}</span>
                    </div>
                    <div class="sum-pallet-period-item">
                        <span class="sum-pallet-detail-label">Until</span>
                        <span class="sum-pallet-detail-value">${formatDate(pallet.period_until)}</span>
                    </div>
                    <div class="sum-pallet-period-item sum-pallet-period-total">
                        <span class="sum-pallet-detail-label">Total</span>
                        <span class="sum-pallet-detail-value">${totalMonths > 0 ? totalMonths + (totalMonths > 1 ? ' Months' : ' Month') : '—'}</span>
                    </div>
                </div>
                <div class="sum-pallet-detail-row">
                    <span class="sum-pallet-detail-label">Price</span>
                    <span class="sum-pallet-detail-value">€${parseFloat(pallet.monthly_price || 0).toFixed(2)} / mo</span>
                </div>
            </div>

            <div class="sum-pallet-card-actions">
                 <button type="button" class="sum-pallet-btn sum-pallet-btn-icon frontend-regenerate-pallet-pdf-btn" data-pallet-id="${pallet.id}" title="Download PDF">📄</button>
                 <button type="button" class="sum-pallet-btn sum-pallet-btn-icon frontend-send-pallet-invoice-btn" data-pallet-id="${pallet.id}" title="Send Invoice">✉️</button>
                 <button type="button" class="sum-pallet-btn sum-pallet-btn-secondary frontend-edit-pallet" data-pallet-id="${pallet.id}">Edit</button>
                 <button type="button" class="sum-pallet-btn sum-pallet-btn-danger frontend-delete-pallet" data-pallet-id="${pallet.id}">Delete</button>
            </div>
        </div>
    `;
    return card;
}

// in pallet-frontend.js

function getFilteredPallets() {
    const searchTerm = $('#frontend-search-pallets').val().toLowerCase();
    const filterStatus = $('#frontend-filter-status').val();

    return pallets.filter(function(pallet) {
        // --- UPDATED SEARCH LOGIC ---
        // Check both the pallet name and the customer's name for a match.
        // This is safer because customer_name comes from the database JOIN.
        const matchesSearch = (
            (pallet.pallet_name && pallet.pallet_name.toLowerCase().includes(searchTerm)) ||
            (pallet.customer_name && pallet.customer_name.toLowerCase().includes(searchTerm))
        );
        // --- END OF UPDATE ---
        
        let matchesFilter = true;
        
        if (filterStatus === 'eu') {
            matchesFilter = pallet.pallet_type === 'EU';
        } else if (filterStatus === 'us') {
            matchesFilter = pallet.pallet_type === 'US';
        } else if (filterStatus === 'past_due') {
            if (pallet.period_until) {
                const today = new Date();
                // Set hours to 0 to compare dates only
                today.setHours(0, 0, 0, 0); 
                const endDate = new Date(pallet.period_until);
                matchesFilter = endDate < today;
            } else {
                matchesFilter = false;
            }
        } else if (filterStatus === 'unpaid') {
            matchesFilter = pallet.payment_status !== 'paid';
        }
        
        return matchesSearch && matchesFilter;
    });
}    
    function filterPallets() {
        renderPallets();
    }
    
    function updateStats() {
        const total = pallets.length;
        const unpaid = pallets.filter(pallet => pallet.payment_status !== 'paid').length;
        const eu = pallets.filter(pallet => pallet.pallet_type === 'EU').length;
        const us = pallets.filter(pallet => pallet.pallet_type === 'US').length;
        
        $('#frontend-total-pallets').text(total);
        $('#frontend-unpaid-pallets').text(unpaid);
        $('#frontend-eu-pallets').text(eu);
        $('#frontend-us-pallets').text(us);
    }
    
    function openModal(pallet = null) {
        editingPallet = pallet;
        
        if (pallet) {
            $('#frontend-pallet-modal-title').text('Edit Pallet Storage');
            populateForm(pallet);
        } else {
            $('#frontend-pallet-modal-title').text('Add New Pallet Storage');
            resetForm();
        }
        
        $('#frontend-pallet-modal').show();
        $('body').css('overflow', 'hidden');
    }
    
    function closeModal() {
        $('#frontend-pallet-modal').hide();
        $('body').css('overflow', 'auto');
        editingPallet = null;
        resetForm();
    }
    
    function populateForm(pallet) {
        $('#frontend-pallet-id').val(pallet.id);
        $('#frontend-pallet-name').val(pallet.pallet_name);
        $('#frontend-pallet-type').val(pallet.pallet_type || 'EU');
        $('#frontend-actual-height').val(pallet.actual_height || '');
        $('#frontend-period-from').val(pallet.period_from || '');
        $('#frontend-period-until').val(pallet.period_until || '');
        $('#frontend-payment-status').val(pallet.payment_status || 'paid');
        
        // --- THIS IS THE KEY CHANGE ---
        $('#frontend-pallet-customer-id').val(pallet.customer_id || ''); 
        
        $('#frontend-secondary-name').val(pallet.secondary_contact_name || '');
        $('#frontend-secondary-phone').val(pallet.secondary_contact_phone || '');
        $('#frontend-secondary-whatsapp').val(pallet.secondary_contact_whatsapp || '');
        $('#frontend-secondary-email').val(pallet.secondary_contact_email || '');
        
        const hasSecondary = pallet.secondary_contact_name && pallet.secondary_contact_name.trim() !== '';
        $('#frontend-has-secondary-contact').prop('checked', hasSecondary);
        
        toggleSecondaryContact();
    }
    
    function resetForm() {
        $('#frontend-pallet-form')[0].reset();
        $('#frontend-pallet-id').val('');

        // --- THIS IS THE KEY CHANGE ---
        $('#frontend-pallet-customer-id').val(''); 
        
        $('#frontend-has-secondary-contact').prop('checked', false);
        $('#frontend-payment-status').val('paid');
        $('#frontend-pallet-type').val('EU');
        toggleSecondaryContact();
    }
    
    function toggleSecondaryContact() {
        const hasSecondary = $('#frontend-has-secondary-contact').is(':checked');
        $('#frontend-secondary-contact-section').toggle(hasSecondary);
    }
    
    function generatePalletName(customerName) {
        $.ajax({
            url: sum_pallet_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_generate_pallet_name_frontend',
                nonce: sum_pallet_frontend_ajax.nonce,
                customer_name: customerName
            },
            success: function(response) {
                if (response.success) {
                    $('#frontend-pallet-name').val(response.data);
                }
            }
        });
    }
    
    function savePallet() {
        const formData = $('#frontend-pallet-form').serialize();
        
        // Show loading state
        const $saveBtn = $('#frontend-pallet-save-btn');
        const originalText = $saveBtn.html();
        $saveBtn.html('<span class="sum-pallet-btn-icon">⏳</span> Saving...').prop('disabled', true);
        
        $.ajax({
            url: sum_pallet_frontend_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=sum_save_pallet_frontend&nonce=' + sum_pallet_frontend_ajax.nonce,
            success: function(response) {
                if (response.success) {
                    closeModal();
                    loadPallets();
                    showSuccess(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('Failed to save pallet');
            },
            complete: function() {
                $saveBtn.html(originalText).prop('disabled', false);
            }
        });
    }
    
    function editPallet(palletId) {
        const pallet = pallets.find(p => p.id == palletId);
        if (pallet) {
            openModal(pallet);
        }
    }
    
    function deletePallet(palletId) {
        if (!confirm('Are you sure you want to delete this pallet storage?')) {
            return;
        }
        
        $.ajax({
            url: sum_pallet_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_delete_pallet_frontend',
                nonce: sum_pallet_frontend_ajax.nonce,
                pallet_id: palletId
            },
            success: function(response) {
                if (response.success) {
                    loadPallets();
                    showSuccess(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('Failed to delete pallet');
            }
        });
    }
    
    function sendPalletInvoice(palletId) {
        if (!confirm('Are you sure you want to send an invoice to this customer?')) {
            return;
        }
        
        $.ajax({
            url: sum_pallet_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_send_pallet_invoice_frontend',
                nonce: sum_pallet_frontend_ajax.nonce,
                pallet_id: palletId
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
    
    function regeneratePalletPdf(palletId) {
        if (!confirm('Generate a new PDF invoice for this pallet?')) {
            return;
        }
        
        $.ajax({
            url: sum_pallet_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_regenerate_pallet_pdf_frontend',
                nonce: sum_pallet_frontend_ajax.nonce,
                pallet_id: palletId
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    
                    if (response.data.download_url) {
                        // Try multiple download methods
                        window.open(response.data.download_url, '_blank');
                        
                        setTimeout(function() {
                            const link = document.createElement('a');
                            link.href = response.data.download_url;
                            link.download = response.data.filename;
                            link.target = '_blank';
                            link.style.display = 'none';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }, 1000);
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
    
    function showSuccess(message) {
        // Create a modern toast notification
        const toast = $(`
            <div class="sum-pallet-toast sum-pallet-toast-success">
                <div class="sum-pallet-toast-icon">✅</div>
                <div class="sum-pallet-toast-message">${message}</div>
            </div>
        `);
        
        $('body').append(toast);
        
        setTimeout(() => {
            toast.addClass('sum-pallet-toast-show');
        }, 100);
        
        setTimeout(() => {
            toast.removeClass('sum-pallet-toast-show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    function showError(message) {
        // Create a modern toast notification
        const toast = $(`
            <div class="sum-pallet-toast sum-pallet-toast-error">
                <div class="sum-pallet-toast-icon">❌</div>
                <div class="sum-pallet-toast-message">${message}</div>
            </div>
        `);
        
        $('body').append(toast);
        
        setTimeout(() => {
            toast.addClass('sum-pallet-toast-show');
        }, 100);
        
        setTimeout(() => {
            toast.removeClass('sum-pallet-toast-show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
     function loadCustomersForSelect(selectCustomerId = null) {
        const $select = $('#frontend-pallet-customer-id');
        
        $.ajax({
            url: sum_pallet_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_get_customer_list_frontend', // Correct action name
                nonce: sum_pallet_frontend_ajax.nonce
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

    /**
     * NEW: Handles the submission of the new customer form.
     */
function saveNewCustomer() {
    const customerData = {
        id: '', // New customer
        full_name: $('#frontend-new-customer-name').val(),
        email: $('#frontend-new-customer-email').val(),
        phone: $('#frontend-new-customer-phone').val(),
        whatsapp: $('#frontend-new-customer-whatsapp').val(),
        full_address: $('#frontend-new-customer-address').val(),
        upload_id: $('#frontend-new-customer-id-upload').val(),
        utility_bill: $('#frontend-new-customer-bill-upload').val()
    };

    $.ajax({
        url: sum_pallet_frontend_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'sum_save_customer_frontend',
            nonce: sum_pallet_frontend_ajax.nonce,
            customer_data: customerData
        },
        success: function(response) {
            if (response.success) {
                customerModal.hide();
                $('#frontend-customer-creation-form')[0].reset();
                showSuccess('Customer created!');
                loadCustomersForSelect(response.data.customer_id);
            } else {
                showError(response.data.message || 'Could not save customer.');
            }
        },
        error: function() {
            showError('An error occurred while saving the customer.');
        }
    });
}
});

// Add toast styles dynamically
jQuery(document).ready(function($) {
    const toastStyles = `
        <style>
        .sum-pallet-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            z-index: 10000;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s ease;
            max-width: 400px;
        }
        
        .sum-pallet-toast-show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .sum-pallet-toast-success {
            border-left: 4px solid #10b981;
        }
        
        .sum-pallet-toast-error {
            border-left: 4px solid #ef4444;
        }
        
        .sum-pallet-toast-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .sum-pallet-toast-message {
            font-weight: 500;
            color: #1e293b;
            line-height: 1.4;
        }
        
        @media (max-width: 480px) {
            .sum-pallet-toast {
                left: 10px;
                right: 10px;
                max-width: none;
                transform: translateY(-100px);
            }
            
            .sum-pallet-toast-show {
                transform: translateY(0);
            }
        }
        </style>
    `;
    
    $('head').append(toastStyles);
});