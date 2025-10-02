jQuery(document).ready(function($) {
    
    // Customer Modal Functions
function openCustomerModal() {
    $('#customer-creation-modal').show();
    $('#customer-creation-form')[0].reset();
}

function closeCustomerModal() {
    $('#customer-creation-modal').hide();
    $('#customer-creation-form')[0].reset();
}

// Event Listeners for the Customer Modal
$('#create-customer-btn').on('click', function() {
    openCustomerModal();
});

$('.customer-modal-close').on('click', function() {
    closeCustomerModal();
});

$('#customer-creation-form').on('submit', function(e) {
    e.preventDefault();
    saveNewCustomer();
});

    let pallets = [];
    let editingPallet = null;
    
    // Initialize
    loadPallets();
    
    // Event listeners
    $('#add-pallet-btn, #add-first-pallet-btn').on('click', function() {
        openModal();
    });
    
    $('.sum-modal-close, #pallet-cancel-btn').on('click', function() {
        closeModal();
    });
    
    $('#pallet-modal').on('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    $('#pallet-form').on('submit', function(e) {
        e.preventDefault();
        savePallet();
    });
    
    $('#search-pallets').on('input', function() {
        filterPallets();
    });
    
    $('#filter-status').on('change', function() {
        filterPallets();
    });
    
     // --- NEW: Customer Modal Handlers ---
    $('#create-customer-btn').on('click', function(e) {
        e.preventDefault();
        openCustomerModal();
    });

    // Close modal via the X button or the Cancel button
    $('.customer-modal-close').on('click', function() {
        closeCustomerModal();
    });
    
    $('#customer-creation-form').on('submit', function(e) {
        e.preventDefault();
        saveNewCustomer();
    });
    // Generate pallet name when customer name changes
    $('#primary-name').on('blur', function() {
        const customerName = $(this).val();
        if (customerName && !$('#pallet-name').val()) {
            generatePalletName(customerName);
        }
    });
    
    // Auto-calculate price when height or type changes
    $('#actual-height, #pallet-type').on('change', function() {
        calculatePrice();
    });
    
    // Functions
    function loadPallets() {
        $('#pallets-grid').html('<div class="sum-loading">Loading pallets...</div>');
        
        const filterValue = $('#filter-status').val();
        
        $.ajax({
            url: sum_pallet_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_get_pallets',
                nonce: sum_pallet_ajax.nonce,
                filter: filterValue
            },
            success: function(response) {
                if (response.success) {
                    pallets = response.data;
                    renderPallets();
                    updateStats();
                } else {
                    showError('Failed to load pallets');
                }
            },
            error: function() {
                showError('Failed to load pallets');
            }
        });
    }
    
    function renderPallets() {
        const filteredPallets = getFilteredPallets();
        const $grid = $('#pallets-grid');
        const $noPallets = $('#no-pallets-message');
        
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
        $('.edit-pallet').on('click', function() {
            const palletId = $(this).data('pallet-id');
            editPallet(palletId);
        });
        
        $('.delete-pallet').on('click', function() {
            const palletId = $(this).data('pallet-id');
            deletePallet(palletId);
        });
        
        $('.send-pallet-invoice-btn').on('click', function() {
            const palletId = $(this).data('pallet-id');
            sendPalletInvoice(palletId);
        });
        
        $('.regenerate-pallet-pdf-btn').on('click', function() {
            const palletId = $(this).data('pallet-id');
            regeneratePalletPdf(palletId);
        });
    }
    
    function renderPalletCard(pallet) {
        const monthlyPrice = pallet.monthly_price ? `â‚¬${parseFloat(pallet.monthly_price).toFixed(2)}/month` : '';
        
        // Payment status indicator
        let paymentBadge = '';
        const paymentStatus = pallet.payment_status || 'paid';
        const paymentClass = paymentStatus === 'paid' ? 'sum-payment-paid' : 'sum-payment-unpaid';
        const paymentText = paymentStatus.charAt(0).toUpperCase() + paymentStatus.slice(1);
        paymentBadge = `<span class="sum-payment-badge ${paymentClass}">${paymentText}</span>`;
        
        // Check if past due
        let pastDueBadge = '';
        if (pallet.period_until) {
            const today = new Date();
            const endDate = new Date(pallet.period_until);
            if (endDate < today) {
                pastDueBadge = '<span class="sum-past-due-badge">Past Due</span>';
            }
        }
        
        let contactInfo = '';
        // Check for the new joined customer name field
        if (pallet.customer_name) {
            const emailForActions = pallet.customer_email || pallet.primary_contact_email; 
            
            contactInfo = `
                <div class="sum-contact-info">
                    <h4>👤 Customer (ID: ${pallet.customer_id || 'N/A'})</h4>
                    <div class="sum-contact-details">
                        <div><strong>Name:</strong> ${pallet.customer_name}</div>
                        <div><strong>Phone:</strong> ${pallet.customer_phone || 'N/A'}</div>
                        <div><strong>WhatsApp:</strong> ${pallet.customer_whatsapp || 'N/A'}</div>
                        <div><strong>Email:</strong> ${pallet.customer_email || 'N/A'}</div>
                        ${pallet.period_from && pallet.period_until ? 
                            `<div><strong>Period:</strong> ${pallet.period_from} - ${pallet.period_until}</div>` : ''}
                        <div class="sum-action-buttons" style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                            ${emailForActions ?
                                `<button type="button" class="send-invoice-btn sum-btn-modern" data-pallet-id="${pallet.id}" title="Send Invoice">
                                    <span class="dashicons dashicons-email"></span> Send Invoice
                                </button>` : ''}
                            ${emailForActions ?
                                `<button type="button" class="regenerate-pdf-btn sum-btn-modern sum-btn-secondary" data-pallet-id="${pallet.id}" title="Generate PDF">
                                    <span class="dashicons dashicons-pdf"></span> PDF
                                </button>` : ''}
                            ${pallet.customer_id ?
                                `<button type="button" class="send-intake-link-btn sum-btn-modern sum-btn-accent" data-pallet-id="${pallet.id}" title="Send Intake Form Link">
                                    <span class="dashicons dashicons-clipboard"></span> Send Intake Link
                                </button>` : ''}
                        </div>
                    </div>
                    ${pallet.secondary_contact_name ? `
                        <div class="sum-secondary-contact">
                            <h4>👥 Secondary Contact</h4>
                            <div class="sum-contact-details">
                                <div><strong>Name:</strong> ${pallet.secondary_contact_name}</div>
                                <div><strong>Phone:</strong> ${pallet.secondary_contact_phone || 'N/A'}</div>
                                <div><strong>Email:</strong> ${pallet.secondary_contact_email || 'N/A'}</div>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }
        
        return `
            <div class="sum-unit-card">
                <div class="sum-unit-header">
                    <div class="sum-unit-info">
                        <h3>${pallet.pallet_name}</h3>
                        <p>${pallet.pallet_type} Pallet â€¢ ${pallet.actual_height}m (${pallet.charged_height}m) â€¢ ${parseFloat(pallet.cubic_meters).toFixed(2)} mÂ³${monthlyPrice ? ` â€¢ ${monthlyPrice}` : ''}</p>
                        <div class="sum-badges">
                            ${paymentBadge}
                            ${pastDueBadge}
                        </div>
                    </div>
                    <div class="sum-unit-actions">
                        <button type="button" class="edit-pallet edit-btn" data-pallet-id="${pallet.id}" title="Edit">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button type="button" class="delete-pallet delete-btn" data-pallet-id="${pallet.id}" title="Delete">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
                
                ${contactInfo}
            </div>
        `;
    }
    
    function getFilteredPallets() {
        const searchTerm = $('#search-pallets').val().toLowerCase();
        
        if (!pallets || !Array.isArray(pallets)) {
            return [];
        }
        
        return pallets.filter(function(pallet) {
            if (!pallet || typeof pallet !== 'object') return false;
            
            return (pallet.pallet_name && pallet.pallet_name.toLowerCase().includes(searchTerm)) ||
                   (pallet.primary_contact_name && pallet.primary_contact_name.toLowerCase().includes(searchTerm));
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
        
        $('#total-pallets').text(total);
        $('#unpaid-pallets').text(unpaid);
        $('#eu-pallets').text(eu);
        $('#us-pallets').text(us);
    }
    
    function openModal(pallet = null) {
        editingPallet = pallet;
        
        if (pallet) {
            $('#modal-title').text('Edit Pallet Storage');
            populateForm(pallet);
        } else {
            $('#modal-title').text('Add New Pallet Storage');
            resetForm();
        }
        
        $('#pallet-modal').show();
    }
    
    function closeModal() {
        $('#pallet-modal').hide();
        editingPallet = null;
        resetForm();
    }
    
    function populateForm(pallet) {
        $('#pallet-id').val(pallet.id);
        $('#pallet-name').val(pallet.pallet_name);
        $('#pallet-type').val(pallet.pallet_type || 'EU');
        $('#actual-height').val(pallet.actual_height || '');
        $('#period-from').val(pallet.period_from || '');
        $('#period-until').val(pallet.period_until || '');
        $('#payment-status').val(pallet.payment_status || 'paid');
        ('#customer-id').val(pallet.customer_id || ''); 
        $('#secondary-name').val(pallet.secondary_contact_name || '');
        $('#secondary-phone').val(pallet.secondary_contact_phone || '');
        $('#secondary-whatsapp').val(pallet.secondary_contact_whatsapp || '');
        $('#secondary-email').val(pallet.secondary_contact_email || '');
        
        const hasSecondary = pallet.secondary_contact_name && pallet.secondary_contact_name.trim() !== '';
        $('#has-secondary-contact').prop('checked', hasSecondary);
        
        toggleSecondaryContact();
    }
    
    function resetForm() {
        $('#pallet-form')[0].reset();
        $('#pallet-id').val('');
        $('#has-secondary-contact').prop('checked', false);
        $('#payment-status').val('paid');
        $('#pallet-type').val('EU');
        toggleSecondaryContact();
    }
    
    function toggleSecondaryContact() {
        const hasSecondary = $('#has-secondary-contact').is(':checked');
        $('#secondary-contact-section').toggle(hasSecondary);
    }
    
    $('#has-secondary-contact').on('change', function() {
        toggleSecondaryContact();
    });
    
    function generatePalletName(customerName) {
        $.ajax({
            url: sum_pallet_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_generate_pallet_name',
                nonce: sum_pallet_ajax.nonce,
                customer_name: customerName
            },
            success: function(response) {
                if (response.success) {
                    $('#pallet-name').val(response.data);
                }
            }
        });
    }
    
    function calculatePrice() {
        // This would calculate based on height and type
        // For now, we'll let the server handle this
    }
    
     // --- NEW: Customer Modal Functions ---

    function openCustomerModal() {
        // Hide the Unit modal and show the Customer modal
        $('#unit-modal').hide();
        $('#customer-creation-modal').show();
        // Reset the form for new entry
        $('#customer-creation-form')[0].reset();
    }

    function closeCustomerModal() {
        // Hide the Customer modal and return to the Unit modal
        $('#customer-creation-modal').hide();
        // Only show unit modal if we were editing/adding a unit
        if ($('#unit-modal').is(':hidden')) {
            $('#unit-modal').show();
        }
    }
    
    
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
                // Reload the customer list and select the new one
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
    function savePallet() {
        const formData = $('#pallet-form').serialize();
        
        $.ajax({
            url: sum_pallet_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=sum_save_pallet&nonce=' + sum_pallet_ajax.nonce,
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
            url: sum_pallet_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_delete_pallet',
                nonce: sum_pallet_ajax.nonce,
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
            url: sum_pallet_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_send_pallet_invoice',
                nonce: sum_pallet_ajax.nonce,
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
            url: sum_pallet_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_regenerate_pallet_pdf',
                nonce: sum_pallet_ajax.nonce,
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
        alert('Success: ' + message);
    }

    function showError(message) {
        alert('Error: ' + message);
    }

    function sendIntakeLink(palletId) {
        if (!confirm('Send intake form link to this customer?')) {
            return;
        }

        $.ajax({
            url: sum_pallet_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sum_send_intake_link',
                nonce: sum_pallet_ajax.nonce,
                unit_id: palletId,
                type: 'pallet'
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message || 'Intake link sent successfully');
                } else {
                    showError(response.data.message || 'Failed to send intake link');
                }
            },
            error: function() {
                showError('Failed to send intake link');
            }
        });
    }

    $(document).on('click', '.send-intake-link-btn', function() {
        const palletId = $(this).data('pallet-id');
        sendIntakeLink(palletId);
    });
});