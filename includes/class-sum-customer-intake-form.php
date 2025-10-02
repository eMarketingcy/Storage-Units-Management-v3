/* Storage Unit Manager Admin Styles */

.sum-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.sum-toggle-wrapper input {
    display: none;
}

.sum-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.sum-stat-icon {
    font-size: 24px;
    margin-right: 15px;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

.sum-stat-total { background: #e3f2fd; }
.sum-stat-occupied { background: #ffebee; }
.sum-stat-available { background: #e8f5e8; }
.sum-stat-unpaid { background: #fff3e0; }

.sum-stat-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.sum-stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.sum-controls {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.sum-search-filter {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.sum-action-buttons {
    display: flex;
    gap: 10px;
}

.sum-search-input, .sum-filter-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.sum-search-input {
    width: 250px;
}

.sum-units-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.sum-unit-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s;
}

.sum-unit-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.sum-unit-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.sum-unit-info h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
    color: #333;
}

.sum-unit-info p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.sum-badges {
    margin-top: 10px;
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.sum-payment-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.sum-payment-paid {
    background: #d4edda;
    color: #155724;
}

.sum-payment-unpaid {
    background: #f8d7da;
    color: #721c24;
}

.sum-past-due-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    background: #dc3545;
    color: white;
}

.sum-unit-actions {
    display: flex;
    gap: 5px;
}

.sum-unit-actions button {
    background: none;
    border: none;
    padding: 8px;
    border-radius: 4px;
    cursor: pointer;
    color: #666;
    transition: all 0.2s;
}

.sum-unit-actions button:hover {
    background: #f0f0f0;
}

.sum-unit-actions .edit-btn:hover { color: #0073aa; }
.sum-unit-actions .send-invoice-btn:hover { color: #00a32a; }
.sum-unit-actions .delete-btn:hover { color: #d63638; }

.sum-unit-status {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 15px 0;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.sum-toggle-wrapper {
    position: relative;
    display: inline-block;
}

.sum-toggle-input {
    display: none;
}

.sum-toggle-label {
    display: block;
    width: 44px;
    height: 24px;
    background: #ccc;
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.3s;
    position: relative;
}

.sum-toggle-input:checked + .sum-toggle-label {
    background: #d63638;
}

.sum-toggle-slider {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transition: transform 0.3s;
}

.sum-toggle-input:checked + .sum-toggle-label .sum-toggle-slider {
    transform: translateX(20px);
}

.sum-contact-info {
    background: #f9f9f9;
    border-radius: 4px;
    padding: 15px;
    margin-top: 15px;
}

.sum-contact-info h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 14px;
}

.sum-contact-details {
    font-size: 13px;
    color: #666;
    line-height: 1.5;
}

.sum-contact-details div {
    margin-bottom: 5px;
}

.sum-secondary-contact {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.sum-no-units {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.sum-no-units-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.sum-no-units h3 {
    color: #333;
    margin-bottom: 10px;
}

.sum-no-units p {
    color: #666;
    margin-bottom: 20px;
}

/* Modal Styles */
.sum-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.sum-modal-content {
    background: #fff;
    border-radius: 8px;
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.sum-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #ddd;
    background: #f9f9f9;
    border-radius: 8px 8px 0 0;
}

.sum-modal-header h2 {
    margin: 0;
    color: #333;
}

.sum-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.sum-modal-close:hover {
    background: #f0f0f0;
}

.sum-form {
    padding: 20px;
}

.sum-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.sum-form-group {
    display: flex;
    flex-direction: column;
}

.sum-form-group label {
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.sum-form-group input, .sum-form-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.sum-form-group input:focus, .sum-form-group select:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 2px rgba(0,115,170,0.1);
}

.sum-occupancy-toggle {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
    margin-bottom: 20px;
}

.sum-occupancy-toggle label {
    font-weight: 600;
    color: #333;
}

.sum-occupancy-details {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.sum-contact-section {
    background: #f9f9f9;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
}

.sum-contact-section h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 16px;
}

.sum-secondary-toggle {
    margin-bottom: 15px;
}

.sum-secondary-toggle label {
    display: flex;
    align-items: center;
    cursor: pointer;
    color: #0073aa;
}

.sum-secondary-toggle input {
    margin-right: 8px;
}

.sum-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.sum-bulk-preview {
    background: #e3f2fd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.sum-bulk-preview h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.sum-bulk-preview p {
    margin: 0;
    color: #666;
    font-style: italic;
}

/* Status indicators */
.sum-status-occupied {
    color: #d63638;
    font-weight: 600;
}

.sum-status-available {
    color: #00a32a;
    font-weight: 600;
}

/* Responsive design */
@media (max-width: 768px) {
    .sum-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .sum-search-filter {
        justify-content: stretch;
    }
    
    .sum-search-input {
        width: 100%;
    }
    
    .sum-units-grid {
        grid-template-columns: 1fr;
    }
    
    .sum-form-grid {
        grid-template-columns: 1fr;
    }
    
    .sum-modal {
        padding: 10px;
    }
    
    .sum-unit-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    
}


/* Loading state */
.sum-loading {
    text-align: center;
    padding: 40px;
    color: #666;
}

.sum-loading::after {
    content: '';
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #ddd;
    border-top: 2px solid #0073aa;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.sum-btn-modern {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #00a32a;
    color: white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.sum-btn-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    background: #008a24;
}

.sum-btn-modern:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.sum-btn-modern .dashicons {
    width: 16px;
    height: 16px;
    font-size: 16px;
}

.sum-btn-modern.sum-btn-secondary {
    background: #2271b1;
}

.sum-btn-modern.sum-btn-secondary:hover {
    background: #135e96;
}

.sum-btn-modern.sum-btn-accent {
    background: #f97316;
}

.sum-btn-modern.sum-btn-accent:hover {
    background: #ea580c;
}

.sum-action-buttons {
    margin-top: 12px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

@media (max-width: 480px) {
    .sum-action-buttons {
        flex-direction: column;
    }

    .sum-btn-modern {
        width: 100%;
        justify-content: center;
    }
}