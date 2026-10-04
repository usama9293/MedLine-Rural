document.addEventListener('DOMContentLoaded', () => {
    const state = { requests: [], inventory: [] };
    const elements = {
        tableBody: document.querySelector('#requests-table-body'), empty: document.querySelector('#requests-empty'), count: document.querySelector('#requests-count'),
        search: document.querySelector('#request-search'), statusFilter: document.querySelector('#request-status-filter'), feedback: document.querySelector('#requests-feedback'),
        refreshButton: document.querySelector('#refresh-requests-button'), openCreateButton: document.querySelector('#open-create-request-button'),
        detailsModal: document.querySelector('#request-details-modal'), detailsContent: document.querySelector('#request-details-content'),
        createModal: document.querySelector('#create-request-modal'), createForm: document.querySelector('#create-request-form'), createError: document.querySelector('#create-request-error'),
        item: document.querySelector('#request-item'), quantity: document.querySelector('#request-quantity'), requiredBy: document.querySelector('#request-required-by'), notes: document.querySelector('#request-notes'),
        inventoryStatus: document.querySelector('#inventory-status'), submitButton: document.querySelector('#submit-request-button'),
        statTotal: document.querySelector('#stat-total'), statPending: document.querySelector('#stat-pending'), statApproved: document.querySelector('#stat-approved'), statCompleted: document.querySelector('#stat-completed'), statCancelled: document.querySelector('#stat-cancelled')
    };
    const detailsModal = new bootstrap.Modal(elements.detailsModal);
    const createModal = new bootstrap.Modal(elements.createModal);

    function errorMessage(error, fallback) {
        const backend = error.message && error.message !== 'The request could not be completed.' ? error.message : '';
        return ({ 401: 'Your session has expired. Please sign in again.', 403: 'You do not have permission to access clinic supply requests.', 404: 'The requested resource was not found.', 405: 'That action is not allowed.', 422: backend || 'Please check the request information.', 500: 'The server could not complete the request.' })[error.status] || backend || fallback;
    }

    function handleAuthError(error) { if (error.status === 401) window.location.href = '../frontend/login.php'; }
    function displayValue(value) { return value === null || value === undefined || String(value).trim() === '' ? 'Not provided' : String(value); }
    function hasScore(score) { return score !== null && score !== undefined && score !== ''; }
    function urgencyCategory(score) { if (!hasScore(score)) return 'Not Scored'; const value = Number(score); if (value >= 80) return 'Critical'; if (value >= 60) return 'High'; if (value >= 40) return 'Medium'; return 'Low'; }
    function statusClass(status) { return ({ Pending: 'badge-soft-warning', Approved: 'badge-soft-info', Completed: 'badge-soft-success', Cancelled: 'badge-soft-danger' })[status] || 'badge-soft-info'; }
    function urgencyClass(score) { return ({ Critical: 'badge-soft-danger', High: 'badge-soft-warning', Medium: 'badge-soft-info', Low: 'badge-soft-success', 'Not Scored': 'badge-soft-warning' })[urgencyCategory(score)]; }
    function yesNo(value) { return Number(value) === 1 ? 'Yes' : 'No'; }
    function setBusy(button, busy, label) { button.disabled = busy; if (busy) button.innerHTML = `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${label}`; else button.innerHTML = '<i class="bi bi-send me-2" aria-hidden="true"></i>Create Request>'; }
    function showFeedback(message, type = 'success') { elements.feedback.className = `alert alert-${type}`; elements.feedback.textContent = message; }

    function filteredRequests() {
        const query = elements.search.value.trim().toLowerCase();
        const status = elements.statusFilter.value;
        return state.requests.filter(request => (!query || [request.request_id, request.clinic_name, request.requested_by, request.notes].some(value => String(value ?? '').toLowerCase().includes(query))) && (!status || request.status === status));
    }
    function updateSummary() {
        const counts = { Pending: 0, Approved: 0, Completed: 0, Cancelled: 0 };
        state.requests.forEach(request => { if (Object.prototype.hasOwnProperty.call(counts, request.status)) counts[request.status] += 1; });
        elements.statTotal.textContent = state.requests.length; elements.statPending.textContent = counts.Pending; elements.statApproved.textContent = counts.Approved; elements.statCompleted.textContent = counts.Completed; elements.statCancelled.textContent = counts.Cancelled;
    }
    function createRow(request) {
        const row = document.createElement('tr');
        const values = [request.request_id, request.status, request.urgency_score, request.requested_at, request.required_by];
        const idCell = document.createElement('td'); idCell.textContent = `#${request.request_id}`;
        const statusCell = document.createElement('td'); statusCell.innerHTML = `<span class="badge ${statusClass(request.status)}"></span>`; statusCell.firstChild.textContent = displayValue(request.status);
        const urgencyCell = document.createElement('td'); urgencyCell.innerHTML = `<span class="badge ${urgencyClass(request.urgency_score)}"></span>`; urgencyCell.firstChild.textContent = hasScore(request.urgency_score) ? `${request.urgency_score} · ${urgencyCategory(request.urgency_score)}` : 'Not Scored';
        const requestedAtCell = document.createElement('td'); requestedAtCell.textContent = displayValue(values[3]);
        const requiredByCell = document.createElement('td'); requiredByCell.textContent = displayValue(values[4]);
        const actionsCell = document.createElement('td'); actionsCell.className = 'text-end text-nowrap';
        const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-secondary users-action'; button.dataset.requestId = request.request_id; button.title = 'View details'; button.setAttribute('aria-label', `View details for request ${request.request_id}`); button.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">View details</span>'; actionsCell.append(button);
        row.append(idCell, statusCell, urgencyCell, requestedAtCell, requiredByCell, actionsCell); return row;
    }
    function renderRequests() { const requests = filteredRequests(); elements.tableBody.replaceChildren(...requests.map(createRow)); elements.tableBody.classList.toggle('d-none', requests.length === 0); elements.empty.classList.toggle('d-none', requests.length > 0); elements.count.textContent = `${requests.length} of ${state.requests.length} requests`; updateSummary(); }

    async function loadRequests() {
        elements.tableBody.classList.remove('d-none'); elements.empty.classList.add('d-none'); elements.tableBody.innerHTML = '<tr><td colspan="6"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading requests...</span></td></tr>';
        try { const response = await MedLineAPI.requestJson('requests/get.php'); state.requests = Array.isArray(response.data) ? response.data : []; renderRequests(); }
        catch (error) { elements.count.textContent = 'Unable to load requests'; elements.tableBody.innerHTML = `<tr><td colspan="6"><div class="error-state"><i class="bi bi-exclamation-circle error-state-icon" aria-hidden="true"></i><p class="mb-3"></p><button class="btn btn-primary btn-sm" type="button" id="retry-requests"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Retry</button></div></td></tr>`; elements.tableBody.querySelector('p').textContent = errorMessage(error, 'Unable to load supply requests. Please try again.'); elements.tableBody.querySelector('#retry-requests').addEventListener('click', loadRequests); handleAuthError(error); }
    }
    function detailRow(label, value) { const row = document.createElement('div'); row.className = 'user-detail-row'; const key = document.createElement('span'); key.textContent = label; const content = document.createElement('strong'); content.textContent = displayValue(value); row.append(key, content); return row; }
    async function showDetails(requestId) {
        elements.detailsContent.innerHTML = '<p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading request details...</p>'; detailsModal.show();
        try { const response = await MedLineAPI.requestJson(`requests/details.php?request_id=${encodeURIComponent(requestId)}`); const request = response.data || {}; const fragment = document.createDocumentFragment(); [['Request ID', request.request_id], ['Clinic', request.clinic_name], ['Requested By ID', request.requested_by], ['Status', request.status], ['Urgency', hasScore(request.urgency_score) ? request.urgency_score : 'Not Scored'], ['Requested At', request.requested_at], ['Required By', request.required_by], ['Notes', request.notes]].forEach(([label, value]) => fragment.append(detailRow(label, value))); const items = Array.isArray(request.items) ? request.items : []; if (!items.length) fragment.append(detailRow('Items', 'No items returned')); items.forEach((item, index) => { const heading = document.createElement('h3'); heading.className = 'section-title mt-4 mb-2'; heading.textContent = items.length > 1 ? `Item ${index + 1}` : 'Requested item'; fragment.append(heading); [['Item name', item.item_name], ['Category', item.category], ['Quantity', item.quantity_requested], ['Criticality', item.criticality_score], ['Cold-chain required', yesNo(item.cold_chain_required)], ['Minimum temperature', item.minimum_temperature], ['Maximum temperature', item.maximum_temperature]].forEach(([label, value]) => fragment.append(detailRow(label, value))); }); elements.detailsContent.replaceChildren(fragment); }
        catch (error) { elements.detailsContent.innerHTML = '<div class="alert alert-danger mb-0"></div>'; elements.detailsContent.firstChild.textContent = errorMessage(error, 'Unable to load request details.'); handleAuthError(error); }
    }

    function inventoryItems(response) { const data = response && response.data; if (Array.isArray(data)) return data; if (data && Array.isArray(data.items)) return data.items; return []; }
    function inventoryLabel(item) { const parts = [item.item_name || item.name, item.category].filter(Boolean); if (item.quantity_available !== undefined && item.quantity_available !== null) parts.push(`Available: ${item.quantity_available}`); return parts.join(' · ') || `Item #${item.item_id}`; }
    async function loadInventory() {
        elements.item.disabled = true; elements.item.innerHTML = '<option value="">Loading inventory...</option>'; elements.inventoryStatus.textContent = '';
        try { const response = await MedLineAPI.requestJson('inventory/get.php'); state.inventory = inventoryItems(response).filter(item => item && item.item_id !== undefined && item.item_id !== null); elements.item.replaceChildren(new Option('Select an item', '')); state.inventory.forEach(item => elements.item.add(new Option(inventoryLabel(item), item.item_id))); elements.item.disabled = state.inventory.length === 0; elements.submitButton.disabled = state.inventory.length === 0; elements.inventoryStatus.textContent = state.inventory.length ? `${state.inventory.length} item${state.inventory.length === 1 ? '' : 's'} available.` : 'No inventory items are available for request creation.'; }
        catch (error) { elements.item.innerHTML = '<option value="">Inventory unavailable</option>'; elements.inventoryStatus.textContent = errorMessage(error, 'Unable to load inventory items.'); elements.submitButton.disabled = true; handleAuthError(error); }
    }
    function resetForm() { elements.createForm.reset(); elements.createForm.classList.remove('was-validated'); elements.createError.classList.add('d-none'); elements.submitButton.disabled = state.inventory.length === 0; }
    function futureRequiredBy() { if (!elements.requiredBy.value) return true; return new Date(elements.requiredBy.value).getTime() > Date.now(); }
    function validForm() { const quantity = Number(elements.quantity.value); const valid = Boolean(elements.item.value) && Number.isInteger(quantity) && quantity > 0 && quantity <= 1000000 && futureRequiredBy(); elements.requiredBy.setCustomValidity(futureRequiredBy() ? '' : 'Required By must be in the future.'); elements.createForm.classList.add('was-validated'); return valid && elements.createForm.checkValidity(); }
    async function submitRequest(event) { event.preventDefault(); elements.createError.classList.add('d-none'); if (!validForm()) return; setBusy(elements.submitButton, true, 'Creating...'); const requiredBy = elements.requiredBy.value ? elements.requiredBy.value.replace('T', ' ') + ':00' : ''; try { const response = await MedLineAPI.requestJson('requests/create.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ item_id: Number(elements.item.value), quantity: Number(elements.quantity.value), required_by: requiredBy, notes: elements.notes.value.trim() }) }); createModal.hide(); resetForm(); showFeedback(response.message || 'Supply request created successfully.'); await loadRequests(); }
        catch (error) { elements.createError.textContent = errorMessage(error, 'Unable to create supply request.'); elements.createError.classList.remove('d-none'); handleAuthError(error); }
        finally { setBusy(elements.submitButton, false); if (!state.inventory.length) elements.submitButton.disabled = true; }
    }

    elements.search.addEventListener('input', renderRequests); elements.statusFilter.addEventListener('change', renderRequests); elements.refreshButton.addEventListener('click', loadRequests); elements.openCreateButton.addEventListener('click', () => { resetForm(); createModal.show(); loadInventory(); }); elements.createForm.addEventListener('submit', submitRequest); elements.tableBody.addEventListener('click', event => { const button = event.target.closest('[data-request-id]'); if (button) showDetails(button.dataset.requestId); });
    loadRequests();
});