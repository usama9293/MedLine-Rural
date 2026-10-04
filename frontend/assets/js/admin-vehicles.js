document.addEventListener('DOMContentLoaded', () => {
    const state = { vehicles: [], selectedVehicleId: null };
    const elements = {
        tableBody: document.querySelector('#vehicles-table-body'),
        empty: document.querySelector('#vehicles-empty'),
        count: document.querySelector('#vehicles-count'),
        search: document.querySelector('#vehicle-search'),
        statusFilter: document.querySelector('#vehicle-status-filter'),
        feedback: document.querySelector('#vehicles-feedback'),
        addButton: document.querySelector('#add-vehicle-button'),
        modal: document.querySelector('#vehicle-modal'),
        form: document.querySelector('#vehicle-form'),
        formError: document.querySelector('#vehicle-form-error'),
        vehicleNumber: document.querySelector('#vehicle-number'),
        vehicleType: document.querySelector('#vehicle-type'),
        capacity: document.querySelector('#vehicle-capacity'),
        refrigerated: document.querySelector('#vehicle-refrigerated'),
        status: document.querySelector('#vehicle-status'),
        saveButton: document.querySelector('#save-vehicle-button'),
        detailsModal: document.querySelector('#vehicle-details-modal'),
        detailsContent: document.querySelector('#vehicle-details-content'),
        statusModal: document.querySelector('#vehicle-status-modal'),
        statusForm: document.querySelector('#vehicle-status-form'),
        statusLabel: document.querySelector('#vehicle-status-label'),
        statusError: document.querySelector('#vehicle-status-error'),
        statusId: document.querySelector('#vehicle-status-id'),
        statusSelect: document.querySelector('#vehicle-status-select'),
        statusButton: document.querySelector('#update-vehicle-status-button')
    };
    const createModal = new bootstrap.Modal(elements.modal);
    const detailsModal = new bootstrap.Modal(elements.detailsModal);
    const statusModal = new bootstrap.Modal(elements.statusModal);

    function errorMessage(error, fallback) {
        return {
            400: 'Please check the vehicle information and try again.',
            401: 'Your session has expired. Please sign in again.',
            403: 'You do not have permission to manage vehicles.',
            404: 'The requested vehicle was not found.',
            409: 'A vehicle with that number already exists.',
            500: 'The server could not complete the request.'
        }[error.status] || fallback;
    }

    function showFeedback(message, type = 'success') {
        elements.feedback.className = `alert alert-${type}`;
        elements.feedback.textContent = message;
    }

    function showFormError(message) {
        elements.formError.textContent = message;
        elements.formError.classList.remove('d-none');
    }

    function clearFormError() {
        elements.formError.textContent = '';
        elements.formError.classList.add('d-none');
    }

    function setBusy(button, busy, label) {
        if (busy) {
            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${label}`;
            return;
        }
        button.disabled = false;
        button.innerHTML = button.dataset.defaultContent;
    }

    function refrigeratedLabel(value) {
        return Number(value) === 1 ? 'Yes' : 'No';
    }

    function statusClass(status) {
        if (status === 'Available') return 'badge-soft-success';
        if (status === 'Inactive') return 'badge-soft-warning';
        if (status === 'Maintenance') return 'badge-soft-danger';
        return 'badge-soft-info';
    }

    function filteredVehicles() {
        const query = elements.search.value.trim().toLowerCase();
        const status = elements.statusFilter.value;
        return state.vehicles.filter(vehicle => {
            const matchesQuery = !query || [vehicle.vehicle_number, vehicle.vehicle_type, vehicle.capacity, vehicle.status].some(value => String(value ?? '').toLowerCase().includes(query));
            return matchesQuery && (!status || vehicle.status === status);
        });
    }

    function action(label, icon, name, vehicle) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary users-action';
        button.dataset.action = name;
        button.dataset.vehicleId = vehicle.vehicle_id;
        button.title = label;
        button.setAttribute('aria-label', `${label}: ${vehicle.vehicle_number}`);
        button.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i><span class="visually-hidden">${label}</span>`;
        button.dataset.defaultContent = button.innerHTML;
        return button;
    }

    function createRow(vehicle) {
        const row = document.createElement('tr');
        const vehicleCell = document.createElement('td');
        vehicleCell.innerHTML = '<strong class="users-name"></strong><span class="users-email"></span>';
        vehicleCell.querySelector('.users-name').textContent = vehicle.vehicle_number;
        vehicleCell.querySelector('.users-email').textContent = vehicle.created_at ? `Added ${vehicle.created_at}` : '';

        const typeCell = document.createElement('td');
        typeCell.textContent = vehicle.vehicle_type || 'Not provided';
        const capacityCell = document.createElement('td');
        capacityCell.textContent = vehicle.capacity === null || vehicle.capacity === undefined || vehicle.capacity === '' ? 'Not provided' : vehicle.capacity;
        const refrigeratedCell = document.createElement('td');
        refrigeratedCell.innerHTML = `<span class="badge ${Number(vehicle.refrigerated) === 1 ? 'badge-soft-info' : 'badge-soft-warning'}">${refrigeratedLabel(vehicle.refrigerated)}</span>`;
        const statusCell = document.createElement('td');
        statusCell.innerHTML = `<span class="badge ${statusClass(vehicle.status)}">${vehicle.status}</span>`;
        const createdCell = document.createElement('td');
        createdCell.textContent = vehicle.created_at || 'Not provided';
        const actionsCell = document.createElement('td');
        actionsCell.className = 'text-end text-nowrap';
        actionsCell.append(action('View details', 'bi-eye', 'details', vehicle), action('Change status', 'bi-arrow-repeat', 'status', vehicle));
        row.append(vehicleCell, typeCell, capacityCell, refrigeratedCell, statusCell, createdCell, actionsCell);
        return row;
    }

    function renderVehicles() {
        const vehicles = filteredVehicles();
        elements.tableBody.replaceChildren();
        elements.empty.classList.toggle('d-none', vehicles.length > 0);
        elements.count.textContent = `${vehicles.length} of ${state.vehicles.length} vehicles`;
        elements.tableBody.classList.toggle('d-none', vehicles.length === 0);
        vehicles.forEach(vehicle => elements.tableBody.append(createRow(vehicle)));
    }

    async function loadVehicles() {
        elements.tableBody.innerHTML = '<tr><td colspan="7"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading vehicles...</span></td></tr>';
        elements.tableBody.classList.remove('d-none');
        try {
            const response = await MedLineAPI.requestJson('vehicles/list.php');
            state.vehicles = Array.isArray(response.data) ? response.data : [];
            renderVehicles();
        } catch (error) {
            elements.count.textContent = 'Unable to load vehicles';
            elements.tableBody.innerHTML = `<tr><td colspan="7"><div class="error-state"><i class="bi bi-exclamation-circle error-state-icon" aria-hidden="true"></i><p class="mb-3">${errorMessage(error, 'Unable to load vehicles. Please try again.')}</p><button class="btn btn-primary btn-sm" type="button" id="retry-vehicles"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Retry</button></div></td></tr>`;
            document.querySelector('#retry-vehicles')?.addEventListener('click', loadVehicles);
            if (error.status === 401) window.location.href = '../frontend/login.php';
        }
    }

    function detailRow(label, value) {
        const row = document.createElement('div');
        row.className = 'user-detail-row';
        const key = document.createElement('span');
        key.textContent = label;
        const content = document.createElement('strong');
        content.textContent = value || 'Not provided';
        row.append(key, content);
        return row;
    }

    async function showDetails(vehicleId) {
        elements.detailsContent.innerHTML = '<p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading vehicle details...</p>';
        detailsModal.show();
        try {
            const response = await MedLineAPI.requestJson(`vehicles/details.php?vehicle_id=${encodeURIComponent(vehicleId)}`);
            const vehicle = response.data;
            elements.detailsContent.replaceChildren();
            [
                ['Vehicle number', vehicle.vehicle_number],
                ['Vehicle type', vehicle.vehicle_type],
                ['Capacity', vehicle.capacity],
                ['Refrigerated', refrigeratedLabel(vehicle.refrigerated)],
                ['Status', vehicle.status],
                ['Created', vehicle.created_at]
            ].forEach(([label, value]) => elements.detailsContent.append(detailRow(label, value)));
        } catch (error) {
            elements.detailsContent.innerHTML = `<div class="alert alert-danger mb-0">${errorMessage(error, 'Unable to load vehicle details.')}</div>`;
        }
    }

    function resetVehicleForm() {
        elements.form.reset();
        elements.status.value = 'Available';
        elements.refrigerated.value = '0';
        clearFormError();
        elements.form.classList.remove('was-validated');
    }

    async function submitVehicle(event) {
        event.preventDefault();
        clearFormError();
        elements.form.classList.add('was-validated');
        if (!elements.form.checkValidity()) {
            return;
        }

        const payload = {
            vehicle_number: elements.vehicleNumber.value.trim(),
            vehicle_type: elements.vehicleType.value.trim(),
            capacity: Number(elements.capacity.value),
            refrigerated: elements.refrigerated.value === '1',
            status: elements.status.value
        };

        setBusy(elements.saveButton, true, 'Creating...');
        try {
            const response = await MedLineAPI.requestJson('vehicles/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            createModal.hide();
            showFeedback(`Vehicle ${response.data?.vehicle_number || payload.vehicle_number} created successfully.`);
            await loadVehicles();
        } catch (error) {
            showFormError(errorMessage(error, 'Unable to create vehicle. Please try again.'));
        } finally {
            setBusy(elements.saveButton, false);
        }
    }

    function openStatus(vehicle) {
        state.selectedVehicleId = vehicle.vehicle_id;
        elements.statusLabel.textContent = `Choose a new status for ${vehicle.vehicle_number}.`;
        elements.statusId.value = vehicle.vehicle_id;
        elements.statusSelect.value = vehicle.status;
        elements.statusError.classList.add('d-none');
        statusModal.show();
    }

    async function updateStatus(event) {
        event.preventDefault();
        const vehicle = state.vehicles.find(item => Number(item.vehicle_id) === Number(state.selectedVehicleId));
        const status = elements.statusSelect.value;
        if (!vehicle || status === vehicle.status) {
            elements.statusError.textContent = 'Choose a different status before updating.';
            elements.statusError.classList.remove('d-none');
            return;
        }
        if (!window.confirm(`Change ${vehicle.vehicle_number} from ${vehicle.status} to ${status}?`)) {
            return;
        }
        setBusy(elements.statusButton, true, 'Updating...');
        try {
            await MedLineAPI.requestJson('vehicles/update-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vehicle_id: Number(vehicle.vehicle_id), status })
            });
            statusModal.hide();
            showFeedback(`Vehicle ${vehicle.vehicle_number} status updated successfully.`);
            await loadVehicles();
        } catch (error) {
            elements.statusError.textContent = errorMessage(error, 'Unable to update vehicle status.');
            elements.statusError.classList.remove('d-none');
        } finally {
            setBusy(elements.statusButton, false);
        }
    }

    elements.addButton?.addEventListener('click', () => {
        resetVehicleForm();
        createModal.show();
    });
    elements.search.addEventListener('input', renderVehicles);
    elements.statusFilter.addEventListener('change', renderVehicles);
    elements.form.addEventListener('submit', submitVehicle);
    elements.statusForm.addEventListener('submit', updateStatus);
    elements.tableBody.addEventListener('click', event => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const vehicle = state.vehicles.find(item => String(item.vehicle_id) === button.dataset.vehicleId);
        if (!vehicle) return;
        if (button.dataset.action === 'details') showDetails(vehicle.vehicle_id);
        if (button.dataset.action === 'status') openStatus(vehicle);
    });
    elements.saveButton.dataset.defaultContent = elements.saveButton.innerHTML;
    elements.statusButton.dataset.defaultContent = elements.statusButton.innerHTML;
    loadVehicles();
});
