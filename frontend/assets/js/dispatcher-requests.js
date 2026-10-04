document.addEventListener('DOMContentLoaded', () => {
    const state = {
        requests: [],
        queue: [],
        selectedRequestId: null,
        detailsOpen: false
    };

    const elements = {
        tableBody: document.querySelector('#requests-table-body'),
        empty: document.querySelector('#requests-empty'),
        count: document.querySelector('#requests-count'),
        search: document.querySelector('#request-search'),
        statusFilter: document.querySelector('#request-status-filter'),
        feedback: document.querySelector('#requests-feedback'),
        refreshButton: document.querySelector('#refresh-requests-button'),
        queueButton: document.querySelector('#open-queue-button'),
        processButton: document.querySelector('#process-triage-button'),
        detailsModal: document.querySelector('#request-details-modal'),
        detailsContent: document.querySelector('#request-details-content'),
        queueModal: document.querySelector('#triage-queue-modal'),
        queueContent: document.querySelector('#triage-queue-content'),
        calculateModal: document.querySelector('#calculate-result-modal'),
        calculateContent: document.querySelector('#calculate-result-content'),
        confirmModal: document.querySelector('#request-confirm-modal'),
        confirmForm: document.querySelector('#request-confirm-form'),
        confirmTitle: document.querySelector('#request-confirm-title'),
        confirmLabel: document.querySelector('#request-confirm-label'),
        confirmError: document.querySelector('#request-confirm-error'),
        confirmId: document.querySelector('#request-confirm-id'),
        confirmAction: document.querySelector('#request-confirm-action'),
        confirmButton: document.querySelector('#request-confirm-button'),
        statTotal: document.querySelector('#stat-total'),
        statPending: document.querySelector('#stat-pending'),
        statApproved: document.querySelector('#stat-approved'),
        statCompleted: document.querySelector('#stat-completed'),
        statCancelled: document.querySelector('#stat-cancelled')
    };

    const detailsModal = new bootstrap.Modal(elements.detailsModal);
    const queueModal = new bootstrap.Modal(elements.queueModal);
    const calculateModal = new bootstrap.Modal(elements.calculateModal);
    const confirmModal = new bootstrap.Modal(elements.confirmModal);

    elements.refreshButton.dataset.defaultContent = elements.refreshButton.innerHTML;
    elements.queueButton.dataset.defaultContent = elements.queueButton.innerHTML;
    elements.processButton.dataset.defaultContent = elements.processButton.innerHTML;
    elements.confirmButton.dataset.defaultContent = elements.confirmButton.innerHTML;

    function errorMessage(error, fallback) {
        const backendMessage = typeof error.message === 'string' && error.message && error.message !== 'The request could not be completed.'
            ? error.message
            : null;
        const mapped = {
            400: 'Please check the request information and try again.',
            401: 'Your session has expired. Please sign in again.',
            403: 'You do not have permission to manage supply requests.',
            404: 'The requested supply request was not found.',
            409: backendMessage || 'That request cannot be updated in its current state.',
            422: backendMessage || 'The request must be triaged before this action.',
            500: 'The server could not complete the request.'
        };
        return mapped[error.status] || backendMessage || fallback;
    }

    function showFeedback(message, type = 'success') {
        elements.feedback.className = `alert alert-${type}`;
        elements.feedback.textContent = message;
    }

    function setBusy(button, busy, label) {
        if (!button) return;
        if (busy) {
            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${label}`;
            return;
        }
        button.disabled = false;
        button.innerHTML = button.dataset.defaultContent;
    }

    function handleAuthError(error) {
        if (error.status === 401) {
            window.location.href = '../frontend/login.php';
        }
    }

    function displayValue(value) {
        if (value === null || value === undefined || String(value).trim() === '') {
            return 'Not provided';
        }
        return String(value);
    }

    function hasScore(score) {
        return score !== null && score !== undefined && score !== '' && score !== 'null';
    }

    function urgencyCategory(score) {
        if (!hasScore(score)) return 'Not Scored';
        const value = Number(score);
        if (value >= 80) return 'Critical';
        if (value >= 60) return 'High';
        if (value >= 40) return 'Medium';
        return 'Low';
    }

    function statusClass(status) {
        if (status === 'Pending') return 'badge-soft-warning';
        if (status === 'Approved') return 'badge-soft-info';
        if (status === 'Completed') return 'badge-soft-success';
        if (status === 'Cancelled') return 'badge-soft-danger';
        return 'badge-soft-info';
    }

    function urgencyClass(score) {
        const category = urgencyCategory(score);
        if (category === 'Critical') return 'badge-soft-danger';
        if (category === 'High') return 'badge-soft-warning';
        if (category === 'Medium') return 'badge-soft-info';
        if (category === 'Low') return 'badge-soft-success';
        return 'badge-soft-secondary';
    }

    function priorityClass(priority) {
        if (priority === 'Critical') return 'badge-soft-danger';
        if (priority === 'High') return 'badge-soft-warning';
        if (priority === 'Medium') return 'badge-soft-info';
        if (priority === 'Low') return 'badge-soft-success';
        if (priority === 'Needs Triage') return 'badge-soft-secondary';
        return 'badge-soft-warning';
    }

    function getPriorityLabel(request) {
        if (!hasScore(request.urgency_score)) return 'Needs Triage';
        const score = Number(request.urgency_score);
        if (score >= 80) return 'Critical';
        if (score >= 60) return 'High';
        if (score >= 40) return 'Medium';
        return 'Low';
    }

    function detailRow(label, value) {
        const row = document.createElement('div');
        row.className = 'user-detail-row';
        const key = document.createElement('span');
        key.textContent = label;
        const content = document.createElement('strong');
        content.textContent = displayValue(value);
        row.append(key, content);
        return row;
    }

    function filteredRequests() {
        const query = elements.search.value.trim().toLowerCase();
        const status = elements.statusFilter.value;

        return state.requests.filter(request => {
            const matchesStatus = !status || request.status === status;
            const searchable = [
                request.request_id,
                request.clinic_name,
                request.requested_by,
                request.status,
                request.item_name,
                request.urgency_score,
                request.required_by
            ].join(' ').toLowerCase();
            const matchesQuery = !query || searchable.includes(query);
            return matchesStatus && matchesQuery;
        });
    }

    function updateSummary() {
        const counts = { Pending: 0, Approved: 0, Completed: 0, Cancelled: 0 };
        state.requests.forEach(request => {
            if (Object.prototype.hasOwnProperty.call(counts, request.status)) {
                counts[request.status] += 1;
            }
        });

        elements.statTotal.textContent = String(state.requests.length);
        elements.statPending.textContent = String(counts.Pending);
        elements.statApproved.textContent = String(counts.Approved);
        elements.statCompleted.textContent = String(counts.Completed);
        elements.statCancelled.textContent = String(counts.Cancelled);
    }

    function actionButton(label, icon, name, request) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary users-action';
        button.dataset.action = name;
        button.dataset.requestId = request.request_id;
        button.title = label;
        button.setAttribute('aria-label', `${label}: request ${request.request_id}`);
        button.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i><span class="visually-hidden">${label}</span>`;
        button.dataset.defaultContent = button.innerHTML;
        return button;
    }

    function buildRequestRow(request) {
        const row = document.createElement('tr');

        const idCell = document.createElement('td');
        idCell.textContent = `#${request.request_id}`;

        const clinicCell = document.createElement('td');
        clinicCell.textContent = displayValue(request.clinic_name);

        const itemCell = document.createElement('td');
        itemCell.textContent = displayValue(request.item_name);

        const qtyCell = document.createElement('td');
        qtyCell.textContent = displayValue(request.quantity_requested);

        const requiredByCell = document.createElement('td');
        requiredByCell.textContent = displayValue(request.required_by);

        const statusCell = document.createElement('td');
        statusCell.innerHTML = `<span class="badge ${statusClass(request.status)}">${displayValue(request.status)}</span>`;

        const urgencyCell = document.createElement('td');
        if (hasScore(request.urgency_score)) {
            urgencyCell.innerHTML = `<span class="badge ${urgencyClass(request.urgency_score)}">${request.urgency_score} · ${urgencyCategory(request.urgency_score)}</span>`;
        } else {
            urgencyCell.innerHTML = `<span class="badge ${urgencyClass(request.urgency_score)}">Not Scored</span>`;
        }

        const priorityCell = document.createElement('td');
        const priorityValue = getPriorityLabel(request);
        priorityCell.innerHTML = `<span class="badge ${priorityClass(priorityValue)}">${priorityValue}</span>`;

        const actionsCell = document.createElement('td');
        actionsCell.className = 'text-end text-nowrap';
        actionsCell.append(actionButton('View details', 'bi-eye', 'details', request));

        if (request.status === 'Pending') {
            actionsCell.append(actionButton('Calculate urgency', 'bi-calculator', 'calculate', request));
            if (hasScore(request.urgency_score)) {
                actionsCell.append(actionButton('Approve request', 'bi-check2-circle', 'approve', request));
            }
            actionsCell.append(actionButton('Cancel request', 'bi-x-circle', 'cancel', request));
        }

        if (request.status === 'Approved') {
            actionsCell.append(actionButton('Complete request', 'bi-check-lg', 'complete', request));
            actionsCell.append(actionButton('Cancel request', 'bi-x-circle', 'cancel', request));
        }

        row.append(idCell, clinicCell, itemCell, qtyCell, requiredByCell, statusCell, urgencyCell, priorityCell, actionsCell);
        return row;
    }

    function renderRequests() {
        const requests = filteredRequests();
        elements.tableBody.replaceChildren();
        elements.empty.classList.toggle('d-none', requests.length > 0);
        elements.count.textContent = `${requests.length} of ${state.requests.length} requests`;
        elements.tableBody.classList.toggle('d-none', requests.length === 0);
        requests.forEach(request => elements.tableBody.append(buildRequestRow(request)));
        updateSummary();
    }

    async function loadRequests(options = {}) {
        const { quiet = false } = options;

        if (!quiet) {
            elements.tableBody.innerHTML = '<tr><td colspan="9"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading requests...</span></td></tr>';
            elements.tableBody.classList.remove('d-none');
            elements.empty.classList.add('d-none');
        }

        try {
            const response = await MedLineAPI.requestJson('requests/get.php');
            state.requests = Array.isArray(response.data) ? response.data : [];
            state.requests = state.requests.map(request => ({
                ...request,
                item_name: request.item_name || 'Unknown item',
                quantity_requested: request.quantity_requested || null,
                priority: getPriorityLabel(request)
            }));

            renderRequests();
            if (state.detailsOpen && state.selectedRequestId) {
                await showDetails(state.selectedRequestId, { keepOpen: true });
            }
        } catch (error) {
            elements.count.textContent = 'Unable to load requests';
            elements.tableBody.classList.remove('d-none');
            elements.empty.classList.add('d-none');
            elements.tableBody.innerHTML = `<tr><td colspan="9"><div class="error-state"><i class="bi bi-exclamation-circle error-state-icon" aria-hidden="true"></i><p class="mb-3">${errorMessage(error, 'Unable to load supply requests. Please try again.')}</p><button class="btn btn-primary btn-sm" type="button" id="retry-requests"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Retry</button></div></td></tr>`;
            document.querySelector('#retry-requests')?.addEventListener('click', () => loadRequests());
            handleAuthError(error);
        }
    }

    async function showDetails(requestId, options = {}) {
        const { keepOpen = false } = options;
        state.selectedRequestId = requestId;
        state.detailsOpen = true;
        elements.detailsContent.innerHTML = '<p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading request details...</p>';
        if (!keepOpen) detailsModal.show();

        try {
            const response = await MedLineAPI.requestJson(`requests/details.php?request_id=${encodeURIComponent(requestId)}`);
            const request = response.data || {};
            const fragment = document.createDocumentFragment();

            [
                ['Request ID', request.request_id],
                ['Clinic', request.clinic_name],
                ['Requester', request.requested_by],
                ['Status', request.status],
                ['Required By', request.required_by],
                ['Urgency Score', hasScore(request.urgency_score) ? request.urgency_score : 'Not Scored'],
                ['Priority', hasScore(request.urgency_score) ? getPriorityLabel(request) : 'Needs Triage'],
                ['Requested At', request.requested_at],
                ['Updated At', request.updated_at || 'Not provided'],
                ['Notes', request.notes]
            ].forEach(([label, value]) => fragment.append(detailRow(label, value)));

            const items = Array.isArray(request.items) ? request.items : [];
            if (items.length === 0) {
                fragment.append(detailRow('Requested Items', 'No items returned'));
            } else {
                const list = document.createElement('div');
                list.className = 'mt-4';
                list.innerHTML = '<h3 class="section-title mb-3">Requested items</h3>';

                items.forEach((item, index) => {
                    const box = document.createElement('div');
                    box.className = 'border rounded p-3 mb-3';
                    box.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>${displayValue(item.item_name)}</strong>
                            <span class="badge badge-soft-info">Qty ${displayValue(item.quantity_requested)}</span>
                        </div>
                        <div class="user-detail-row"><span>Category</span><strong>${displayValue(item.category)}</strong></div>
                        <div class="user-detail-row"><span>Criticality</span><strong>${displayValue(item.criticality_score)}</strong></div>
                        <div class="user-detail-row"><span>Cold-chain required</span><strong>${item.cold_chain_required === 1 || item.cold_chain_required === '1' ? 'Yes' : 'No'}</strong></div>
                        <div class="user-detail-row"><span>Minimum temperature</span><strong>${displayValue(item.minimum_temperature)}</strong></div>
                        <div class="user-detail-row"><span>Maximum temperature</span><strong>${displayValue(item.maximum_temperature)}</strong></div>
                    `;
                    list.append(box);
                });

                fragment.append(list);
            }

            elements.detailsContent.replaceChildren(fragment);
        } catch (error) {
            elements.detailsContent.innerHTML = `<div class="alert alert-danger mb-0">${errorMessage(error, 'Unable to load request details.')}</div>`;
            handleAuthError(error);
        }
    }

    function renderQueue(requests) {
        if (!requests.length) {
            elements.queueContent.innerHTML = '<div class="empty-state"><i class="bi bi-list-ol empty-state-icon" aria-hidden="true"></i><h3 class="section-title">No requests in the triage queue</h3><p class="text-muted-custom mb-0">Completed and cancelled requests are excluded from this queue.</p></div>';
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        const table = document.createElement('table');
        table.className = 'table users-table align-middle mb-0';
        table.innerHTML = '<thead><tr><th scope="col">Request</th><th scope="col">Clinic</th><th scope="col">Item</th><th scope="col">Qty</th><th scope="col">Status</th><th scope="col">Urgency</th><th scope="col">Priority</th></tr></thead>';
        const body = document.createElement('tbody');

        requests.forEach(request => {
            const row = document.createElement('tr');
            const requestCell = document.createElement('td');
            requestCell.textContent = `#${request.request_id}`;

            const clinicCell = document.createElement('td');
            clinicCell.textContent = displayValue(request.clinic_name);

            const itemCell = document.createElement('td');
            itemCell.textContent = displayValue(request.item_name);

            const qtyCell = document.createElement('td');
            qtyCell.textContent = displayValue(request.quantity_requested);

            const statusCell = document.createElement('td');
            statusCell.innerHTML = `<span class="badge ${statusClass(request.status)}">${displayValue(request.status)}</span>`;

            const urgencyCell = document.createElement('td');
            if (hasScore(request.urgency_score)) {
                urgencyCell.innerHTML = `<span class="badge ${urgencyClass(request.urgency_score)}">${request.urgency_score}</span>`;
            } else {
                urgencyCell.innerHTML = `<span class="badge ${urgencyClass(request.urgency_score)}">Not Scored</span>`;
            }

            const priorityCell = document.createElement('td');
            const priorityValue = request.priority || getPriorityLabel(request);
            priorityCell.innerHTML = `<span class="badge ${priorityClass(priorityValue)}">${priorityValue}</span>`;

            row.append(requestCell, clinicCell, itemCell, qtyCell, statusCell, urgencyCell, priorityCell);
            body.append(row);
        });

        table.append(body);
        wrapper.append(table);
        elements.queueContent.replaceChildren(wrapper);
    }

    async function loadQueue(options = {}) {
        const { show = false } = options;
        if (show) {
            elements.queueContent.innerHTML = '<p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading triage queue...</p>';
            queueModal.show();
        }

        try {
            const response = await MedLineAPI.requestJson('triage/queue.php');
            state.queue = Array.isArray(response.data) ? response.data : [];
            if (show || elements.queueModal.classList.contains('show')) {
                renderQueue(state.queue);
            }
        } catch (error) {
            if (show || elements.queueModal.classList.contains('show')) {
                elements.queueContent.innerHTML = `<div class="alert alert-danger mb-0">${errorMessage(error, 'Unable to load the triage queue.')}</div>`;
            }
            handleAuthError(error);
        }
    }

    function showCalculation(data) {
        const fragment = document.createDocumentFragment();
        fragment.append(detailRow('Request ID', data.request_id));
        fragment.append(detailRow('Urgency Score', data.urgency_score));

        if (data.item) {
            fragment.append(detailRow('Item', data.item.item_name));
        }

        if (data.factors) {
            const heading = document.createElement('h3');
            heading.className = 'section-title mt-4 mb-2';
            heading.textContent = 'Calculation factors';
            fragment.append(heading);
            fragment.append(detailRow('Clinical criticality', data.factors.clinical_criticality));
            fragment.append(detailRow('Stock-out risk', data.factors.stock_out_risk));
            fragment.append(detailRow('Cold-chain risk', data.factors.cold_chain_risk));
            fragment.append(detailRow('Transit risk', data.factors.transit_risk));
        }

        if (data.stock) {
            const heading = document.createElement('h3');
            heading.className = 'section-title mt-4 mb-2';
            heading.textContent = 'Stock snapshot';
            fragment.append(heading);
            fragment.append(detailRow('Quantity available', data.stock.quantity_available));
            fragment.append(detailRow('Average daily usage', data.stock.average_daily_usage));
            fragment.append(detailRow('Days remaining', data.stock.days_remaining));
        }

        elements.calculateContent.replaceChildren(fragment);
        calculateModal.show();
    }

    async function calculateRequest(request, button) {
        setBusy(button, true, 'Calculating...');
        try {
            const response = await MedLineAPI.requestJson(`triage/calculate.php?request_id=${encodeURIComponent(request.request_id)}`, {
                method: 'POST'
            });
            showFeedback(`Urgency score calculated for request #${request.request_id}.`);
            showCalculation(response.data || {});
            await loadRequests({ quiet: true });
            await loadQueue();
        } catch (error) {
            showFeedback(errorMessage(error, 'Unable to calculate urgency.'), 'danger');
            handleAuthError(error);
        } finally {
            setBusy(button, false);
        }
    }

    async function processTriage() {
        setBusy(elements.processButton, true, 'Processing...');
        try {
            const response = await MedLineAPI.requestJson('triage/process.php', { method: 'POST' });
            const data = response.data || {};
            const processed = Number(data.processed_count || 0);
            const failed = Number(data.failed_count || 0);
            let message = '';

            if (processed === 0) {
                message = 'No pending unscored requests were found.';
            } else {
                message = `Triage completed. Processed ${processed} request${processed === 1 ? '' : 's'}.`;
            }

            if (failed > 0) {
                message += ` Failed ${failed}.`;
            }

            showFeedback(message, processed === 0 ? 'warning' : 'success');
            await loadRequests({ quiet: true });
            await loadQueue();
        } catch (error) {
            showFeedback(errorMessage(error, 'Unable to process pending triage.'), 'danger');
            handleAuthError(error);
        } finally {
            setBusy(elements.processButton, false);
        }
    }

    async function approveRequest(request, button) {
        const confirmed = window.confirm(`Approve request #${request.request_id} for ${displayValue(request.clinic_name)}? This will set the request status to Approved.`);
        if (!confirmed) {
            return;
        }

        setBusy(button, true, 'Approving...');
        try {
            const response = await MedLineAPI.requestJson(`triage/approve.php?request_id=${encodeURIComponent(request.request_id)}`, {
                method: 'POST'
            });
            showFeedback(response.message || `Request #${request.request_id} approved.`);
            await loadRequests({ quiet: true });
            await loadQueue();
            if (state.detailsOpen && state.selectedRequestId === request.request_id) {
                await showDetails(request.request_id, { keepOpen: true });
            }
        } catch (error) {
            showFeedback(errorMessage(error, 'Unable to approve this request.'), 'danger');
            handleAuthError(error);
        } finally {
            setBusy(button, false);
        }
    }

    function openConfirm(request, action) {
        state.selectedRequestId = request.request_id;
        elements.confirmId.value = request.request_id;
        elements.confirmAction.value = action;
        elements.confirmError.classList.add('d-none');

        if (action === 'cancel') {
            elements.confirmTitle.textContent = 'Cancel request';
            elements.confirmLabel.textContent = `Cancel supply request #${request.request_id} for ${displayValue(request.clinic_name)}? This cannot be reversed on this page.`;
            elements.confirmButton.textContent = 'Cancel Request';
        } else {
            elements.confirmTitle.textContent = 'Complete request';
            elements.confirmLabel.textContent = `Mark supply request #${request.request_id} as completed?`;
            elements.confirmButton.textContent = 'Complete Request';
        }

        confirmModal.show();
    }

    async function submitConfirm(event) {
        event.preventDefault();
        const requestId = Number(elements.confirmId.value);
        const action = elements.confirmAction.value;
        const status = action === 'cancel' ? 'Cancelled' : 'Completed';

        setBusy(elements.confirmButton, true, 'Updating...');
        try {
            const response = await MedLineAPI.requestJson('requests/update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ request_id: requestId, status })
            });
            confirmModal.hide();
            showFeedback(response.message || `Request #${requestId} updated to ${status}.`);
            await loadRequests({ quiet: true });
            await loadQueue();
            if (state.detailsOpen && state.selectedRequestId === requestId) {
                await showDetails(requestId, { keepOpen: true });
            }
        } catch (error) {
            elements.confirmError.textContent = errorMessage(error, 'Unable to update request status.');
            elements.confirmError.classList.remove('d-none');
            handleAuthError(error);
        } finally {
            setBusy(elements.confirmButton, false);
        }
    }

    elements.search.addEventListener('input', renderRequests);
    elements.statusFilter.addEventListener('change', renderRequests);
    elements.refreshButton.addEventListener('click', () => loadRequests());
    elements.queueButton.addEventListener('click', () => loadQueue({ show: true }));
    elements.processButton.addEventListener('click', processTriage);
    elements.confirmForm.addEventListener('submit', submitConfirm);
    elements.detailsModal.addEventListener('hidden.bs.modal', () => {
        state.detailsOpen = false;
    });

    elements.tableBody.addEventListener('click', event => {
        const button = event.target.closest('[data-action]');
        if (!button) return;

        const request = state.requests.find(item => String(item.request_id) === button.dataset.requestId);
        if (!request) return;

        if (button.dataset.action === 'details') showDetails(request.request_id);
        if (button.dataset.action === 'calculate') calculateRequest(request, button);
        if (button.dataset.action === 'approve') approveRequest(request, button);
        if (button.dataset.action === 'cancel') openConfirm(request, 'cancel');
        if (button.dataset.action === 'complete') openConfirm(request, 'complete');
    });

    loadRequests();
    loadQueue();
});
