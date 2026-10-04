document.addEventListener('DOMContentLoaded', () => {
    const state = { clinics: [], editingClinicId: null };
    const elements = {
        tableBody: document.querySelector('#clinics-table-body'),
        empty: document.querySelector('#clinics-empty'),
        count: document.querySelector('#clinics-count'),
        search: document.querySelector('#clinic-search'),
        feedback: document.querySelector('#clinics-feedback'),
        form: document.querySelector('#clinic-form'),
        formError: document.querySelector('#clinic-form-error'),
        id: document.querySelector('#clinic-id'),
        name: document.querySelector('#clinic-name'),
        address: document.querySelector('#clinic-address'),
        region: document.querySelector('#clinic-region'),
        latitude: document.querySelector('#clinic-latitude'),
        longitude: document.querySelector('#clinic-longitude'),
        modal: document.querySelector('#clinic-modal'),
        modalKicker: document.querySelector('#clinic-modal-kicker'),
        modalTitle: document.querySelector('#clinic-modal-title'),
        saveButton: document.querySelector('#save-clinic-button'),
        detailsModal: document.querySelector('#clinic-details-modal'),
        detailsContent: document.querySelector('#clinic-details-content')
    };
    const clinicModal = new bootstrap.Modal(elements.modal);
    const detailsModal = new bootstrap.Modal(elements.detailsModal);

    function errorMessage(error, fallback) {
        return {
            400: 'Please check the clinic information and try again.',
            401: 'Your session has expired. Please sign in again.',
            403: 'You do not have permission to manage clinics.',
            404: 'The requested clinic was not found.',
            409: 'A clinic with that name already exists or is already in that state.',
            422: 'Please check the clinic information and try again.',
            500: 'The server could not complete the request.'
        }[error.status] || fallback;
    }

    function feedback(message, type = 'success') {
        elements.feedback.className = `alert alert-${type}`;
        elements.feedback.textContent = message;
    }

    function busy(button, isBusy, label) {
        if (isBusy) {
            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${label}`;
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.defaultContent;
        }
    }

    function coordinates(clinic) {
        if (clinic.latitude === null || clinic.latitude === undefined || clinic.longitude === null || clinic.longitude === undefined) {
            return 'Not provided';
        }
        return `${Number(clinic.latitude).toFixed(4)}, ${Number(clinic.longitude).toFixed(4)}`;
    }

    function filteredClinics() {
        const query = elements.search.value.trim().toLowerCase();
        if (!query) return state.clinics;
        return state.clinics.filter(clinic => [clinic.clinic_name, clinic.address, clinic.region, clinic.status, coordinates(clinic)].some(value => String(value || '').toLowerCase().includes(query)));
    }

    function action(label, icon, name, clinic) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary users-action';
        button.dataset.action = name;
        button.dataset.clinicId = clinic.clinic_id;
        button.title = label;
        button.setAttribute('aria-label', `${label}: ${clinic.clinic_name}`);
        button.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i><span class="visually-hidden">${label}</span>`;
        button.dataset.defaultContent = button.innerHTML;
        return button;
    }

    function row(clinic) {
        const item = document.createElement('tr');
        const clinicCell = document.createElement('td');
        clinicCell.innerHTML = '<strong class="users-name"></strong><span class="users-email"></span>';
        clinicCell.querySelector('.users-name').textContent = clinic.clinic_name;
        clinicCell.querySelector('.users-email').textContent = clinic.created_at ? `Added ${clinic.created_at}` : '';
        const address = document.createElement('td'); address.textContent = clinic.address || 'Not provided';
        const region = document.createElement('td'); region.textContent = clinic.region || 'Not provided';
        const coords = document.createElement('td'); coords.textContent = coordinates(clinic);
        const status = document.createElement('td');
        const badge = document.createElement('span'); badge.className = `badge ${clinic.status === 'Active' ? 'badge-soft-success' : 'badge-soft-warning'}`; badge.textContent = clinic.status; status.append(badge);
        const actions = document.createElement('td'); actions.className = 'text-end text-nowrap';
        actions.append(action('View details', 'bi-eye', 'details', clinic), action('Edit clinic', 'bi-pencil', 'edit', clinic), action(clinic.status === 'Active' ? 'Deactivate clinic' : 'Activate clinic', clinic.status === 'Active' ? 'bi-building-dash' : 'bi-building-check', 'status', clinic));
        item.append(clinicCell, address, region, coords, status, actions);
        return item;
    }

    function render() {
        const clinics = filteredClinics();
        elements.tableBody.replaceChildren();
        elements.empty.classList.toggle('d-none', clinics.length > 0);
        elements.count.textContent = `${clinics.length} of ${state.clinics.length} clinics`;
        elements.tableBody.classList.toggle('d-none', clinics.length === 0);
        clinics.forEach(clinic => elements.tableBody.append(row(clinic)));
    }

    async function loadClinics() {
        elements.tableBody.innerHTML = '<tr><td colspan="6"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading clinics...</span></td></tr>';
        try {
            const response = await MedLineAPI.requestJson('clinics/list.php');
            state.clinics = Array.isArray(response.data) ? response.data : [];
            render();
        } catch (error) {
            elements.count.textContent = 'Unable to load clinics';
            elements.tableBody.innerHTML = `<tr><td colspan="6"><div class="error-state"><i class="bi bi-exclamation-circle error-state-icon" aria-hidden="true"></i><p class="mb-0">${errorMessage(error, 'Unable to load clinics. Please try again.')}</p></div></td></tr>`;
            if (error.status === 401) window.location.href = '../frontend/login.php';
        }
    }

    function clearForm() {
        state.editingClinicId = null;
        elements.form.reset();
        elements.id.value = '';
        elements.modalKicker.textContent = 'NEW LOCATION';
        elements.modalTitle.textContent = 'Add Clinic';
        elements.saveButton.dataset.defaultContent = '<i class="bi bi-check2 me-1" aria-hidden="true"></i>Save Clinic';
        elements.formError.classList.add('d-none');
        elements.form.classList.remove('was-validated');
    }

    function openEdit(clinic) {
        state.editingClinicId = clinic.clinic_id;
        elements.id.value = clinic.clinic_id;
        elements.name.value = clinic.clinic_name || '';
        elements.address.value = clinic.address || '';
        elements.region.value = clinic.region || '';
        elements.latitude.value = clinic.latitude ?? '';
        elements.longitude.value = clinic.longitude ?? '';
        elements.modalKicker.textContent = 'LOCATION SETTINGS';
        elements.modalTitle.textContent = 'Edit Clinic';
        elements.saveButton.dataset.defaultContent = '<i class="bi bi-check2 me-1" aria-hidden="true"></i>Save Changes';
        elements.formError.classList.add('d-none');
        elements.form.classList.remove('was-validated');
        clinicModal.show();
    }

    async function save(event) {
        event.preventDefault();
        elements.form.classList.add('was-validated');
        if (!elements.form.checkValidity()) return;
        const numberOrNull = value => value === '' ? null : Number(value);
        const payload = { clinic_name: elements.name.value.trim(), address: elements.address.value.trim() || null, region: elements.region.value.trim() || null, latitude: numberOrNull(elements.latitude.value), longitude: numberOrNull(elements.longitude.value) };
        const endpoint = state.editingClinicId ? 'clinics/update.php' : 'clinics/create.php';
        if (state.editingClinicId) payload.clinic_id = state.editingClinicId;
        busy(elements.saveButton, true, state.editingClinicId ? 'Saving...' : 'Creating...');
        try {
            await MedLineAPI.requestJson(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            clinicModal.hide(); feedback(state.editingClinicId ? 'Clinic updated successfully.' : 'Clinic created successfully.'); await loadClinics();
        } catch (error) {
            elements.formError.textContent = errorMessage(error, 'Unable to save the clinic. Please try again.'); elements.formError.classList.remove('d-none');
        } finally { busy(elements.saveButton, false); }
    }

    function detailRow(label, value) {
        const item = document.createElement('div'); item.className = 'user-detail-row';
        const key = document.createElement('span'); key.textContent = label;
        const content = document.createElement('strong'); content.textContent = value || 'Not provided'; item.append(key, content); return item;
    }

    async function details(clinicId) {
        elements.detailsContent.innerHTML = '<p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading clinic details...</p>'; detailsModal.show();
        try {
            const response = await MedLineAPI.requestJson(`clinics/details.php?clinic_id=${encodeURIComponent(clinicId)}`);
            const clinic = response.data; elements.detailsContent.replaceChildren();
            [['Clinic name', clinic.clinic_name], ['Address', clinic.address], ['Region', clinic.region], ['Coordinates', coordinates(clinic)], ['Status', clinic.status], ['Created', clinic.created_at]].forEach(([label, value]) => elements.detailsContent.append(detailRow(label, value)));
            const heading = document.createElement('h3'); heading.className = 'section-title mt-4 mb-2'; heading.textContent = 'Clinic Staff'; elements.detailsContent.append(heading);
            const staff = clinic.staff || [];
            if (!staff.length) { const empty = document.createElement('p'); empty.className = 'text-muted-custom mb-0'; empty.textContent = 'No Clinic Staff are assigned.'; elements.detailsContent.append(empty); return; }
            const list = document.createElement('div'); list.className = 'list-group list-group-flush';
            staff.forEach(member => { const item = document.createElement('div'); item.className = 'list-group-item px-0 d-flex justify-content-between align-items-center'; item.innerHTML = '<div><strong class="staff-name"></strong><span class="users-email staff-email"></span></div><span class="badge staff-status"></span>'; item.querySelector('.staff-name').textContent = member.full_name; item.querySelector('.staff-email').textContent = member.email; const badge = item.querySelector('.staff-status'); badge.className = `badge ${member.status === 'Active' ? 'badge-soft-success' : 'badge-soft-warning'} staff-status`; badge.textContent = member.status; list.append(item); });
            elements.detailsContent.append(list);
        } catch (error) { elements.detailsContent.innerHTML = `<div class="alert alert-danger mb-0">${errorMessage(error, 'Unable to load clinic details.')}</div>`; }
    }

    async function changeStatus(clinic) {
        const next = clinic.status === 'Active' ? 'Inactive' : 'Active';
        if (!window.confirm(`${next === 'Inactive' ? 'Deactivate' : 'Activate'} ${clinic.clinic_name}?`)) return;
        const button = document.querySelector(`[data-action="status"][data-clinic-id="${clinic.clinic_id}"]`); if (button) busy(button, true, '');
        try { await MedLineAPI.requestJson('clinics/status.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ clinic_id: Number(clinic.clinic_id), status: next }) }); feedback(`Clinic ${next.toLowerCase()} successfully.`); await loadClinics(); }
        catch (error) { feedback(errorMessage(error, 'Unable to update clinic status.'), 'danger'); }
        finally { if (button) busy(button, false); }
    }

    elements.search.addEventListener('input', render);
    elements.form.addEventListener('submit', save);
    elements.modal.addEventListener('hidden.bs.modal', clearForm);
    elements.modal.addEventListener('show.bs.modal', event => { if (event.relatedTarget?.id === 'add-clinic-button' || !state.editingClinicId) clearForm(); });
    elements.tableBody.addEventListener('click', event => { const button = event.target.closest('[data-action]'); if (!button) return; const clinic = state.clinics.find(item => String(item.clinic_id) === button.dataset.clinicId); if (!clinic) return; if (button.dataset.action === 'details') details(clinic.clinic_id); if (button.dataset.action === 'edit') openEdit(clinic); if (button.dataset.action === 'status') changeStatus(clinic); });
    document.querySelectorAll('[data-bs-target="#clinic-modal"]').forEach(button => button.addEventListener('click', clearForm));
    elements.saveButton.dataset.defaultContent = elements.saveButton.innerHTML;
    loadClinics();
});
