// Rendering and Filtering logic
// Assumes sumState and all UI/Action functions are available globally or via a shared namespace.

function toggleViewMode(mode) {
    const $wrapper = jQuery('#frontend-units-view-wrapper');
    const $gridBtn = jQuery('#frontend-view-grid');
    const $listBtn = jQuery('#frontend-view-list');
    
    if (mode === 'list') {
        $wrapper.removeClass('sum-view-mode-grid').addClass('sum-view-mode-list');
        $listBtn.addClass('active').removeClass('sum-frontend-btn-secondary');
        $gridBtn.removeClass('active').addClass('sum-frontend-btn-secondary');
        window.sumState.currentViewMode = 'list';
    } else {
        $wrapper.removeClass('sum-view-mode-list').addClass('sum-view-mode-grid');
        $gridBtn.addClass('active').removeClass('sum-frontend-btn-secondary');
        $listBtn.removeClass('active').addClass('sum-frontend-btn-secondary');
        window.sumState.currentViewMode = 'grid';
    }
    
    // Rerender units to ensure the correct view is populated
    renderUnits();
}

function getFilteredUnits(units) {
    const searchTerm = jQuery('#frontend-search-units').val().toLowerCase();
    const filterStatus = jQuery('#frontend-filter-status').val();
    
    if (!units || !Array.isArray(units)) { return []; }
    
    return units.filter(function(unit) {
        if (!unit || typeof unit !== 'object') return false;
        
        const matchesSearch = unit.unit_name.toLowerCase().includes(searchTerm) ||
                            (unit.website_name && unit.website_name.toLowerCase().includes(searchTerm)) ||
                            (unit.customer_name && unit.customer_name.toLowerCase().includes(searchTerm));
        
        let matchesFilter = true;
        
        if (filterStatus === 'occupied') {
            matchesFilter = parseInt(unit.is_occupied);
        } else if (filterStatus === 'available') {
            matchesFilter = !parseInt(unit.is_occupied);
        } else if (filterStatus === 'past_due') {
            if (parseInt(unit.is_occupied) && unit.period_until) {
                const today = new Date();
                const endDate = new Date(unit.period_until);
                matchesFilter = endDate < today;
            } else {
                matchesFilter = false;
            }
        } else if (filterStatus === 'unpaid') {
            matchesFilter = parseInt(unit.is_occupied) && unit.payment_status !== 'paid';
        }
        
        return matchesSearch && matchesFilter;
    });
}

function filterUnits(units) {
    renderUnits(units);
}

function renderUnits() {
    const units = window.sumState.getUnits();
    const filteredUnits = getFilteredUnits(units);
    const $grid = jQuery('#frontend-units-grid');
    const $tableBody = jQuery('#frontend-units-table-body');
    const $noUnits = jQuery('#frontend-no-units');
    
    $grid.empty();
    $tableBody.empty();
    
    if (filteredUnits.length === 0) {
        // If the total list is empty OR a filter yields no results
        const showNoUnits = jQuery('#frontend-search-units').val() === '' && jQuery('#frontend-filter-status').val() === 'all';

        jQuery('#frontend-units-view-wrapper').toggle(!showNoUnits);
        $noUnits.toggle(showNoUnits);
        
        // Hide grid/table if no filtered units, but show 'no results' if not on initial load.
        if (!showNoUnits) {
             $grid.html('<p class="sum-frontend-no-results">No units match your current filter and search criteria.</p>');
             $tableBody.html('<tr><td colspan="8" class="sum-frontend-no-results">No units match your current filter and search criteria.</td></tr>');
        }
        
        return;
    }
    
    $noUnits.hide();
    jQuery('#frontend-units-view-wrapper').show();

    let gridHtml = '';
    let tableHtml = '';

    filteredUnits.forEach(function(unit) {
        gridHtml += renderUnitCard(unit);
        tableHtml += renderUnitRow(unit);
    });
    
    $grid.html(gridHtml);
    $tableBody.html(tableHtml);
    
    // Bind events for edit/delete/toggle on both views
    bindEvents();
}

/**
 * Calculates the number of occupied months between two dates.
 */
function calculateOccupiedMonths(startDateStr, endDateStr) {
    if (!startDateStr || !endDateStr) return 0;
    try {
        const startDate = new Date(startDateStr);
        const endDate = new Date(endDateStr);
        if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || endDate < startDate) return 0;
        const startYear = startDate.getFullYear();
        const startMonth = startDate.getMonth();
        const endYear = endDate.getFullYear();
        const endMonth = endDate.getMonth();
        return (endYear - startYear) * 12 + (endMonth - startMonth) + 1;
    } catch (e) { return 0; }
}

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

function renderUnitRow(unit) {
    const isOccupied = parseInt(unit.is_occupied);
    const monthlyPrice = unit.monthly_price ? `€${parseFloat(unit.monthly_price).toFixed(2)}` : 'N/A';
    const sizeSqm = unit.size ? unit.size : (unit.sqm ? `${unit.sqm} m²` : 'N/A');
    
    let statusDisplay = isOccupied ? 'Occupied' : 'Available';
    let statusClass = isOccupied ? 'sum-table-status-occupied' : 'sum-table-status-available';

    if (isOccupied) {
        const paymentStatus = unit.payment_status || 'paid';
        statusDisplay = paymentStatus.charAt(0).toUpperCase() + paymentStatus.slice(1);
        statusClass = paymentStatus === 'paid' ? 'sum-table-status-paid' : 
                      paymentStatus === 'overdue' ? 'sum-table-status-overdue' : 'sum-table-status-unpaid';

        // Check if past due
        if (unit.period_until) {
            const today = new Date();
            const endDate = new Date(unit.period_until);
            if (endDate < today && paymentStatus !== 'paid') {
                statusDisplay = 'Past Due';
                statusClass = 'sum-table-status-past-due'; // Use a dedicated past-due class for clarity
            }
        }
    }
    
    // Actions column buttons
    const actions = `
        <div class="sum-table-actions">
            <button type="button" class="frontend-edit-unit sum-frontend-card-btn" data-unit-id="${unit.id}" title="Edit">✏️</button>
            <button type="button" class="frontend-delete-unit sum-frontend-card-btn" data-unit-id="${unit.id}" title="Delete">🗑️</button>
        </div>
    `;

    return `
        <tr data-unit-id="${unit.id}">
            <td>${unit.id}</td>
            <td>${unit.unit_name}</td>
            <td>${sizeSqm}</td>
            <td>${unit.primary_contact_name || 'N/A'}</td>
            <td>${unit.period_until || 'N/A'}</td>
            <td>${monthlyPrice}</td>
            <td><span class="sum-table-status-badge ${statusClass}">${statusDisplay}</span></td>
            <td>${actions}</td>
        </tr>
    `;
}


function renderUnitCard(unit) {
  const esc = (v) =>
    String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  const isAssigned = Boolean(Number(unit.is_occupied) || unit.customer_id);
  const totalMonths = calculateOccupiedMonths(unit.period_from, unit.period_until);

  // --- status mapping (snake_case → Title Case) ---
  const rawStatus = isAssigned ? (unit.payment_status || 'occupied') : 'available';
  const statusMap = { paid: 'paid', unpaid: 'unpaid', overdue: 'overdue', assigned: 'assigned', occupied: 'assigned', available: 'available' };
  const statusClass = statusMap[rawStatus] || 'assigned';
  const statusText = esc(rawStatus.replace(/_/g, ' ').replace(/\b\w/g, s => s.toUpperCase()));

  // --- size / area ---
  const sizeInfo = [unit.size, unit.sqm ? `${esc(unit.sqm)} m²` : null].filter(Boolean).join(' / ');

  // --- customer fields (canonical first, then fallbacks) ---
  const customerName =
    unit.full_name ||
    unit.customer_name ||
    unit.primary_contact_name ||
    'N/A';

  const customerEmail =
    unit.email ||
    unit.customer_email ||
    unit.primary_contact_email ||
    '';

  // --- currency formatting ---
  const currency =
    unit.currency ||
    (window.sumSettings && window.sumSettings.currency) ||
    'EUR';
  let priceText = '';
  try {
    priceText = new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(parseFloat(unit.monthly_price || 0));
  } catch {
    // Fallback to symbol heuristic if Intl or code is odd
    const sym = currency === 'USD' ? '$' : currency === 'GBP' ? '£' : '€';
    priceText = sym + parseFloat(unit.monthly_price || 0).toFixed(2);
  }

  const canEmail = isAssigned && !!customerEmail;

  return `
    <div class="sum-frontend-card" data-unit-id="${esc(unit.id)}" data-customer-id="${esc(unit.customer_id || '')}">
      <div class="sum-frontend-card-header">
        <div class="sum-frontend-card-title">
          <span class="sum-frontend-icon">📦</span>
          <h3>${esc(unit.unit_name)}</h3>
        </div>
        <div class="sum-frontend-card-status-badge ${esc(statusClass)}">${statusText}</div>
      </div>

      <div class="sum-frontend-card-body">
        <div class="sum-frontend-detail-row">
          <span class="sum-frontend-detail-label">Customer</span>
          <span class="sum-frontend-detail-value">
            ${isAssigned
              ? `${esc(customerName)}`
              : '—'}
          </span>
        </div>

        <div class="sum-frontend-detail-row">
          <span class="sum-frontend-detail-label">Size</span>
          <span class="sum-frontend-detail-value">${sizeInfo || 'N/A'}</span>
        </div>

        <div class="sum-frontend-period-row">
          <div class="sum-frontend-period-item">
            <span class="sum-frontend-detail-label">From</span><br/>
            <span class="sum-frontend-detail-value">${formatDate(unit.period_from) || '—'}</span>
          </div>
          <div class="sum-frontend-period-item">
            <span class="sum-frontend-detail-label">Until</span><br/>
            <span class="sum-frontend-detail-value">${formatDate(unit.period_until) || '—'}</span>
          </div>
          <div class="sum-frontend-period-item sum-frontend-period-total">
            <span class="sum-frontend-detail-label">Total</span><br/>
            <span class="sum-frontend-detail-value">${
              totalMonths > 0 ? `${totalMonths} ${totalMonths > 1 ? 'Months' : 'Month'}` : '—'
            }</span>
          </div>
        </div>

        <div class="sum-frontend-detail-row">
          <span class="sum-frontend-detail-label">Price</span>
          <span class="sum-frontend-detail-value">${priceText} / mo</span>
        </div>
      </div>

      <div class="sum-frontend-card-actions">
        <button type="button"
                class="sum-frontend-btn sum-frontend-btn-icon frontend-regenerate-pdf-btn"
                data-unit-id="${esc(unit.id)}"
                title="Download PDF">📄</button>

        <button type="button"
                class="sum-frontend-btn sum-frontend-btn-icon frontend-send-invoice-btn ${canEmail ? '' : 'is-disabled'}"
                data-unit-id="${esc(unit.id)}"
                ${canEmail ? '' : 'disabled'}
                title="${canEmail ? 'Send Invoice' : 'No customer email'}">✉️</button>

        <button type="button" class="sum-frontend-btn sum-frontend-btn-secondary frontend-edit-unit" data-unit-id="${esc(unit.id)}">Edit</button>
        <button type="button" class="sum-frontend-btn sum-frontend-btn-danger frontend-delete-unit" data-unit-id="${esc(unit.id)}">Delete</button>
        ${unit.customer_id ?
                                `<button type="button" class="send-intake-link-btn sum-frontend-btn" data-unit-id="${unit.id}" title="Send Intake Form Link">
                                    <span class="dashicons dashicons-clipboard"></span> Send Intake Link
                                </button>` : ''}
      </div>
    </div>`;
}


function bindEvents() {
    const $ = jQuery;
    const actions = window.sumActions;
    
    // Grid/Table actions
    $('.frontend-edit-unit').off('click').on('click', function() {
        const unitId = $(this).data('unit-id');
        actions.editUnit(unitId);
    });
    
    $('.frontend-delete-unit').off('click').on('click', function() {
        const unitId = $(this).data('unit-id');
        actions.deleteUnit(unitId);
    });
    
    // Card/Contact actions
    $('.frontend-send-invoice-btn').off('click').on('click', function() {
        const unitId = $(this).data('unit-id');
        actions.sendInvoice(unitId);
    });
    
    $('.send-intake-link-btn').off('click').on('click', function() {
        const unitId = $(this).data('unit-id');
        actions.sendIntakeLink(unitId);
    });
    
    $('.frontend-regenerate-pdf-btn').off('click').on('click', function() {
        const unitId = $(this).data('unit-id');
        actions.regeneratePdf(unitId);
    });
    
    $('.frontend-toggle-occupancy').off('click').on('click', function(e) {
        // Prevent event from bubbling up to the table row click if in list view
        e.stopPropagation(); 
        const unitId = $(this).data('unit-id');
        actions.toggleOccupancy(unitId);
    });
    
    // Table row click events (for editing from list view)
    $('.sum-frontend-table tbody tr').off('click').on('click', function(e) {
         // Only trigger if click wasn't on a button/link/toggle
        if (!$(e.target).closest('button, a, input, label').length) {
            const unitId = $(this).data('unit-id');
            if (unitId) {
                actions.editUnit(unitId);
            }
        }
    });
}

// Expose to global scope for use in sum-main.js
window.toggleViewMode = toggleViewMode;
window.filterUnits = filterUnits;
window.renderUnits = renderUnits;
window.bindEvents = bindEvents;