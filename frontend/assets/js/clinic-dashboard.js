document.addEventListener('DOMContentLoaded', () => {
    const state = {
        requests: [],
        stockRisk: []
    };

    const elements = {
        refreshButton: document.querySelector('#refresh-dashboard-button'),
        retryButton: document.querySelector('#retry-dashboard'),
        dashboardError: document.querySelector('#dashboard-error'),
        errorMessage: document.querySelector('#dashboard-error-message'),
        statTotal: document.querySelector('#stat-total'),
        statPending: document.querySelector('#stat-pending'),
        statApproved: document.querySelector('#stat-approved'),
        statCompleted: document.querySelector('#stat-completed'),
        statCancelled: document.querySelector('#stat-cancelled'),
        recentTableBody: document.querySelector('#recent-requests-table-body'),
        recentEmpty: document.querySelector('#recent-requests-empty'),
        riskTableBody: document.querySelector('#stock-risk-table-body'),
        riskEmpty: document.querySelector('#stock-risk-empty'),
        detailsModal: document.querySelector('#request-details-modal'),
        detailsContent: document.querySelector('#request-details-content')
    };

    const detailsModal = new bootstrap.Modal(elements.detailsModal);

    function displayValue(value) {
        return value === null || value === undefined || String(value).trim() === '' ? 'Not provided' : String(value);
    }

    function hasScore(score) {
        return score !== null && score !== undefined && score !== '';
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
        return ({
            Pending: 'badge-soft-warning',
            Approved: 'badge-soft-info',
            Completed: 'badge-soft-success',
            Cancelled: 'badge-soft-danger'
        })[status] || 'badge-soft-info';
    }

    function urgencyClass(score) {
        return ({
            Critical: 'badge-soft-danger',
            High: 'badge-soft-warning',
            Medium: 'badge-soft-info',
            Low: 'badge-soft-success',
            'Not Scored': 'badge-soft-warning'
        })[urgencyCategory(score)];
    }

    function riskClass(risk) {
        return ({
            Critical: 'badge-soft-danger',
            High: 'badge-soft-warning',
            Medium: 'badge-soft-info',
            Low: 'badge-soft-success',
            Unknown: 'badge-soft-warning'
        })[risk] || 'badge-soft-info';
    }

    function yesNo(value) {
        return Number(value) === 1 ? 'Yes' : 'No';
    }

    function handleAuthError(error) {
        if (error.status === 401) {
            window.location.href = '../frontend/login.php';
        }
    }

    function updateSummary() {
        const counts = { Pending: 0, Approved: 0, Completed: 0, Cancelled: 0 };
        state.requests.forEach(request => {
            if (Object.prototype.hasOwnProperty.call(counts, request.status)) {
                counts[request.status] += 1;
            }
        });
        elements.statTotal.textContent = state.requests.length;
        elements.statPending.textContent = counts.Pending;
        elements.statApproved.textContent = counts.Approved;
        elements.statCompleted.textContent = counts.Completed;
        elements.statCancelled.textContent = counts.Cancelled;
    }

    function createRecentRequestRow(request) {
        const row = document.createElement('tr');

        const idCell = document.createElement('td');
        idCell.textContent = `#${request.request_id}`;

        const statusCell = document.createElement('td');
        const statusBadge = document.createElement('span');
        statusBadge.className = `badge ${statusClass(request.status)}`;
        statusBadge.textContent = displayValue(request.status);
        statusCell.appendChild(statusBadge);

        const urgencyCell = document.createElement('td');
        const urgencyBadge = document.createElement('span');
        urgencyBadge.className = `badge ${urgencyClass(request.urgency_score)}`;
        urgencyBadge.textContent = hasScore(request.urgency_score)
            ? `${request.urgency_score} · ${urgencyCategory(request.urgency_score)}`
            : 'Not Scored';
        urgencyCell.appendChild(urgencyBadge);

        const requestedAtCell = document.createElement('td');
        requestedAtCell.textContent = displayValue(request.requested_at);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'text-end text-nowrap';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary users-action';
        button.dataset.requestId = request.request_id;
        button.title = 'View details';
        button.setAttribute('aria-label', `View details for request ${request.request_id}`);
        button.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">View details</span>';
        actionsCell.append(button);

        row.append(idCell, statusCell, urgencyCell, requestedAtCell, actionsCell);
        return row;
    }

    function renderRecentRequests() {
        const recent = state.requests.slice(0, 5);
        elements.recentTableBody.replaceChildren(...recent.map(createRecentRequestRow));
        elements.recentTableBody.classList.toggle('d-none', recent.length === 0);
        elements.recentEmpty.classList.toggle('d-none', recent.length > 0);
    }

    function createStockRiskRow(item) {
        const row = document.createElement('tr');

        const itemCell = document.createElement('td');
        itemCell.textContent = displayValue(item.item_name);

        const categoryCell = document.createElement('td');
        categoryCell.textContent = displayValue(item.category);

        const availableCell = document.createElement('td');
        availableCell.textContent = displayValue(item.quantity_available);

        const daysCell = document.createElement('td');
        daysCell.textContent = item.days_remaining !== null && item.days_remaining !== undefined
            ? `${item.days_remaining} days`
            : 'N/A';

        const riskCell = document.createElement('td');
        const riskBadge = document.createElement('span');
        riskBadge.className = `badge ${riskClass(item.stock_out_risk)}`;
        riskBadge.textContent = displayValue(item.stock_out_risk);
        riskCell.appendChild(riskBadge);

        row.append(itemCell, categoryCell, availableCell, daysCell, riskCell);
        return row;
    }

    function renderStockRisk() {
        const preview = state.stockRisk.slice(0, 5);
        elements.riskTableBody.replaceChildren(...preview.map(createStockRiskRow));
        elements.riskTableBody.classList.toggle('d-none', preview.length === 0);
        elements.riskEmpty.classList.toggle('d-none', preview.length > 0);
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

    const clinicCharts = {
        requests: null,
        stockRisk: null
    };

    function isChartAvailable() {
        return typeof window.Chart !== 'undefined';
    }

    function destroyChart(chartRef, canvasEl) {
        if (chartRef) {
            try { chartRef.destroy(); } catch (e) {}
        }
        if (window.Chart && canvasEl) {
            try {
                const existing = window.Chart.getChart(canvasEl);
                if (existing) existing.destroy();
            } catch (e) {}
        }
    }

    function renderClinicCharts() {
        const reqCanvas = document.getElementById('chart-clinic-requests');
        const reqEmpty = document.getElementById('empty-clinic-requests');
        const riskCanvas = document.getElementById('chart-clinic-stock-risk');
        const riskEmpty = document.getElementById('empty-clinic-stock-risk');

        if (!isChartAvailable()) {
            console.warn('Chart.js library is not available. Skipping chart render.');
            if (reqCanvas) reqCanvas.classList.add('d-none');
            if (reqEmpty) {
                reqEmpty.classList.remove('d-none');
                const p = reqEmpty.querySelector('p');
                if (p) p.textContent = 'Chart library unavailable';
            }
            if (riskCanvas) riskCanvas.classList.add('d-none');
            if (riskEmpty) {
                riskEmpty.classList.remove('d-none');
                const p = riskEmpty.querySelector('p');
                if (p) p.textContent = 'Chart library unavailable';
            }
            return;
        }

        // 1. Supply Request Status Chart (Isolated)
        try {
            if (reqCanvas) {
                destroyChart(clinicCharts.requests, reqCanvas);
                clinicCharts.requests = null;

                const reqCounts = { Pending: 0, Approved: 0, Completed: 0, Cancelled: 0 };
                (state.requests || []).forEach(r => {
                    if (reqCounts[r.status] !== undefined) {
                        reqCounts[r.status]++;
                    }
                });

                const reqTotal = reqCounts.Pending + reqCounts.Approved + reqCounts.Completed + reqCounts.Cancelled;

                if (reqTotal === 0) {
                    reqCanvas.classList.add('d-none');
                    if (reqEmpty) {
                        reqEmpty.classList.remove('d-none');
                        const p = reqEmpty.querySelector('p');
                        if (p) p.textContent = 'No supply requests recorded for this clinic';
                    }
                } else {
                    reqCanvas.classList.remove('d-none');
                    if (reqEmpty) reqEmpty.classList.add('d-none');

                    clinicCharts.requests = new window.Chart(reqCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: ['Pending', 'Approved', 'Completed', 'Cancelled'],
                            datasets: [{
                                data: [reqCounts.Pending, reqCounts.Approved, reqCounts.Completed, reqCounts.Cancelled],
                                backgroundColor: ['#b57919', '#347ca4', '#27845b', '#b54747'],
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { boxWidth: 12, font: { size: 11 } }
                                }
                            },
                            cutout: '62%'
                        }
                    });
                }
            }
        } catch (err) {
            console.error('Error rendering clinic request chart:', err);
        }

        // 2. Inventory Stock-Out Risk Chart (Isolated)
        try {
            if (riskCanvas) {
                destroyChart(clinicCharts.stockRisk, riskCanvas);
                clinicCharts.stockRisk = null;

                const riskCounts = { Critical: 0, High: 0, Medium: 0, Low: 0 };
                (state.stockRisk || []).forEach(item => {
                    const risk = item.stock_out_risk;
                    if (riskCounts[risk] !== undefined) {
                        riskCounts[risk]++;
                    }
                });

                const riskTotal = riskCounts.Critical + riskCounts.High + riskCounts.Medium + riskCounts.Low;

                if (riskTotal === 0) {
                    riskCanvas.classList.add('d-none');
                    if (riskEmpty) {
                        riskEmpty.classList.remove('d-none');
                        const p = riskEmpty.querySelector('p');
                        if (p) p.textContent = 'No inventory records found for this clinic';
                    }
                } else {
                    riskCanvas.classList.remove('d-none');
                    if (riskEmpty) riskEmpty.classList.add('d-none');

                    clinicCharts.stockRisk = new window.Chart(riskCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: ['Critical', 'High', 'Medium', 'Low'],
                            datasets: [{
                                data: [riskCounts.Critical, riskCounts.High, riskCounts.Medium, riskCounts.Low],
                                backgroundColor: ['#b54747', '#b57919', '#347ca4', '#27845b'],
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { boxWidth: 12, font: { size: 11 } }
                                }
                            },
                            cutout: '62%'
                        }
                    });
                }
            }
        } catch (err) {
            console.error('Error rendering clinic stock risk chart:', err);
        }
    }

    async function showDetails(requestId) {
        elements.detailsContent.innerHTML = '<p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading request details...</p>';
        detailsModal.show();
        try {
            const response = await MedLineAPI.requestJson(`requests/details.php?request_id=${encodeURIComponent(requestId)}`);
            const request = response.data || {};
            const fragment = document.createDocumentFragment();

            [
                ['Request ID', request.request_id],
                ['Clinic', request.clinic_name],
                ['Requested By ID', request.requested_by],
                ['Status', request.status],
                ['Urgency', hasScore(request.urgency_score) ? request.urgency_score : 'Not Scored'],
                ['Requested At', request.requested_at],
                ['Required By', request.required_by],
                ['Notes', request.notes]
            ].forEach(([label, value]) => fragment.append(detailRow(label, value)));

            const items = Array.isArray(request.items) ? request.items : [];
            if (!items.length) {
                fragment.append(detailRow('Items', 'No items returned'));
            }
            items.forEach((item, index) => {
                const heading = document.createElement('h3');
                heading.className = 'section-title mt-4 mb-2';
                heading.textContent = items.length > 1 ? `Item ${index + 1}` : 'Requested item';
                fragment.append(heading);
                [
                    ['Item name', item.item_name],
                    ['Category', item.category],
                    ['Quantity', item.quantity_requested],
                    ['Criticality', item.criticality_score],
                    ['Cold-chain required', yesNo(item.cold_chain_required)],
                    ['Minimum temperature', item.minimum_temperature],
                    ['Maximum temperature', item.maximum_temperature]
                ].forEach(([label, value]) => fragment.append(detailRow(label, value)));
            });

            elements.detailsContent.replaceChildren(fragment);
        } catch (error) {
            elements.detailsContent.innerHTML = '<div class="alert alert-danger mb-0"></div>';
            elements.detailsContent.firstChild.textContent = error.message || 'Unable to load request details.';
            handleAuthError(error);
        }
    }

    async function loadDashboard() {
        elements.dashboardError.classList.add('d-none');
        elements.recentTableBody.innerHTML = '<tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading recent requests...</span></td></tr>';
        elements.riskTableBody.innerHTML = '<tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading stock risk preview...</span></td></tr>';

        try {
            const [requestsResponse, riskResponse] = await Promise.all([
                MedLineAPI.requestJson('requests/get.php'),
                MedLineAPI.requestJson('inventory/stock_risk.php').catch(() => ({ data: [] }))
            ]);

            state.requests = Array.isArray(requestsResponse.data) ? requestsResponse.data : [];
            state.stockRisk = Array.isArray(riskResponse.data) ? riskResponse.data : [];

            updateSummary();
            renderRecentRequests();
            renderStockRisk();
            renderClinicCharts();
        } catch (error) {
            elements.errorMessage.textContent = error.message || 'Unable to load clinic dashboard data.';
            elements.dashboardError.classList.remove('d-none');
            elements.recentTableBody.innerHTML = '<tr><td colspan="5"><span class="text-muted-custom small">Failed to load data.</span></td></tr>';
            elements.riskTableBody.innerHTML = '<tr><td colspan="5"><span class="text-muted-custom small">Failed to load data.</span></td></tr>';
            handleAuthError(error);
        }
    }

    elements.refreshButton.addEventListener('click', loadDashboard);
    elements.retryButton.addEventListener('click', loadDashboard);
    elements.recentTableBody.addEventListener('click', event => {
        const button = event.target.closest('[data-request-id]');
        if (button) showDetails(button.dataset.requestId);
    });

    loadDashboard();
});
