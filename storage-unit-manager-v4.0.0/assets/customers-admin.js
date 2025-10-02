jQuery(document).ready(function($) {
    const modal = $('#customer-modal');
    const modalTitle = $('#customer-modal-title');
    const customerForm = $('#customer-form');
    const customerList = $('#customer-list');
    
    // --- Initial Load ---
    loadCustomers();

    // --- Event Handlers ---

    // Open modal for adding a new customer
    $('#add-new-customer').on('click', function(e) {
        e.preventDefault();
        openModal();
    });

    // Close modal
    modal.on('click', '.close', closeModal);
    $(window).on('click', function(e) {
        if ($(e.target).is(modal)) {
            closeModal();
        }
    });

    // Handle form submission for both create and update
    customerForm.on('submit', saveCustomer);

    // Handle clicks for edit and delete buttons
    customerList.on('click', '.edit-customer-btn', function() {
        const customerData = $(this).closest('tr').data('customer');
        openModal(customerData);
    });

    customerList.on('click', '.delete-customer-btn', function() {
        const customerId = $(this).data('id');
        if (confirm('Are you sure you want to delete this customer? This action cannot be undone.')) {
            deleteCustomer(customerId);
        }
    });

    // --- Core Functions ---

    /**
     * Fetches customers from the server and renders them in the table.
     */
    function loadCustomers() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sum_get_customer_list',
                nonce: sum_customer_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    customerList.empty();
                    response.data.forEach(customer => {
                        customerList.append(renderCustomerRow(customer));
                    });
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An unexpected error occurred while fetching customers.');
            }
        });
    }

    /**
     * Creates the HTML for a single customer row.
     * @param {object} customer - The customer data object.
     * @returns {string} - The HTML string for the table row.
     */
    function renderCustomerRow(customer) {
        const row = `
            <tr id="customer-${customer.id}">
                <td>${customer.full_name}</td>
                <td>${customer.email}</td>
                <td>${customer.full_address || 'N/A'}</td>
                <td>
                    <button class="button edit-customer-btn">Edit</button>
                    <button class="button delete-customer-btn" data-id="${customer.id}">Delete</button>
                </td>
            </tr>
        `;
        const $row = $(row);
        $row.data('customer', customer); // Attach full customer data to the row
        return $row;
    }

    /**
     * Saves a new or existing customer.
     * @param {Event} e - The form submit event.
     */
    function saveCustomer(e) {
        e.preventDefault();
        
        const customerData = {
            id: $('#customer-id').val(),
            full_name: $('#full_name').val(),
            email: $('#email').val(),
            full_address: $('#full_address').val(),
            upload_id: $('#upload_id').val(),
            utility_bill: $('#utility_bill').val(),
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sum_save_customer',
                nonce: sum_customer_admin_vars.nonce,
                customer_data: customerData
            },
            success: function(response) {
                if (response.success) {
                    closeModal();
                    loadCustomers(); // Refresh the list
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An unexpected error occurred while saving the customer.');
            }
        });
    }

    /**
     * Deletes a customer by their ID.
     * @param {number} customerId - The ID of the customer to delete.
     */
    function deleteCustomer(customerId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sum_delete_customer',
                nonce: sum_customer_admin_vars.nonce,
                customer_id: customerId
            },
            success: function(response) {
                if (response.success) {
                    $('#customer-' + customerId).fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An unexpected error occurred while deleting the customer.');
            }
        });
    }

    // --- Modal and Form Helpers ---

    /**
     * Opens and populates the modal.
     * @param {object|null} customer - The customer data to populate the form with. Null for a new customer.
     */
    function openModal(customer = null) {
        if (customer) {
            modalTitle.text('Edit Customer');
            $('#customer-id').val(customer.id);
            $('#full_name').val(customer.full_name);
            $('#email').val(customer.email);
            $('#full_address').val(customer.full_address);
            $('#upload_id').val(customer.upload_id);
            $('#utility_bill').val(customer.utility_bill);
        } else {
            modalTitle.text('Add New Customer');
            customerForm[0].reset();
            $('#customer-id').val('');
        }
        modal.show();
    }

    /**
     * Closes the modal and resets the form.
     */
    function closeModal() {
        modal.hide();
        customerForm[0].reset();
        $('#customer-id').val('');
    }
});