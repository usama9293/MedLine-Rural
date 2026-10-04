(() => {
    const loadingElement = document.querySelector('#dashboard-loading');
    const dataElement = document.querySelector('#dashboard-data');
    const errorElement = document.querySelector('#dashboard-error');
    const errorMessageElement = document.querySelector('#dashboard-error-message');
    const retryButton = document.querySelector('#retry-dashboard');
    const updatedElement = document.querySelector('#last-updated');

    const charts = {
        requests: null,
        shipments: null,
        urgency: null
    };

    const chartColors = {
        primary: '#176b87',
        warning: '#b57919',
        success: '#27845b',
        danger: '#b54747',
        info: '#347ca4',
        secondary: '#52727d',
        gray: '#94a3b8'
    };

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return escapeHtml(dateStr);
        return d.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function urgencyCategory(score) {
        if (score === null || score === undefined || score === '') return 'Low';
        const val = Number(score);
        if (val >= 80) return 'Critical';
        if (val >= 60) return 'High';
        if (val >= 40) return 'Medium';
        return 'Low';
    }

    function urgencyBadge(score) {
        const cat = urgencyCategory(score);
        const map = {
            Critical: 'badge-soft-danger',
            High: 'badge-soft-warning',
            Medium: 'badge-soft-info',
            Low: 'badge-soft-success'
        };
        return `<span class="badge ${map[cat] || 'badge-soft-info'}">${cat}</span>`;
    }

    function statusBadge(status) {
        const map = {
            Pending: 'badge-soft-warning',
            Dispatched: 'badge-soft-info',
            'In Transit': 'badge-soft-primary',
            Delivered: 'badge-soft-success',
            Completed: 'badge-soft-success',
            Approved: 'badge-soft-info',
            Cancelled: 'badge-soft-danger',
            Open: 'badge-soft-danger',
            Acknowledged: 'badge-soft-warning',
            Resolved: 'badge-soft-success'
        };
        return `<span class="badge ${map[status] || 'badge-soft-secondary'}">${escapeHtml(status)}</span>`;
    }

    function setLoading(isLoading) {
        if (loadingElement) loadingElement.classList.toggle('d-none', !isLoading);
        if (dataElement) dataElement.classList.toggle('d-none', isLoading);
        if (retryButton) retryButton.disabled = isLoading;
    }

    function showError(message) {
        if (errorMessageElement) errorMessageElement.textContent = message;
        if (errorElement) errorElement.classList.remove('d-none');
        if (dataElement) dataElement.classList.add('d-none');
    }

    function hideError() {
        if (errorElement) errorElement.classList.add('d-none');
    }

    function renderDoughnut(canvasId, emptyId, chartKey, labels, data, colors) {
        const total = data.reduce((acc, v) => acc + (Number(v) || 0), 0);
        const canvas = document.getElementById(canvasId);
        const emptyEl = document.getElementById(emptyId);

        if (!canvas) return;

        if (total === 0) {
            canvas.classList.add('d-none');
            if (emptyEl) emptyEl.classList.remove('d-none');
            if (charts[chartKey]) {
                charts[chartKey].destroy();
                charts[chartKey] = null;
            }
            return;
        }

        canvas.classList.remove('d-none');
        if (emptyEl) emptyEl.classList.add('d-none');

        if (charts[chartKey]) {
            charts[chartKey].destroy();
        }

        charts[chartKey] = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
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
                        labels: {
                            boxWidth: 12,
                            font: { size: 11 }
                        }
                    }
                },
                cutout: '62%'
            }
        });
    }

    function renderPendingRequests(requests) {
        const tbody = document.getElementById('disp-requests-body');
        const empty = document.getElementById('disp-requests-empty');
        if (!tbody) return;

        // Filter for pending or urgent requests
        const filtered = (requests || []).filter(r => r.status === 'Pending' || Number(r.urgency_score) >= 60);

        if (filtered.length === 0) {
            tbody.innerHTML = '';
            if (empty) empty.classList.remove('d-none');
            return;
        }

        if (empty) empty.classList.add('d-none');
        const list = filtered.slice(0, 5);
        tbody.innerHTML = list.map(r => `
            <tr>
                <td><strong>#${escapeHtml(r.request_id)}</strong></td>
                <td>${escapeHtml(r.clinic_name || 'Clinic #' + r.clinic_id)}</td>
                <td>${urgencyBadge(r.urgency_score)}</td>
                <td>${statusBadge(r.status)}</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary py-0 px-2" href="../dispatcher/requests.php?id=${encodeURIComponent(r.request_id)}">View</a>
                </td>
            </tr>
        `).join('');
    }

    function renderActiveShipments(shipments) {
        const tbody = document.getElementById('disp-shipments-body');
        const empty = document.getElementById('disp-shipments-empty');
        if (!tbody) return;

        const active = (shipments || []).filter(s => s.status === 'In Transit' || s.status === 'Dispatched');

        if (active.length === 0) {
            tbody.innerHTML = '';
            if (empty) empty.classList.remove('d-none');
            return;
        }

        if (empty) empty.classList.add('d-none');
        const list = active.slice(0, 5);
        tbody.innerHTML = list.map(s => `
            <tr>
                <td>
                    <strong>#${escapeHtml(s.shipment_id)}</strong>
                    <div class="text-muted-custom small">Req #${escapeHtml(s.request_id)}</div>
                </td>
                <td>
                    <div class="small">${escapeHtml(s.driver_name || 'Unassigned')}</div>
                    <div class="text-muted-custom small">${escapeHtml(s.vehicle_number || 'No vehicle')}</div>
                </td>
                <td>${statusBadge(s.status)}</td>
                <td class="text-muted-custom small">${formatDate(s.estimated_delivery)}</td>
            </tr>
        `).join('');
    }

    function renderOpenAlerts(alerts, openCount) {
        const tbody = document.getElementById('disp-alerts-body');
        const empty = document.getElementById('disp-alerts-empty');
        const badge = document.getElementById('disp-open-alerts-badge');

        if (badge) {
            badge.textContent = `${openCount} Open`;
            badge.className = openCount > 0 ? 'badge badge-soft-danger' : 'badge badge-soft-success';
        }

        if (!tbody) return;

        if (!alerts || alerts.length === 0) {
            tbody.innerHTML = '';
            if (empty) empty.classList.remove('d-none');
            return;
        }

        if (empty) empty.classList.add('d-none');
        const list = alerts.slice(0, 5);
        tbody.innerHTML = list.map(a => `
            <tr>
                <td><strong>#${escapeHtml(a.alert_id)}</strong></td>
                <td>Shipment #${escapeHtml(a.shipment_id || 'N/A')}</td>
                <td><span class="badge badge-soft-danger">${escapeHtml(a.alert_type || 'Alert')}</span></td>
                <td class="small text-truncate" style="max-width: 260px;" title="${escapeHtml(a.alert_message || '')}">
                    ${escapeHtml(a.alert_message || 'No details')}
                </td>
                <td class="text-muted-custom small">${formatDate(a.created_at)}</td>
            </tr>
        `).join('');
    }

    async function loadDashboard() {
        hideError();
        setLoading(true);

        try {
            const [summaryRes, requestsRes, shipmentsRes, alertsRes] = await Promise.allSettled([
                MedLineAPI.requestJson('dashboard/summary.php'),
                MedLineAPI.requestJson('requests/get.php'),
                MedLineAPI.requestJson('shipments/list.php'),
                MedLineAPI.requestJson('dashboard/alerts.php')
            ]);

            if (summaryRes.status !== 'fulfilled' || !summaryRes.value.success) {
                throw new Error('Failed to retrieve summary operations.');
            }

            const summaryData = summaryRes.value.data || {};
            const reqStats = summaryData.requests || {};
            const shipStats = summaryData.shipments || {};
            const alertStats = summaryData.alerts || {};

            // 1. KPI Row
            const pendingReqEl = document.getElementById('disp-stat-pending-requests');
            const approvedReqEl = document.getElementById('disp-stat-approved-requests');
            const activeShipEl = document.getElementById('disp-stat-active-shipments');
            const openAlertsEl = document.getElementById('disp-stat-open-alerts');

            const activeShipmentsCount = (Number(shipStats.dispatched) || 0) + (Number(shipStats.in_transit) || 0);

            if (pendingReqEl) pendingReqEl.textContent = reqStats.pending || 0;
            if (approvedReqEl) approvedReqEl.textContent = reqStats.approved || 0;
            if (activeShipEl) activeShipEl.textContent = activeShipmentsCount;
            if (openAlertsEl) openAlertsEl.textContent = alertStats.open || 0;

            // 2. Request Status Chart
            renderDoughnut(
                'chart-disp-requests',
                'empty-disp-requests',
                'requests',
                ['Pending', 'Approved', 'Completed'],
                [reqStats.pending || 0, reqStats.approved || 0, reqStats.completed || 0],
                [chartColors.warning, chartColors.info, chartColors.success]
            );

            // 3. Shipment Status Chart
            renderDoughnut(
                'chart-disp-shipments',
                'empty-disp-shipments',
                'shipments',
                ['Pending', 'Dispatched', 'In Transit', 'Delivered'],
                [shipStats.pending || 0, shipStats.dispatched || 0, shipStats.in_transit || 0, shipStats.delivered || 0],
                [chartColors.warning, chartColors.info, chartColors.primary, chartColors.success]
            );

            // 4. Urgency Distribution Chart
            let requestsList = [];
            if (requestsRes.status === 'fulfilled' && requestsRes.value.success && Array.isArray(requestsRes.value.data)) {
                requestsList = requestsRes.value.data;
            }

            const urgencyCounts = { Critical: 0, High: 0, Medium: 0, Low: 0 };
            requestsList.forEach(r => {
                const cat = urgencyCategory(r.urgency_score);
                if (urgencyCounts[cat] !== undefined) {
                    urgencyCounts[cat]++;
                }
            });

            renderDoughnut(
                'chart-disp-urgency',
                'empty-disp-urgency',
                'urgency',
                ['Critical', 'High', 'Medium', 'Low'],
                [urgencyCounts.Critical, urgencyCounts.High, urgencyCounts.Medium, urgencyCounts.Low],
                [chartColors.danger, chartColors.warning, chartColors.info, chartColors.success]
            );

            // 5. Tables
            renderPendingRequests(requestsList);

            let shipmentsList = [];
            if (shipmentsRes.status === 'fulfilled' && shipmentsRes.value.success && Array.isArray(shipmentsRes.value.data)) {
                shipmentsList = shipmentsRes.value.data;
            }
            renderActiveShipments(shipmentsList);

            let openAlertsList = [];
            let openAlertsCount = alertStats.open || 0;
            if (alertsRes.status === 'fulfilled' && alertsRes.value.success && alertsRes.value.data) {
                openAlertsList = alertsRes.value.data.open_alerts || [];
            }
            renderOpenAlerts(openAlertsList, openAlertsCount);

            if (updatedElement) {
                updatedElement.textContent = `Updated ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
            }

            setLoading(false);
        } catch (error) {
            setLoading(false);

            if (error.status === 401) {
                window.location.href = '../frontend/login.php';
                return;
            }

            if (error.status === 403) {
                showError('You do not have permission to view the dispatcher dashboard.');
                return;
            }

            showError(error.message || 'Unable to load dispatch data. Please try again.');
        }
    }

    if (retryButton) {
        retryButton.addEventListener('click', loadDashboard);
    }
    loadDashboard();
})();
