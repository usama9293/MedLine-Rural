document.addEventListener('DOMContentLoaded', () => {
    const state = {
        shipments: [],
        selectedShipmentId: null,
        trackingOpen: false,
        pendingAction: null,
        createOptionsLoaded: false
    };

    const elements = {
        tableBody: document.querySelector('#shipments-table-body'),
        empty: document.querySelector('#shipments-empty'),
        count: document.querySelector('#shipments-count'),
        search: document.querySelector('#shipment-search'),
        statusFilter: document.querySelector('#shipment-status-filter'),
        feedback: document.querySelector('#shipments-feedback'),
        refreshButton: document.querySelector('#refresh-shipments-button'),
        openCreateButton: document.querySelector('#open-create-shipment-button'),
        detailsModal: document.querySelector('#shipment-details-modal'),
        detailsContent: document.querySelector('#shipment-details-content'),
        trackingModal: document.querySelector('#shipment-tracking-modal'),
        trackingContent: document.querySelector('#shipment-tracking-content'),
        createModal: document.querySelector('#create-shipment-modal'),
        createForm: document.querySelector('#create-shipment-form'),
        createError: document.querySelector('#create-shipment-error'),
        request: document.querySelector('#shipment-request'),
        vehicle: document.querySelector('#shipment-vehicle'),
        driver: document.querySelector('#shipment-driver'),
        estimatedDelivery: document.querySelector('#shipment-estimated-delivery'),
        requestOptionsStatus: document.querySelector('#request-options-status'),
        vehicleOptionsStatus: document.querySelector('#vehicle-options-status'),
        driverOptionsStatus: document.querySelector('#driver-options-status'),
        submitButton: document.querySelector('#submit-shipment-button'),
        confirmModal: document.querySelector('#shipment-confirm-modal'),
        confirmForm: document.querySelector('#shipment-confirm-form'),
        confirmTitle: document.querySelector('#shipment-confirm-title'),
        confirmLabel: document.querySelector('#shipment-confirm-label'),
        confirmError: document.querySelector('#shipment-confirm-error'),
        confirmButton: document.querySelector('#shipment-confirm-button'),
        statTotal: document.querySelector('#stat-total'),
        statPending: document.querySelector('#stat-pending'),
        statDispatched: document.querySelector('#stat-dispatched'),
        statInTransit: document.querySelector('#stat-in-transit'),
        statDelivered: document.querySelector('#stat-delivered')
    };

    const detailsModal = new bootstrap.Modal(elements.detailsModal);
    const trackingModal = new bootstrap.Modal(elements.trackingModal);
    const createModal = new bootstrap.Modal(elements.createModal);
    const confirmModal = new bootstrap.Modal(elements.confirmModal);

    function errorMessage(error, fallback) {
        const backendMessage = typeof error.message === 'string' && error.message && error.message !== 'The request could not be completed.' ? error.message : null;
        const mapped = {
            400: backendMessage || 'Please check the shipment information and try again.',
            401: 'Your session has expired. Please sign in again.',
            403: backendMessage || 'You do not have permission to manage shipments.',
            404: backendMessage || 'The requested shipment or resource was not found.',
            405: 'That action is not allowed.',
            409: backendMessage || 'That shipment action conflicts with the current resource state.',
            422: backendMessage || 'The shipment does not meet the required business rules.',
            500: 'The server could not complete the shipment operation.'
        };
        return mapped[error.status] || backendMessage || fallback;
    }

    function handleAuthError(error) {
        if (error.status === 401) window.location.href = '../frontend/login.php';
    }

    function displayValue(value) {
        return value === null || value === undefined || String(value).trim() === '' ? 'Not provided' : String(value);
    }

    function statusClass(status) {
        return ({ Pending: 'badge-soft-warning', Dispatched: 'badge-soft-info', 'In Transit': 'badge-soft-info', Delivered: 'badge-soft-success' })[status] || 'badge-soft-info';
    }

    function showFeedback(message, type = 'success') {
        elements.feedback.className = `alert alert-${type}`;
        elements.feedback.textContent = message;
        elements.feedback.classList.remove('d-none');
    }

    function setBusy(button, busy, label, defaultContent) {
        button.disabled = busy;
        button.innerHTML = busy ? `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${label}` : defaultContent;
    }

    function filteredShipments() {
        const query = elements.search.value.trim().toLowerCase();
        const status = elements.statusFilter.value;
        return state.shipments.filter(shipment => {
            const matchesQuery = !query || [shipment.shipment_id, shipment.request_id, shipment.vehicle_number, shipment.driver_name].some(value => String(value ?? '').toLowerCase().includes(query));
            return matchesQuery && (!status || shipment.status === status);
        });
    }

    function updateSummary() {
        const counts = { Pending: 0, Dispatched: 0, 'In Transit': 0, Delivered: 0 };
        state.shipments.forEach(shipment => {
            if (Object.prototype.hasOwnProperty.call(counts, shipment.status)) {
                counts[shipment.status] += 1;
            }
        });
        elements.statTotal.textContent = state.shipments.length;
        elements.statPending.textContent = counts.Pending;
        elements.statDispatched.textContent = counts.Dispatched;
        elements.statInTransit.textContent = counts['In Transit'];
        elements.statDelivered.textContent = counts.Delivered;
    }

    function actionButton(label, icon, action, shipment) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary users-action me-1';
        button.dataset.action = action;
        button.dataset.shipmentId = shipment.shipment_id;
        button.title = label;
        button.setAttribute('aria-label', `${label}: shipment ${shipment.shipment_id}`);
        button.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i><span class="visually-hidden">${label}</span>`;
        return button;
    }

    function createRow(shipment) {
        const row = document.createElement('tr');

        const shipmentCell = document.createElement('td');
        shipmentCell.textContent = `#${shipment.shipment_id}`;

        const requestCell = document.createElement('td');
        requestCell.textContent = `#${shipment.request_id}`;

        const vehicleCell = document.createElement('td');
        vehicleCell.textContent = displayValue(shipment.vehicle_number || shipment.vehicle_id);

        const driverCell = document.createElement('td');
        driverCell.textContent = displayValue(shipment.driver_name || shipment.driver_id);

        const statusCell = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = `badge ${statusClass(shipment.status)}`;
        badge.textContent = displayValue(shipment.status);
        statusCell.appendChild(badge);

        const estimatedCell = document.createElement('td');
        estimatedCell.textContent = displayValue(shipment.estimated_delivery);

        const dispatchedCell = document.createElement('td');
        dispatchedCell.textContent = displayValue(shipment.dispatched_at);

        const deliveredCell = document.createElement('td');
        deliveredCell.textContent = displayValue(shipment.delivered_at);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'text-end text-nowrap';
        actionsCell.append(
            actionButton('View details', 'bi-eye', 'details', shipment),
            actionButton('View tracking', 'bi-geo-alt', 'tracking', shipment)
        );

        if (shipment.status === 'Pending') {
            actionsCell.append(
                actionButton('Validate assignment', 'bi-shield-check', 'validate', shipment),
                actionButton('Dispatch shipment', 'bi-send', 'dispatch', shipment)
            );
        }
        if (shipment.status === 'Dispatched') {
            actionsCell.append(actionButton('Mark in transit', 'bi-truck', 'in-transit', shipment));
        }
        if (shipment.status === 'In Transit') {
            actionsCell.append(actionButton('Mark delivered', 'bi-check2-circle', 'delivered', shipment));
        }

        row.append(
            shipmentCell,
            requestCell,
            vehicleCell,
            driverCell,
            statusCell,
            estimatedCell,
            dispatchedCell,
            deliveredCell,
            actionsCell
        );

        return row;
    }

    function renderShipments() {
        const shipments = filteredShipments();
        elements.tableBody.replaceChildren(...shipments.map(createRow));
        elements.tableBody.classList.toggle('d-none', shipments.length === 0);
        elements.empty.classList.toggle('d-none', shipments.length > 0);
        elements.count.textContent = `${shipments.length} of ${state.shipments.length} shipments`;
        updateSummary();
    }

    async function loadShipments() {
        elements.tableBody.classList.remove('d-none');
        elements.empty.classList.add('d-none');
        elements.tableBody.innerHTML = '<tr><td colspan="9"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading shipments...</span></td></tr>';
        try {
            const response = await MedLineAPI.requestJson('shipments/list.php');
            state.shipments = Array.isArray(response.data) ? response.data : [];
            renderShipments();
        } catch (error) {
            elements.count.textContent = 'Unable to load shipments';
            elements.tableBody.innerHTML = '<tr><td colspan="9"><div class="error-state"><i class="bi bi-exclamation-circle error-state-icon" aria-hidden="true"></i><p class="mb-3"></p><button class="btn btn-primary btn-sm" type="button" id="retry-shipments"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Retry</button></div></td></tr>';
            elements.tableBody.querySelector('p').textContent = errorMessage(error, 'Unable to load shipments. Please try again.');
            elements.tableBody.querySelector('#retry-shipments').addEventListener('click', loadShipments);
            handleAuthError(error);
        }
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

    async function showDetails(shipmentId) {
        elements.detailsContent.innerHTML = '<p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading shipment details...</p>';
        detailsModal.show();
        try {
            const response = await MedLineAPI.requestJson(`shipments/get.php?shipment_id=${encodeURIComponent(shipmentId)}`);
            const list = Array.isArray(response.data) ? response.data : (response.data ? [response.data] : []);
            const shipment = list.find(item => String(item.shipment_id) === String(shipmentId)) || list[0] || {};
            const fragment = document.createDocumentFragment();
            [
                ['Shipment ID', shipment.shipment_id],
                ['Request ID', shipment.request_id],
                ['Vehicle ID', shipment.vehicle_id],
                ['Driver ID', shipment.driver_id],
                ['Status', shipment.status],
                ['Estimated Delivery', shipment.estimated_delivery],
                ['Dispatched At', shipment.dispatched_at],
                ['Delivered At', shipment.delivered_at],
                ['Clinic Name', shipment.clinic_name],
                ['Request Urgency Score', shipment.urgency_score]
            ].forEach(([label, value]) => fragment.append(detailRow(label, value)));

            elements.detailsContent.replaceChildren(fragment);
        } catch (error) {
            elements.detailsContent.innerHTML = '<div class="alert alert-danger mb-0"></div>';
            elements.detailsContent.firstChild.textContent = errorMessage(error, 'Unable to load shipment details.');
            handleAuthError(error);
        }
    }

    function appendTable(container, headings, rows, emptyMessage) {
        if (!rows.length) {
            const empty = document.createElement('p');
            empty.className = 'text-muted-custom small mb-0';
            empty.textContent = emptyMessage;
            container.append(empty);
            return;
        }
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        const table = document.createElement('table');
        table.className = 'table users-table align-middle mb-0';
        const head = document.createElement('thead');
        const headRow = document.createElement('tr');
        headings.forEach(heading => {
            const cell = document.createElement('th');
            cell.scope = 'col';
            cell.textContent = heading;
            headRow.append(cell);
        });
        head.append(headRow);
        table.append(head);
        const body = document.createElement('tbody');
        rows.forEach(rowData => {
            const row = document.createElement('tr');
            rowData.forEach(value => {
                const cell = document.createElement('td');
                if (value instanceof Node) cell.append(value);
                else cell.textContent = displayValue(value);
                row.append(cell);
            });
            body.append(row);
        });
        table.append(body);
        wrapper.append(table);
        container.append(wrapper);
    }

    function trackingSummary(shipment) {
        const section = document.createElement('section');
        section.className = 'mb-4';
        const heading = document.createElement('h3');
        heading.className = 'section-title mb-3';
        heading.textContent = 'Shipment';
        section.append(heading);
        const grid = document.createElement('div');
        grid.className = 'row g-0 border rounded-2 overflow-hidden';
        [
            ['Shipment ID', shipment.shipment_id],
            ['Request ID', shipment.request_id],
            ['Vehicle ID', shipment.vehicle_id],
            ['Driver ID', shipment.driver_id],
            ['Status', shipment.status],
            ['Estimated Delivery', shipment.estimated_delivery],
            ['Dispatched At', shipment.dispatched_at],
            ['Delivered At', shipment.delivered_at]
        ].forEach(([label, value]) => {
            const cell = document.createElement('div');
            cell.className = 'col-12 col-sm-6 p-3 border-bottom';
            cell.append(detailRow(label, value));
            grid.append(cell);
        });
        section.append(grid);
        return section;
    }

    function alertAction(alert) {
        if (alert.status === 'Resolved') return null;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary';
        button.dataset.alertAction = alert.status === 'Open' ? 'acknowledge' : 'resolve';
        button.dataset.alertId = alert.alert_id;
        button.textContent = alert.status === 'Open' ? 'Acknowledge' : 'Resolve';
        return button;
    }

    function renderTracking(data) {
        const shipment = data.shipment || {};
        const telemetry = Array.isArray(data.telemetry) ? data.telemetry : [];
        const alerts = Array.isArray(data.alerts) ? data.alerts : [];
        const fragment = document.createDocumentFragment();

        fragment.append(trackingSummary(shipment));

        const telemetrySection = document.createElement('section');
        telemetrySection.className = 'mb-4';
        const telemetryHeading = document.createElement('h3');
        telemetryHeading.className = 'section-title mb-3';
        telemetryHeading.textContent = 'Telemetry';
        telemetrySection.append(telemetryHeading);
        appendTable(
            telemetrySection,
            ['Event ID', 'Event Type', 'Temperature', 'Latitude', 'Longitude', 'Recorded At'],
            telemetry.map(event => [
                event.event_id,
                event.event_type,
                event.temperature !== null ? `${event.temperature}°C` : null,
                event.latitude,
                event.longitude,
                event.recorded_at
            ]),
            'No telemetry events returned for this shipment.'
        );
        fragment.append(telemetrySection);

        const alertsSection = document.createElement('section');
        const alertsHeading = document.createElement('h3');
        alertsHeading.className = 'section-title mb-3';
        alertsHeading.textContent = 'Alerts';
        alertsSection.append(alertsHeading);
        appendTable(
            alertsSection,
            ['Alert ID', 'Type', 'Message', 'Value', 'Threshold', 'Status', 'Created At', 'Resolved At', 'Actions'],
            alerts.map(alert => [
                alert.alert_id,
                alert.alert_type,
                alert.alert_message,
                alert.alert_value !== null ? `${alert.alert_value}°C` : null,
                alert.threshold_value !== null ? `${alert.threshold_value}°C` : null,
                alert.status,
                alert.created_at,
                alert.resolved_at,
                alertAction(alert)
            ]),
            'No telemetry alerts returned for this shipment.'
        );
        fragment.append(alertsSection);

        elements.trackingContent.replaceChildren(fragment);
    }

    async function showTracking(shipmentId, options = {}) {
        state.selectedShipmentId = shipmentId;
        state.trackingOpen = true;
        elements.trackingContent.innerHTML = '<p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading shipment tracking...</p>';
        if (!options.keepOpen) trackingModal.show();
        try {
            const response = await MedLineAPI.requestJson(`shipments/get_tracking.php?shipment_id=${encodeURIComponent(shipmentId)}`);
            renderTracking(response.data || {});
        } catch (error) {
            elements.trackingContent.innerHTML = '<div class="alert alert-danger mb-0"></div>';
            elements.trackingContent.firstChild.textContent = errorMessage(error, 'Unable to load shipment tracking.');
            handleAuthError(error);
        }
    }

    function optionLabel(parts) {
        return parts.filter(part => part !== null && part !== undefined && String(part).trim() !== '').join(' · ');
    }

    function setOptions(select, options, placeholder, statusElement, emptyMessage) {
        select.replaceChildren(new Option(placeholder, ''));
        options.forEach(option => select.add(new Option(option.label, option.value)));
        select.disabled = options.length === 0;
        statusElement.textContent = options.length ? `${options.length} available.` : emptyMessage;
    }

    async function loadCreateOptions() {
        elements.submitButton.disabled = true;
        elements.request.disabled = true;
        elements.vehicle.disabled = true;
        elements.driver.disabled = true;
        elements.request.innerHTML = '<option value="">Loading approved requests...</option>';
        elements.vehicle.innerHTML = '<option value="">Loading vehicles...</option>';
        elements.driver.innerHTML = '<option value="">Loading drivers...</option>';
        try {
            const [requestsResponse, vehiclesResponse, driversResponse] = await Promise.all([
                MedLineAPI.requestJson('requests/get.php'),
                MedLineAPI.requestJson('vehicles/list.php'),
                MedLineAPI.requestJson('drivers/list.php')
            ]);

            const existingRequestIds = new Set(state.shipments.map(s => Number(s.request_id)));

            const requests = (Array.isArray(requestsResponse.data) ? requestsResponse.data : []).filter(request => {
                const isApproved = request.status === 'Approved';
                const isTriaged = request.urgency_score !== null && request.urgency_score !== undefined && request.urgency_score !== '';
                const hasNoShipment = !existingRequestIds.has(Number(request.request_id));
                return isApproved && isTriaged && hasNoShipment;
            });

            const vehicles = (Array.isArray(vehiclesResponse.data) ? vehiclesResponse.data : []).filter(vehicle => vehicle.status === 'Available');
            const drivers = (Array.isArray(driversResponse.data) ? driversResponse.data : []).filter(driver => driver.status === 'Available');

            setOptions(
                elements.request,
                requests.map(request => ({
                    value: request.request_id,
                    label: optionLabel([
                        `Request #${request.request_id}`,
                        request.clinic_name,
                        `Urgency ${request.urgency_score}`,
                        `Requested ${request.requested_at}`,
                        request.required_by ? `Required ${request.required_by}` : null
                    ])
                })),
                'Select an approved request',
                elements.requestOptionsStatus,
                'No approved and triaged requests are available for shipment creation.'
            );

            setOptions(
                elements.vehicle,
                vehicles.map(vehicle => ({
                    value: vehicle.vehicle_id,
                    label: optionLabel([
                        vehicle.vehicle_number,
                        vehicle.vehicle_type,
                        vehicle.capacity !== null && vehicle.capacity !== undefined ? `Capacity ${vehicle.capacity}` : null,
                        Number(vehicle.refrigerated) === 1 ? 'Refrigerated' : 'Standard',
                        vehicle.status
                    ])
                })),
                'Select an available vehicle',
                elements.vehicleOptionsStatus,
                'No available vehicles are returned.'
            );

            setOptions(
                elements.driver,
                drivers.map(driver => ({
                    value: driver.driver_id,
                    label: optionLabel([
                        driver.driver_name,
                        driver.license_number ? `License ${driver.license_number}` : null,
                        driver.status
                    ])
                })),
                'Select an available driver',
                elements.driverOptionsStatus,
                'No available drivers are returned.'
            );

            state.createOptionsLoaded = true;
            updateCreateButton();
        } catch (error) {
            elements.request.innerHTML = '<option value="">Unable to load request options</option>';
            elements.vehicle.innerHTML = '<option value="">Unable to load vehicle options</option>';
            elements.driver.innerHTML = '<option value="">Unable to load driver options</option>';
            elements.requestOptionsStatus.textContent = errorMessage(error, 'Unable to load shipment creation options.');
            elements.vehicleOptionsStatus.textContent = '';
            elements.driverOptionsStatus.textContent = '';
            handleAuthError(error);
        }
    }

    function resetCreateForm() {
        elements.createForm.reset();
        elements.createForm.classList.remove('was-validated');
        elements.createError.classList.add('d-none');
        state.createOptionsLoaded = false;
    }

    function futureDateValid() {
        if (!elements.estimatedDelivery.value) return true;
        return new Date(elements.estimatedDelivery.value).getTime() > Date.now();
    }

    function updateCreateButton() {
        elements.submitButton.disabled = !state.createOptionsLoaded || !elements.request.value || !elements.vehicle.value || !elements.driver.value;
    }

    function validCreateForm() {
        elements.estimatedDelivery.setCustomValidity(futureDateValid() ? '' : 'Estimated delivery must be in the future.');
        elements.createForm.classList.add('was-validated');
        return elements.createForm.checkValidity();
    }

    async function submitCreate(event) {
        event.preventDefault();
        elements.createError.classList.add('d-none');
        if (!validCreateForm()) return;
        const defaultContent = '<i class="bi bi-truck-front me-2" aria-hidden="true"></i>Create Shipment';
        setBusy(elements.submitButton, true, 'Creating...', defaultContent);
        const body = {
            request_id: Number(elements.request.value),
            vehicle_id: Number(elements.vehicle.value),
            driver_id: Number(elements.driver.value)
        };
        if (elements.estimatedDelivery.value) {
            body.estimated_delivery = `${elements.estimatedDelivery.value.replace('T', ' ')}:00`;
        }
        try {
            const response = await MedLineAPI.requestJson('shipments/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const shipmentId = response.data?.shipment_id;
            createModal.hide();
            resetCreateForm();
            showFeedback(response.message || 'Shipment created successfully.');
            await loadShipments();
            if (shipmentId) showDetails(shipmentId);
        } catch (error) {
            elements.createError.textContent = errorMessage(error, 'Unable to create shipment.');
            elements.createError.classList.remove('d-none');
            handleAuthError(error);
        } finally {
            setBusy(elements.submitButton, false, '', defaultContent);
            updateCreateButton();
        }
    }

    function openConfirm(action, shipment) {
        state.pendingAction = { type: action, shipmentId: Number(shipment.shipment_id) };
        elements.confirmError.classList.add('d-none');
        const labels = {
            dispatch: ['Dispatch shipment', `Dispatch shipment #${shipment.shipment_id}? The vehicle will become In Transit and the driver will become On Trip.`],
            'in-transit': ['Mark in transit', `Move shipment #${shipment.shipment_id} from Dispatched to In Transit?`],
            delivered: ['Mark delivered', `Mark shipment #${shipment.shipment_id} as Delivered? The related Approved request will become Completed and assigned resources will become Available.`],
            resolve: ['Resolve alert', `Resolve telemetry alert #${state.pendingAction?.alertId}? This is the final alert status.`]
        };
        const label = labels[action];
        elements.confirmTitle.textContent = label[0];
        elements.confirmLabel.textContent = label[1];
        elements.confirmButton.textContent = label[0];
        confirmModal.show();
    }

    function openAlertConfirm(alertId) {
        state.pendingAction = { type: 'resolve', alertId: Number(alertId), shipmentId: state.selectedShipmentId };
        elements.confirmError.classList.add('d-none');
        elements.confirmTitle.textContent = 'Resolve alert';
        elements.confirmLabel.textContent = `Resolve telemetry alert #${alertId}? This is the final alert status.`;
        elements.confirmButton.textContent = 'Resolve alert';
        confirmModal.show();
    }

    async function validateAssignment(shipment, button) {
        const defaultContent = button.innerHTML;
        setBusy(button, true, 'Checking...', defaultContent);
        try {
            const response = await MedLineAPI.requestJson('shipments/validate-assignment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    shipment_id: Number(shipment.shipment_id),
                    vehicle_id: Number(shipment.vehicle_id),
                    driver_id: Number(shipment.driver_id)
                })
            });
            showFeedback(response.message || `Assignment for shipment #${shipment.shipment_id} is valid.`);
        } catch (error) {
            showFeedback(errorMessage(error, 'Unable to validate the shipment assignment.'), 'danger');
            handleAuthError(error);
        } finally {
            setBusy(button, false, '', defaultContent);
        }
    }

    async function submitConfirm(event) {
        event.preventDefault();
        if (!state.pendingAction) return;
        const action = state.pendingAction;
        const defaultContent = elements.confirmButton.textContent;
        setBusy(elements.confirmButton, true, 'Working...', defaultContent);
        try {
            let response;
            if (action.type === 'dispatch') {
                response = await MedLineAPI.requestJson('shipments/dispatch.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ shipment_id: action.shipmentId })
                });
            }
            if (action.type === 'in-transit' || action.type === 'delivered') {
                response = await MedLineAPI.requestJson('shipments/update_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        shipment_id: action.shipmentId,
                        status: action.type === 'in-transit' ? 'In Transit' : 'Delivered'
                    })
                });
            }
            if (action.type === 'resolve') {
                response = await MedLineAPI.requestJson('telemetry/update_alert_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ alert_id: action.alertId, status: 'Resolved' })
                });
            }
            confirmModal.hide();
            showFeedback(response.message || 'Shipment action completed.');
            await loadShipments();
            if (state.trackingOpen && state.selectedShipmentId) {
                await showTracking(state.selectedShipmentId, { keepOpen: true });
            }
        } catch (error) {
            elements.confirmError.textContent = errorMessage(error, 'Unable to complete the requested action.');
            elements.confirmError.classList.remove('d-none');
            handleAuthError(error);
        } finally {
            setBusy(elements.confirmButton, false, '', defaultContent);
        }
    }

    async function acknowledgeAlert(alertId) {
        try {
            const response = await MedLineAPI.requestJson('telemetry/update_alert_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ alert_id: Number(alertId), status: 'Acknowledged' })
            });
            showFeedback(response.message || 'Telemetry alert acknowledged.');
            if (state.selectedShipmentId) {
                await showTracking(state.selectedShipmentId, { keepOpen: true });
            }
        } catch (error) {
            showFeedback(errorMessage(error, 'Unable to acknowledge telemetry alert.'), 'danger');
            handleAuthError(error);
        }
    }

    elements.search.addEventListener('input', renderShipments);
    elements.statusFilter.addEventListener('change', renderShipments);
    elements.refreshButton.addEventListener('click', loadShipments);
    elements.openCreateButton.addEventListener('click', () => {
        resetCreateForm();
        createModal.show();
        loadCreateOptions();
    });
    [elements.request, elements.vehicle, elements.driver].forEach(select => select.addEventListener('change', updateCreateButton));
    elements.createForm.addEventListener('submit', submitCreate);
    elements.confirmForm.addEventListener('submit', submitConfirm);
    elements.detailsModal.addEventListener('hidden.bs.modal', () => {
        if (!state.trackingOpen) state.selectedShipmentId = null;
    });
    elements.trackingModal.addEventListener('hidden.bs.modal', () => {
        state.trackingOpen = false;
        state.selectedShipmentId = null;
    });

    elements.tableBody.addEventListener('click', event => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const shipment = state.shipments.find(item => String(item.shipment_id) === button.dataset.shipmentId);
        if (!shipment) return;
        if (button.dataset.action === 'details') showDetails(shipment.shipment_id);
        if (button.dataset.action === 'tracking') showTracking(shipment.shipment_id);
        if (button.dataset.action === 'validate') validateAssignment(shipment, button);
        if (button.dataset.action === 'dispatch' || button.dataset.action === 'in-transit' || button.dataset.action === 'delivered') {
            openConfirm(button.dataset.action, shipment);
        }
    });

    elements.trackingContent.addEventListener('click', event => {
        const button = event.target.closest('[data-alert-action]');
        if (!button) return;
        if (button.dataset.alertAction === 'acknowledge') acknowledgeAlert(button.dataset.alertId);
        if (button.dataset.alertAction === 'resolve') openAlertConfirm(button.dataset.alertId);
    });

    loadShipments();
});
