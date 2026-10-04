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
        alerts: null
    };

    const metricPaths = [
        'requests.total',
        'requests.pending',
        'requests.approved',
        'requests.completed',
        'shipments.total',
        'shipments.pending',
        'shipments.dispatched',
        'shipments.in_transit',
        'shipments.delivered',
        'alerts.total',
        'alerts.open',
        'alerts.acknowledged',
        'alerts.resolved',
        'vehicles.total',
        'vehicles.available',
        'vehicles.inactive'
    ];

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

    function getMetricValue(data, path) {
        const value = path.split('.').reduce((current, key) => current?.[key], data);
        return value === null || value === undefined || value === '' ? 0 : value;
    }

    function isValidSummary(data) {
        return data && ['requests', 'shipments', 'alerts', 'vehicles'].every(group => data[group] && typeof data[group] === 'object');
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

    function isChartAvailable() {
        return typeof window.Chart !== 'undefined';
    }

    function renderDoughnut(canvasId, emptyId, chartKey, labels, data, colors) {
        const canvas = document.getElementById(canvasId);
        const emptyEl = document.getElementById(emptyId);

        if (!canvas) return;

        const total = data.reduce((acc, v) => acc + (Number(v) || 0), 0);

        if (!isChartAvailable()) {
            console.warn(`Chart.js is not loaded. Cannot render ${canvasId}.`);
            canvas.classList.add('d-none');
            if (emptyEl) {
                emptyEl.classList.remove('d-none');
                const p = emptyEl.querySelector('p');
                if (p) p.textContent = 'Chart unavailable';
            }
            return;
        }

        if (total === 0) {
            canvas.classList.add('d-none');
            if (emptyEl) {
                emptyEl.classList.remove('d-none');
                const p = emptyEl.querySelector('p');
                if (p) p.textContent = 'No records available';
            }
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

        charts[chartKey] = new window.Chart(canvas, {
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

    function renderRecentRequests(requests) {
        const tbody = document.getElementById('admin-recent-requests-body');
        const empty = document.getElementById('admin-recent-requests-empty');
        if (!tbody) return;

        if (!Array.isArray(requests) || requests.length === 0) {
            tbody.innerHTML = '';
            if (empty) empty.classList.remove('d-none');
            return;
        }

        if (empty) empty.classList.add('d-none');
        const recent = requests.slice(0, 5);
        tbody.innerHTML = recent.map(r => `
            <tr>
                <td><strong>#${escapeHtml(r.request_id)}</strong></td>
                <td>${escapeHtml(r.clinic_name || 'Clinic #' + r.clinic_id)}</td>
                <td>${urgencyBadge(r.urgency_score)}</td>
                <td>${statusBadge(r.status)}</td>
                <td class="text-muted-custom small">${formatDate(r.requested_at)}</td>
            </tr>
        `).join('');
    }

    function renderRecentShipments(shipments) {
        const tbody = document.getElementById('admin-recent-shipments-body');
        const empty = document.getElementById('admin-recent-shipments-empty');
        if (!tbody) return;

        if (!Array.isArray(shipments) || shipments.length === 0) {
            tbody.innerHTML = '';
            if (empty) empty.classList.remove('d-none');
            return;
        }

        if (empty) empty.classList.add('d-none');
        const recent = shipments.slice(0, 5);
        tbody.innerHTML = recent.map(s => `
            <tr>
                <td><strong>#${escapeHtml(s.shipment_id)}</strong></td>
                <td>Req #${escapeHtml(s.request_id)}</td>
                <td>
                    <div class="small">${escapeHtml(s.driver_name || 'Unassigned')}</div>
                    <div class="text-muted-custom small">${escapeHtml(s.vehicle_number || 'No vehicle')}</div>
                </td>
                <td>${statusBadge(s.status)}</td>
                <td class="text-muted-custom small">${formatDate(s.estimated_delivery)}</td>
            </tr>
        `).join('');
    }

    function renderRecentAlerts(alerts, openCount) {
        const tbody = document.getElementById('admin-recent-alerts-body');
        const empty = document.getElementById('admin-recent-alerts-empty');
        const countBadge = document.getElementById('admin-open-alerts-count');

        if (countBadge) {
            countBadge.textContent = `${openCount} Open`;
            countBadge.className = openCount > 0 ? 'badge badge-soft-danger' : 'badge badge-soft-success';
        }

        if (!tbody) return;

        if (!Array.isArray(alerts) || alerts.length === 0) {
            tbody.innerHTML = '';
            if (empty) empty.classList.remove('d-none');
            return;
        }

        if (empty) empty.classList.add('d-none');
        const recent = alerts.slice(0, 5);
        tbody.innerHTML = recent.map(a => `
            <tr>
                <td><strong>#${escapeHtml(a.alert_id)}</strong></td>
                <td><span class="badge badge-soft-danger">${escapeHtml(a.alert_type || 'Alert')}</span></td>
                <td class="small text-truncate" style="max-width: 260px;" title="${escapeHtml(a.alert_message || '')}">
                    ${escapeHtml(a.alert_message || 'No description')}
                </td>
                <td>${statusBadge(a.status)}</td>
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

            // Summary is required for main KPI cards
            if (summaryRes.status !== 'fulfilled' || !summaryRes.value.success || !isValidSummary(summaryRes.value.data)) {
                throw new Error('The dashboard summary returned an unexpected response.');
            }

            const summaryData = summaryRes.value.data;

            // 1. Update KPI cards
            try {
                metricPaths.forEach(path => {
                    const metricElement = document.querySelector(`[data-stat="${path}"]`);
                    if (metricElement) {
                        metricElement.textContent = getMetricValue(summaryData, path);
                    }
                });
            } catch (err) {
                console.error('Error updating KPI metrics:', err);
            }

            // 2. Request status chart (Isolated)
            try {
                const reqStats = summaryData.requests || {};
                renderDoughnut(
                    'chart-admin-requests',
                    'empty-admin-requests',
                    'requests',
                    ['Pending', 'Approved', 'Completed'],
                    [reqStats.pending || 0, reqStats.approved || 0, reqStats.completed || 0],
                    [chartColors.warning, chartColors.info, chartColors.success]
                );
            } catch (err) {
                console.error('Error rendering request status chart:', err);
            }

            // 3. Shipment status chart (Isolated)
            try {
                const shipStats = summaryData.shipments || {};
                renderDoughnut(
                    'chart-admin-shipments',
                    'empty-admin-shipments',
                    'shipments',
                    ['Pending', 'Dispatched', 'In Transit', 'Delivered'],
                    [shipStats.pending || 0, shipStats.dispatched || 0, shipStats.in_transit || 0, shipStats.delivered || 0],
                    [chartColors.warning, chartColors.info, chartColors.primary, chartColors.success]
                );
            } catch (err) {
                console.error('Error rendering shipment status chart:', err);
            }

            // 4. Alert status chart (Isolated)
            try {
                const alertStats = summaryData.alerts || {};
                renderDoughnut(
                    'chart-admin-alerts',
                    'empty-admin-alerts',
                    'alerts',
                    ['Open', 'Acknowledged', 'Resolved'],
                    [alertStats.open || 0, alertStats.acknowledged || 0, alertStats.resolved || 0],
                    [chartColors.danger, chartColors.warning, chartColors.success]
                );
            } catch (err) {
                console.error('Error rendering alert status chart:', err);
            }

            // 5. Recent Requests table (Isolated)
            try {
                if (requestsRes.status === 'fulfilled' && requestsRes.value && requestsRes.value.success && Array.isArray(requestsRes.value.data)) {
                    renderRecentRequests(requestsRes.value.data);
                } else {
                    renderRecentRequests([]);
                }
            } catch (err) {
                console.error('Error rendering recent requests table:', err);
                renderRecentRequests([]);
            }

            // 6. Recent Shipments table (Isolated)
            try {
                if (shipmentsRes.status === 'fulfilled' && shipmentsRes.value && shipmentsRes.value.success && Array.isArray(shipmentsRes.value.data)) {
                    renderRecentShipments(shipmentsRes.value.data);
                } else {
                    renderRecentShipments([]);
                }
            } catch (err) {
                console.error('Error rendering recent shipments table:', err);
                renderRecentShipments([]);
            }

            // 7. Recent Alerts table (Isolated)
            try {
                const alertStats = summaryData.alerts || {};
                let openAlertsCount = Number(alertStats.open) || 0;
                let alertsList = [];
                if (alertsRes.status === 'fulfilled' && alertsRes.value && alertsRes.value.success && alertsRes.value.data) {
                    alertsList = alertsRes.value.data.recent_alerts || alertsRes.value.data.open_alerts || [];
                }
                renderRecentAlerts(alertsList, openAlertsCount);
            } catch (err) {
                console.error('Error rendering recent alerts table:', err);
                renderRecentAlerts([], 0);
            }

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
                showError('You do not have permission to view this dashboard.');
                return;
            }

            showError(error.message || 'Unable to load dashboard data. Please try again.');
        }
    }

    if (retryButton) {
        retryButton.addEventListener('click', loadDashboard);
    }
    loadDashboard();
})();
