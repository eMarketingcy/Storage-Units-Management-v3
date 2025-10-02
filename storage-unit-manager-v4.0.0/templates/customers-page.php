<div class="wrap">
    <h1>Customer Management
        <a href="#" class="page-title-action" id="add-new-customer">Add New Customer</a>
    </h1>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column">Full Name</th>
                <th scope="col" class="manage-column">Email</th>
                <th scope="col" class="manage-column">Address</th>
                <th scope="col" class="manage-column">Actions</th>
            </tr>
        </thead>
        <tbody id="customer-list">
            </tbody>
    </table>

    <div id="customer-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="customer-modal-title">Add New Customer</h2>
            <form id="customer-form">
                <input type="hidden" id="customer-id" name="id">
                <div class="form-field">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                <div class="form-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-field">
                    <label for="full_address">Full Address</label>
                    <textarea id="full_address" name="full_address"></textarea>
                </div>
                <div class="form-field">
                    <label for="upload_id">ID Upload</label>
                    <input type="text" id="upload_id" name="upload_id">
                </div>
                <div class="form-field">
                    <label for="utility_bill">Utility Bill</label>
                    <input type="text" id="utility_bill" name="utility_bill">
                </div>
                <div class="submit">
                    <button type="submit" class="button button-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>