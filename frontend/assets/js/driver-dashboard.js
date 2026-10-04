(() => {
    const loadingElement = document.querySelector('#dashboard-loading');
    const dataElement = document.querySelector('#dashboard-data');
    const errorElement = document.querySelector('#dashboard-error');
    const errorMessageElement = document.querySelector('#dashboard-error-message');
    const retryButton = document.querySelector('#retry-dashboard');
    const updatedElement = document.querySelector('#last-updated');

    const charts = {
        status: null,
        telemetry: null
    };

    const chartColors = {
        primary: '#176b87',
        warning: '#b57919',
        success: '#27845b',
        danger: '#b54747',
        info: '#347ca4',
        secondary: '#52727d'
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

    function formatTime(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return String(dateStr);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function statusBadge(status) {
        const map = {
            Pending: 'badge-soft-warning',
            Dispatched: 'badge-soft-info',
            'In Transit': 'badge-soft-primary',
            Delivered: 'badge-soft-success',
            Completed: 'badge-soft-success',
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

    function renderStatusChart(shipments) {
        const canvas = document.getElementById('chart-driver-status');
        const emptyEl = document.getElementById('empty-driver-status');
        if (!canvas) return;

        const counts = {
            'In Transit': 0,
            Delivered: 0,
            Dispatched: 0,
            Pending: 0
        };

        (shipments || []).forEach(s => {
            if (counts[s.status] !== undefined) {
                counts[s.status]++;
            } else {
                counts.Pending++;
            }
        });

        const total = Object.values(counts).reduce((a, b) => a + b, 0);

        if (total === 0) {
            canvas.classList.add('d-none');
            if (emptyEl) emptyEl.classList.remove('d-none');
            if (charts.status) {
                charts.status.destroy();
                charts.status = null;
            }
            return;
        }

        canvas.classList.remove('d-none');
        if (emptyEl) emptyEl.classList.add('d-none');

        if (charts.status) {
            charts.status.destroy();
        }

        charts.status = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: ['In Transit', 'Delivered', 'Dispatched', 'Pending'],
                datasets: [{
                    data: [counts['In Transit'], counts.Delivered, counts.Dispatched, counts.Pending],
                    backgroundColor: [chartColors.primary, chartColors.success, chartColors.info, chartColors.warning],
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

    function renderTelemetryChart(telemetryEvents) {
        const canvas = document.getElementById('chart-driver-telemetry');
        const emptyEl = document.getElementById('empty-driver-telemetry');
        if (!canvas) return;

        if (!telemetryEvents || telemetryEvents.length === 0) {
            canvas.classList.add('d-none');
            if (emptyEl) emptyEl.classList.remove('d-none');
            if (charts.telemetry) {
                charts.telemetry.destroy();
                charts.telemetry = null;
            }
            return;
        }

        canvas.classList.remove('d-none');
        if (emptyEl) emptyEl.classList.add('d-none');

        // Events are usually ordered DESC in database; sort ASC for chronological time series
        const chronological = [...telemetryEvents].sort((a, b) => new Date(a.recorded_at) - new Date(b.recorded_at));

        const labels = chronological.map(e => formatTime(e.recorded_at));
        const temps = chronological.map(e => Number(e.temperature));
        const minSafeLine = new Array(chronological.length).fill(2);
        const maxSafeLine = new Array(chronological.length).fill(8);

        if (charts.telemetry) {
            charts.telemetry.destroy();
        }

        charts.telemetry = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Temperature (°C)',
                        data: temps,
                        borderColor: chartColors.primary,
                        backgroundColor: 'rgba(23, 107, 135, 0.15)',
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointBackgroundColor: chartColors.primary,
                        tension: 0.25,
                        fill: true
                    },
                    {
                        label: 'Safe Min (2°C)',
                        data: minSafeLine,
                        borderColor: chartColors.warning,
                        borderWidth: 1.5,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false
                    },
                    {
                        label: 'Safe Max (8°C)',
                        data: maxSafeLine,
                        borderColor: chartColors.danger,
                        borderWidth: 1.5,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        suggestedMin: 0,
                        suggestedMax: 12,
                        title: {
                            display: true,
                            text: '°C',
                            font: { size: 11, weight: 'bold' }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Time',
                            font: { size: 11 }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                }
            }
        });
    }

    function renderActiveShipmentCard(activeShipment, latestTelemetry) {
        const container = document.getElementById('driver-active-shipment-content');
        if (!container) return;

        if (!activeShipment) {
            container.innerHTML = `
                <div class="text-center py-3 text-muted-custom">
                    <i class="bi bi-box-seam fs-3 d-block mb-2 opacity-50"></i>
                    <p class="mb-0">No active delivery in progress right now.</p>
                </div>
            `;
            return;
        }

        const currentTemp = latestTelemetry ? `${latestTelemetry.temperature}°C` : 'N/A';
        let tempBadge = '<span class="badge badge-soft-secondary">No reading</span>';
        if (latestTelemetry) {
            const t = Number(latestTelemetry.temperature);
            if (t >= 2 && t <= 8) {
                tempBadge = `<span class="badge badge-soft-success">${currentTemp} (Safe)</span>`;
            } else {
                tempBadge = `<span class="badge badge-soft-danger">${currentTemp} (Out of range)</span>`;
            }
        }

        container.innerHTML = `
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-3">
                    <span class="text-muted-custom small d-block">Shipment ID</span>
                    <strong class="fs-5">#${escapeHtml(activeShipment.shipment_id)}</strong>
                    <div class="mt-1">${statusBadge(activeShipment.status)}</div>
                </div>
                <div class="col-12 col-md-3">
                    <span class="text-muted-custom small d-block">Destination</span>
                    <strong>${escapeHtml(activeShipment.clinic_name || 'Destination Clinic')}</strong>
                    <div class="text-muted-custom small">Request #${escapeHtml(activeShipment.request_id)}</div>
                </div>
                <div class="col-12 col-md-3">
                    <span class="text-muted-custom small d-block">Est. Delivery</span>
                    <strong>${formatDate(activeShipment.estimated_delivery)}</strong>
                    <div class="text-muted-custom small">Dispatched: ${formatDate(activeShipment.dispatched_at)}</div>
                </div>
                <div class="col-12 col-md-3">
                    <span class="text-muted-custom small d-block">Latest Temperature</span>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        ${tempBadge}
                    </div>
                </div>
            </div>
        `;
    }

    function renderTelemetryTable(events) {
        const tbody = document.getElementById('driver-telemetry-body');
        const empty = document.getElementById('driver-telemetry-empty');
        const countBadge = document.getElementById('driver-telemetry-count');

        const totalEvents = events ? events.length : 0;
        if (countBadge) countBadge.textContent = `${totalEvents} Samples`;

        if (!tbody) return;

        if (!events || events.length === 0) {
            tbody.innerHTML = '';
            if (empty) empty.classList.remove('d-none');
            return;
        }

        if (empty) empty.classList.add('d-none');
        const list = events.slice(0, 5);

        tbody.innerHTML = list.map(e => {
            const t = Number(e.temperature);
            let status = '<span class="badge badge-soft-success">Safe</span>';
            if (t < 2 || t > 8) {
                status = '<span class="badge badge-soft-danger">Excursion</span>';
            }
            const coords = e.latitude && e.longitude ? `${Number(e.latitude).toFixed(4)}, ${Number(e.longitude).toFixed(4)}` : 'N/A';
            return `
                <tr>
                    <td class="text-nowrap">${formatDate(e.recorded_at)}</td>
                    <td><strong>${escapeHtml(e.temperature)}°C</strong></td>
                    <td>${status}</td>
                    <td class="text-muted-custom small text-nowrap">${escapeHtml(coords)}</td>
                </tr>
            `;
        }).join('');
    }

    function renderAlertsTable(alerts) {
        const tbody = document.getElementById('driver-alerts-body');
        const empty = document.getElementById('driver-alerts-empty');
        const badge = document.getElementById('driver-alerts-badge');

        const totalAlerts = alerts ? alerts.length : 0;
        if (badge) {
            badge.textContent = `${totalAlerts} Alerts`;
            badge.className = totalAlerts > 0 ? 'badge badge-soft-danger' : 'badge badge-soft-success';
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
                <td><span class="badge badge-soft-danger">${escapeHtml(a.alert_type || 'Alert')}</span></td>
                <td class="small text-truncate" style="max-width: 200px;" title="${escapeHtml(a.alert_message || '')}">
                    ${escapeHtml(a.alert_message || 'Notice')}
                </td>
                <td>${statusBadge(a.status)}</td>
            </tr>
        `).join('');
    }

    async function loadDashboard() {
        hideError();
        setLoading(true);

        try {
            const shipmentsRes = await MedLineAPI.requestJson('shipments/get.php');

            if (!shipmentsRes || !shipmentsRes.success || !Array.isArray(shipmentsRes.data)) {
                throw new Error('Failed to retrieve driver shipments.');
            }

            const shipments = shipmentsRes.data;

            // 1. Calculate KPIs
            let inTransitCount = 0;
            let deliveredCount = 0;
            let pendingCount = 0;

            shipments.forEach(s => {
                if (s.status === 'In Transit') inTransitCount++;
                else if (s.status === 'Delivered') deliveredCount++;
                else pendingCount++;
            });

            const statTotalEl = document.getElementById('driver-stat-total');
            const statInTransitEl = document.getElementById('driver-stat-intransit');
            const statDeliveredEl = document.getElementById('driver-stat-delivered');
            const statPendingEl = document.getElementById('driver-stat-pending');

            if (statTotalEl) statTotalEl.textContent = shipments.length;
            if (statInTransitEl) statInTransitEl.textContent = inTransitCount;
            if (statDeliveredEl) statDeliveredEl.textContent = deliveredCount;
            if (statPendingEl) statPendingEl.textContent = pendingCount;

            // 2. Render Status Doughnut Chart
            renderStatusChart(shipments);

            // 3. Find active shipment (In Transit first, then Dispatched, then first Pending)
            const activeShipment = shipments.find(s => s.status === 'In Transit') ||
                                   shipments.find(s => s.status === 'Dispatched') ||
                                   shipments.find(s => s.status === 'Pending') ||
                                   shipments[0] || null;

            let telemetryEvents = [];
            let shipmentAlerts = [];

            if (activeShipment) {
                try {
                    const trackingRes = await MedLineAPI.requestJson(`shipments/get_tracking.php?shipment_id=${encodeURIComponent(activeShipment.shipment_id)}`);
                    if (trackingRes && trackingRes.success && trackingRes.data) {
                        telemetryEvents = trackingRes.data.telemetry || [];
                        shipmentAlerts = trackingRes.data.alerts || [];
                    }
                } catch (e) {
                    console.warn('Could not fetch tracking telemetry:', e);
                }
            }

            const latestTelemetry = telemetryEvents.length > 0 ? telemetryEvents[0] : null;

            // 4. Render Active Shipment Card
            renderActiveShipmentCard(activeShipment, latestTelemetry);

            // 5. Render Temperature Telemetry Chart
            renderTelemetryChart(telemetryEvents);

            // 6. Render Telemetry & Alerts tables
            renderTelemetryTable(telemetryEvents);
            renderAlertsTable(shipmentAlerts);

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
                showError('You do not have permission to view the driver dashboard.');
                return;
            }

            showError(error.message || 'Unable to load driver data. Please try again.');
        }
    }

    if (retryButton) {
        retryButton.addEventListener('click', loadDashboard);
    }
    loadDashboard();
})();
