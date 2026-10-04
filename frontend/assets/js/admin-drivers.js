document.addEventListener('DOMContentLoaded', () => {
    const state = { drivers: [], selectedDriverId: null, availableUsers: [] };
    const elements = {
        tableBody: document.querySelector('#drivers-table-body'),
        empty: document.querySelector('#drivers-empty'),
        count: document.querySelector('#drivers-count'),
        search: document.querySelector('#driver-search'),
        statusFilter: document.querySelector('#driver-status-filter'),
        feedback: document.querySelector('#drivers-feedback'),
        addButton: document.querySelector('#add-driver-button'),
        modal: document.querySelector('#driver-modal'),
        form: document.querySelector('#driver-form'),
        formError: document.querySelector('#driver-form-error'),
        driverUser: document.querySelector('#driver-user'),
        licenseNumber: document.querySelector('#driver-license-number'),
        status: document.querySelector('#driver-status'),
        saveButton: document.querySelector('#save-driver-button'),
        detailsModal: document.querySelector('#driver-details-modal'),
        detailsContent: document.querySelector('#driver-details-content'),
        statusModal: document.querySelector('#driver-status-modal'),
        statusForm: document.querySelector('#driver-status-form'),
        statusLabel: document.querySelector('#driver-status-label'),
        statusCurrent: document.querySelector('#driver-status-current'),
        statusError: document.querySelector('#driver-status-error'),
        statusId: document.querySelector('#driver-status-id'),
        statusSelect: document.querySelector('#driver-status-select'),
        statusButton: document.querySelector('#update-driver-status-button')
    };
    const createModal = new bootstrap.Modal(elements.modal);
    const detailsModal = new bootstrap.Modal(elements.detailsModal);
    const statusModal = new bootstrap.Modal(elements.statusModal);

    function errorMessage(error, fallback) {
        return {
            400: 'Please check the driver information and try again.',
            401: 'Your session has expired. Please sign in again.',
            403: 'You do not have permission to manage drivers.',
            404: 'The requested driver was not found.',
            405: 'That action is not allowed.',
            409: 'A driver profile already exists for that user or license number.',
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

    function statusClass(status) {
        if (status === 'Available') return 'badge-soft-success';
        if (status === 'Inactive') return 'badge-soft-warning';
        return 'badge-soft-info';
    }

    function licenseLabel(value) {
        return value === null || value === undefined || String(value).trim() === '' ? 'Not provided' : value;
    }

    function filteredDrivers() {
        const query = elements.search.value.trim().toLowerCase();
        const status = elements.statusFilter.value;
        return state.drivers.filter(driver => {
            const matchesQuery = !query || [driver.driver_name, driver.email, driver.license_number].some(value => String(value ?? '').toLowerCase().includes(query));
            return matchesQuery && (!status || driver.status === status);
        });
    }

    function action(label, icon, name, driver) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary users-action';
        button.dataset.action = name;
        button.dataset.driverId = driver.driver_id;
        button.title = label;
        button.setAttribute('aria-label', `${label}: ${driver.driver_name}`);
        button.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i><span class="visually-hidden">${label}</span>`;
        button.dataset.defaultContent = button.innerHTML;
        return button;
    }

    function createRow(driver) {
        const row = document.createElement('tr');
        const driverCell = document.createElement('td');
        driverCell.innerHTML = '<strong class="users-name"></strong><span class="users-email"></span>';
        driverCell.querySelector('.users-name').textContent = driver.driver_name || 'Not provided';
        driverCell.querySelector('.users-email').textContent = driver.email || '';

        const licenseCell = document.createElement('td');
        licenseCell.textContent = licenseLabel(driver.license_number);
        const statusCell = document.createElement('td');
        statusCell.innerHTML = `<span class="badge ${statusClass(driver.status)}">${driver.status || 'Not provided'}</span>`;
        const createdCell = document.createElement('td');
        createdCell.textContent = driver.created_at || 'Not provided';
        const actionsCell = document.createElement('td');
        actionsCell.className = 'text-end text-nowrap';
        actionsCell.append(action('View details', 'bi-eye', 'details', driver), action('Change status', 'bi-arrow-repeat', 'status', driver));
        row.append(driverCell, licenseCell, statusCell, createdCell, actionsCell);
        return row;
    }

    function renderDrivers() {
        const drivers = filteredDrivers();
        elements.tableBody.replaceChildren();
        elements.empty.classList.toggle('d-none', drivers.length > 0);
        elements.count.textContent = `${drivers.length} of ${state.drivers.length} drivers`;
        elements.tableBody.classList.toggle('d-none', drivers.length === 0);
        drivers.forEach(driver => elements.tableBody.append(createRow(driver)));
    }

    async function loadDrivers() {
        elements.tableBody.innerHTML = '<tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading drivers...</span></td></tr>';
        elements.tableBody.classList.remove('d-none');
        try {
            const response = await MedLineAPI.requestJson('drivers/list.php');
            state.drivers = Array.isArray(response.data) ? response.data : [];
            renderDrivers();
        } catch (error) {
            elements.count.textContent = 'Unable to load drivers';
            elements.tableBody.innerHTML = `<tr><td colspan="5"><div class="error-state"><i class="bi bi-exclamation-circle error-state-icon" aria-hidden="true"></i><p class="mb-3">${errorMessage(error, 'Unable to load drivers. Please try again.')}</p><button class="btn btn-primary btn-sm" type="button" id="retry-drivers"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Retry</button></div></td></tr>`;
            document.querySelector('#retry-drivers')?.addEventListener('click', loadDrivers);
            if (error.status === 401) window.location.href = '../frontend/login.php';
        }
    }

    async function loadAvailableUsers() {
        elements.driverUser.replaceChildren();
        const loadingOption = new Option('Loading driver users...', '');
        loadingOption.disabled = true;
        loadingOption.selected = true;
        elements.driverUser.appendChild(loadingOption);
        elements.driverUser.disabled = true;
        elements.saveButton.disabled = true;
        try {
            const response = await MedLineAPI.requestJson('drivers/available-users.php');
            state.availableUsers = Array.isArray(response.data) ? response.data : [];
            elements.driverUser.replaceChildren();
            if (state.availableUsers.length === 0) {
                const emptyOption = new Option('No eligible Driver users available', '');
                emptyOption.disabled = true;
                emptyOption.selected = true;
                elements.driverUser.appendChild(emptyOption);
                elements.driverUser.disabled = true;
                elements.saveButton.disabled = true;
                return;
            }

            elements.driverUser.appendChild(new Option('Select a driver user', ''));
            state.availableUsers.forEach(user => {
                const label = `${user.full_name} (${user.email})`;
                elements.driverUser.appendChild(new Option(label, String(user.user_id)));
            });
            elements.driverUser.disabled = false;
            elements.saveButton.disabled = false;
        } catch (error) {
            elements.driverUser.replaceChildren();
            const errorOption = new Option(errorMessage(error, 'Unable to load driver users.'), '');
            errorOption.disabled = true;
            errorOption.selected = true;
            elements.driverUser.appendChild(errorOption);
            elements.driverUser.disabled = true;
            elements.saveButton.disabled = true;
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

    async function showDetails(driverId) {
        elements.detailsContent.innerHTML = '<p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading driver details...</p>';
        detailsModal.show();
        try {
            const response = await MedLineAPI.requestJson(`drivers/details.php?driver_id=${encodeURIComponent(driverId)}`);
            const driver = response.data;
            elements.detailsContent.replaceChildren();
            [
                ['Driver name', driver.driver_name],
                ['Email', driver.email],
                ['License number', licenseLabel(driver.license_number) === 'Not provided' ? '' : driver.license_number],
                ['Operational status', driver.status],
                ['Created', driver.created_at]
            ].forEach(([label, value]) => elements.detailsContent.append(detailRow(label, value)));
        } catch (error) {
            elements.detailsContent.innerHTML = `<div class="alert alert-danger mb-0">${errorMessage(error, 'Unable to load driver details.')}</div>`;
        }
    }

    function resetDriverForm() {
        elements.form.reset();
        elements.status.value = 'Available';
        clearFormError();
        elements.form.classList.remove('was-validated');
    }

    async function submitDriver(event) {
        event.preventDefault();
        clearFormError();
        elements.form.classList.add('was-validated');
        if (!elements.form.checkValidity()) {
            return;
        }

        const userId = Number(elements.driverUser.value);
        if (!userId || Number.isNaN(userId)) {
            showFormError('Select a valid driver user.');
            return;
        }

        const payload = {
            user_id: userId,
            license_number: elements.licenseNumber.value.trim(),
            status: elements.status.value
        };

        setBusy(elements.saveButton, true, 'Creating...');
        try {
            const response = await MedLineAPI.requestJson('drivers/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            createModal.hide();
            const driverName = response.data?.driver_name || payload.user_id;
            showFeedback(`Driver ${driverName} created successfully.`);
            await loadDrivers();
            await loadAvailableUsers();
        } catch (error) {
            showFormError(errorMessage(error, 'Unable to create driver profile. Please try again.'));
        } finally {
            setBusy(elements.saveButton, false);
        }
    }

    function openStatus(driver) {
        state.selectedDriverId = driver.driver_id;
        elements.statusLabel.textContent = `Choose a new operational status for ${driver.driver_name}.`;
        elements.statusCurrent.textContent = `Current status: ${driver.status || 'Not provided'}`;
        elements.statusId.value = driver.driver_id;
        elements.statusSelect.value = driver.status;
        elements.statusError.classList.add('d-none');
        statusModal.show();
    }

    async function updateStatus(event) {
        event.preventDefault();
        const driver = state.drivers.find(item => Number(item.driver_id) === Number(state.selectedDriverId));
        const status = elements.statusSelect.value;
        if (!driver || status === driver.status) {
            elements.statusError.textContent = 'Choose a different operational status before updating.';
            elements.statusError.classList.remove('d-none');
            return;
        }
        if (!window.confirm(`Change ${driver.driver_name} from ${driver.status} to ${status}?`)) {
            return;
        }
        setBusy(elements.statusButton, true, 'Updating...');
        try {
            const response = await MedLineAPI.requestJson('drivers/status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ driver_id: Number(driver.driver_id), status })
            });
            driver.status = response.data?.status ?? status;
            statusModal.hide();
            showFeedback(`Operational status for ${driver.driver_name} updated successfully.`);
            renderDrivers();
        } catch (error) {
            elements.statusError.textContent = errorMessage(error, 'Unable to update driver status.');
            elements.statusError.classList.remove('d-none');
            if (error.status === 401) window.location.href = '../frontend/login.php';
        } finally {
            setBusy(elements.statusButton, false);
        }
    }

    elements.addButton?.addEventListener('click', async () => {
        resetDriverForm();
        await loadAvailableUsers();
        createModal.show();
    });
    elements.search.addEventListener('input', renderDrivers);
    elements.statusFilter.addEventListener('change', renderDrivers);
    elements.form.addEventListener('submit', submitDriver);
    elements.statusForm.addEventListener('submit', updateStatus);
    elements.tableBody.addEventListener('click', event => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const driver = state.drivers.find(item => String(item.driver_id) === button.dataset.driverId);
        if (!driver) return;
        if (button.dataset.action === 'details') showDetails(driver.driver_id);
        if (button.dataset.action === 'status') openStatus(driver);
    });
    elements.saveButton.dataset.defaultContent = elements.saveButton.innerHTML;
    elements.statusButton.dataset.defaultContent = elements.statusButton.innerHTML;
    loadDrivers();
    loadAvailableUsers();
});
