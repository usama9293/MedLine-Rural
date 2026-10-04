document.addEventListener('DOMContentLoaded', () => {
    const state = {
        users: [],
        roles: [],
        clinics: [],
        editingUserId: null
    };

    const elements = {
        tableBody: document.querySelector('#users-table-body'),
        emptyState: document.querySelector('#users-empty'),
        count: document.querySelector('#users-count'),
        search: document.querySelector('#user-search'),
        feedback: document.querySelector('#users-feedback'),
        form: document.querySelector('#user-form'),
        formError: document.querySelector('#form-error'),
        userId: document.querySelector('#user-id'),
        fullName: document.querySelector('#full-name'),
        email: document.querySelector('#user-email'),
        password: document.querySelector('#user-password'),
        passwordField: document.querySelector('#password-field'),
        role: document.querySelector('#user-role'),
        clinicField: document.querySelector('#clinic-field'),
        clinic: document.querySelector('#user-clinic'),
        modal: document.querySelector('#user-modal'),
        modalKicker: document.querySelector('#user-modal-kicker'),
        modalTitle: document.querySelector('#user-modal-title'),
        saveButton: document.querySelector('#save-user-button'),
        detailsModal: document.querySelector('#details-modal'),
        detailsContent: document.querySelector('#details-content'),
        resetModal: document.querySelector('#reset-modal'),
        resetForm: document.querySelector('#reset-form'),
        resetError: document.querySelector('#reset-error'),
        resetUserId: document.querySelector('#reset-user-id'),
        resetUserLabel: document.querySelector('#reset-user-label'),
        newPassword: document.querySelector('#new-password'),
        resetButton: document.querySelector('#reset-password-button')
    };

    const userModal = new bootstrap.Modal(elements.modal);
    const detailsModal = new bootstrap.Modal(elements.detailsModal);
    const resetModal = new bootstrap.Modal(elements.resetModal);

    function getErrorMessage(error, fallback) {
        const messages = {
            401: 'Your session has expired. Please sign in again.',
            403: 'You do not have permission to manage users.',
            404: 'The requested user or clinic was not found.',
            409: 'That email address is already in use or the account is already in that state.',
            422: 'Please check the submitted values and try again.',
            500: 'The server could not complete the request.'
        };
        return messages[error.status] || fallback;
    }

    function showFeedback(message, type = 'success') {
        elements.feedback.className = `alert alert-${type}`;
        elements.feedback.textContent = message;
    }

    function hideFeedback() {
        elements.feedback.className = 'alert d-none';
        elements.feedback.textContent = '';
    }

    function showFormError(message) {
        elements.formError.textContent = message;
        elements.formError.classList.remove('d-none');
    }

    function clearFormError() {
        elements.formError.textContent = '';
        elements.formError.classList.add('d-none');
    }

    function setButtonBusy(button, busy, busyLabel) {
        if (!busy) {
            button.disabled = false;
            button.innerHTML = button.dataset.defaultContent;
            return;
        }

        button.disabled = true;
        button.innerHTML = `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${busyLabel}`;
    }

    function populateSelect(select, options, placeholder, valueKey, labelKey) {
        select.replaceChildren(new Option(placeholder, ''));
        options.forEach(option => {
            select.append(new Option(option[labelKey], option[valueKey]));
        });
    }

    function updateClinicVisibility() {
        const selectedRole = state.roles.find(role => String(role.role_id) === elements.role.value);
        const clinicRequired = selectedRole?.role_name === 'Clinic Staff';
        elements.clinicField.classList.toggle('d-none', !clinicRequired);
        elements.clinic.required = clinicRequired;
        if (!clinicRequired) {
            elements.clinic.value = '';
        }
    }

    function getFilteredUsers() {
        const query = elements.search.value.trim().toLowerCase();
        if (!query) {
            return state.users;
        }

        return state.users.filter(user => [
            user.full_name,
            user.email,
            user.role_name,
            user.clinic_name,
            user.status
        ].some(value => String(value || '').toLowerCase().includes(query)));
    }

    function createActionButton(label, icon, action, user) {
        const button = document.createElement('button');
        button.className = 'btn btn-sm btn-outline-secondary users-action';
        button.type = 'button';
        button.dataset.action = action;
        button.dataset.userId = user.user_id;
        button.title = label;
        button.setAttribute('aria-label', `${label}: ${user.full_name}`);
        button.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i><span class="visually-hidden">${label}</span>`;
        button.dataset.defaultContent = button.innerHTML;
        return button;
    }

    function createUserRow(user) {
        const row = document.createElement('tr');
        const userCell = document.createElement('td');
        userCell.innerHTML = '<strong class="users-name"></strong><span class="users-email"></span>';
        userCell.querySelector('.users-name').textContent = user.full_name;
        userCell.querySelector('.users-email').textContent = user.email;

        const roleCell = document.createElement('td');
        roleCell.textContent = user.role_name;
        const clinicCell = document.createElement('td');
        clinicCell.textContent = user.clinic_name || 'Not assigned';
        const statusCell = document.createElement('td');
        const statusBadge = document.createElement('span');
        statusBadge.className = `badge ${user.status === 'Active' ? 'badge-soft-success' : 'badge-soft-warning'}`;
        statusBadge.textContent = user.status;
        statusCell.append(statusBadge);
        const actionsCell = document.createElement('td');
        actionsCell.className = 'text-end text-nowrap';

        [
            ['View details', 'bi-eye', 'details'],
            ['Edit user', 'bi-pencil', 'edit'],
            [user.status === 'Active' ? 'Deactivate user' : 'Activate user', user.status === 'Active' ? 'bi-person-dash' : 'bi-person-check', 'status'],
            ['Reset password', 'bi-key', 'reset']
        ].forEach(([label, icon, action]) => actionsCell.append(createActionButton(label, icon, action, user)));

        row.append(userCell, roleCell, clinicCell, statusCell, actionsCell);
        return row;
    }

    function renderUsers() {
        const filteredUsers = getFilteredUsers();
        elements.tableBody.replaceChildren();
        elements.emptyState.classList.toggle('d-none', filteredUsers.length > 0);
        elements.count.textContent = `${filteredUsers.length} of ${state.users.length} users`;

        if (filteredUsers.length === 0) {
            elements.tableBody.classList.add('d-none');
            return;
        }

        elements.tableBody.classList.remove('d-none');
        filteredUsers.forEach(user => elements.tableBody.append(createUserRow(user)));
    }

    async function loadLookups() {
        const [rolesResponse, clinicsResponse] = await Promise.all([
            MedLineAPI.requestJson('roles/list.php'),
            MedLineAPI.requestJson('clinics/list.php')
        ]);
        state.roles = rolesResponse.data || [];
        state.clinics = clinicsResponse.data || [];
        populateSelect(elements.role, state.roles, 'Select a role', 'role_id', 'role_name');
        populateSelect(elements.clinic, state.clinics, 'Select a clinic', 'clinic_id', 'clinic_name');
    }

    async function loadUsers() {
        elements.tableBody.innerHTML = '<tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading users...</span></td></tr>';
        try {
            const response = await MedLineAPI.requestJson('users/list.php');
            state.users = Array.isArray(response.data) ? response.data : [];
            renderUsers();
        } catch (error) {
            elements.tableBody.innerHTML = `<tr><td colspan="5"><div class="error-state"><i class="bi bi-exclamation-circle error-state-icon" aria-hidden="true"></i><p class="mb-0">${getErrorMessage(error, 'Unable to load users. Please try again.')}</p></div></td></tr>`;
            elements.count.textContent = 'Unable to load users';
            if (error.status === 401) {
                window.location.href = '../frontend/login.php';
            }
        }
    }

    function resetUserForm() {
        state.editingUserId = null;
        elements.form.reset();
        elements.userId.value = '';
        elements.modalKicker.textContent = 'NEW ACCOUNT';
        elements.modalTitle.textContent = 'Add User';
        elements.saveButton.dataset.defaultContent = '<i class="bi bi-check2 me-1" aria-hidden="true"></i>Save User';
        elements.passwordField.classList.remove('d-none');
        elements.password.required = true;
        clearFormError();
        updateClinicVisibility();
    }

    function openEditForm(user) {
        state.editingUserId = user.user_id;
        elements.userId.value = user.user_id;
        elements.fullName.value = user.full_name;
        elements.email.value = user.email;
        elements.role.value = user.role_id;
        elements.clinic.value = user.clinic_id || '';
        elements.modalKicker.textContent = 'ACCOUNT SETTINGS';
        elements.modalTitle.textContent = 'Edit User';
        elements.saveButton.dataset.defaultContent = '<i class="bi bi-check2 me-1" aria-hidden="true"></i>Save Changes';
        elements.passwordField.classList.add('d-none');
        elements.password.required = false;
        clearFormError();
        updateClinicVisibility();
        userModal.show();
    }

    async function submitUser(event) {
        event.preventDefault();
        clearFormError();
        elements.form.classList.add('was-validated');
        updateClinicVisibility();
        if (!elements.form.checkValidity()) {
            return;
        }

        const payload = {
            full_name: elements.fullName.value.trim(),
            email: elements.email.value.trim(),
            role_id: Number(elements.role.value),
            clinic_id: elements.clinic.value ? Number(elements.clinic.value) : null
        };
        const endpoint = state.editingUserId ? 'users/update.php' : 'users/create.php';
        if (state.editingUserId) {
            payload.user_id = state.editingUserId;
        } else {
            payload.password = elements.password.value;
        }

        setButtonBusy(elements.saveButton, true, state.editingUserId ? 'Saving...' : 'Creating...');
        try {
            await MedLineAPI.requestJson(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            userModal.hide();
            showFeedback(state.editingUserId ? 'User updated successfully.' : 'User created successfully.');
            await loadUsers();
        } catch (error) {
            showFormError(getErrorMessage(error, 'Unable to save the user. Please try again.'));
        } finally {
            setButtonBusy(elements.saveButton, false);
        }
    }

    async function showDetails(userId) {
        elements.detailsContent.innerHTML = '<p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading details...</p>';
        detailsModal.show();
        try {
            const response = await MedLineAPI.requestJson(`users/details.php?user_id=${encodeURIComponent(userId)}`);
            const user = response.data;
            elements.detailsContent.innerHTML = '';
            const details = [
                ['Full name', user.full_name],
                ['Email address', user.email],
                ['Role', user.role_name],
                ['Clinic', user.clinic_name || 'Not assigned'],
                ['Status', user.status],
                ['Created', user.created_at],
                ['Updated', user.updated_at]
            ];
            details.forEach(([label, value]) => {
                const row = document.createElement('div');
                row.className = 'user-detail-row';
                const labelElement = document.createElement('span');
                labelElement.textContent = label;
                const valueElement = document.createElement('strong');
                valueElement.textContent = value || 'Not available';
                row.append(labelElement, valueElement);
                elements.detailsContent.append(row);
            });
        } catch (error) {
            elements.detailsContent.innerHTML = `<div class="alert alert-danger mb-0">${getErrorMessage(error, 'Unable to load user details.')}</div>`;
        }
    }

    async function changeStatus(user) {
        const nextStatus = user.status === 'Active' ? 'Inactive' : 'Active';
        if (!window.confirm(`${nextStatus === 'Inactive' ? 'Deactivate' : 'Activate'} ${user.full_name}?`)) {
            return;
        }

        const button = document.querySelector(`[data-action="status"][data-user-id="${user.user_id}"]`);
        if (button) {
            setButtonBusy(button, true, '');
        }
        try {
            await MedLineAPI.requestJson('users/status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: Number(user.user_id), status: nextStatus })
            });
            showFeedback(`User ${nextStatus.toLowerCase()} successfully.`);
            await loadUsers();
        } catch (error) {
            showFeedback(getErrorMessage(error, 'Unable to update user status.'), 'danger');
        } finally {
            if (button) {
                setButtonBusy(button, false);
            }
        }
    }

    function openResetForm(user) {
        elements.resetForm.reset();
        elements.resetUserId.value = user.user_id;
        elements.resetUserLabel.textContent = `Set a new password for ${user.full_name}.`;
        elements.resetError.classList.add('d-none');
        elements.resetButton.dataset.defaultContent = 'Reset Password';
        resetModal.show();
    }

    async function resetPassword(event) {
        event.preventDefault();
        elements.resetForm.classList.add('was-validated');
        if (!elements.resetForm.checkValidity()) {
            return;
        }

        setButtonBusy(elements.resetButton, true, 'Resetting...');
        try {
            await MedLineAPI.requestJson('users/reset-password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: Number(elements.resetUserId.value), new_password: elements.newPassword.value })
            });
            resetModal.hide();
            showFeedback('User password reset successfully.');
        } catch (error) {
            elements.resetError.textContent = getErrorMessage(error, 'Unable to reset the password.');
            elements.resetError.classList.remove('d-none');
        } finally {
            setButtonBusy(elements.resetButton, false);
        }
    }

    elements.search.addEventListener('input', renderUsers);
    elements.role.addEventListener('change', updateClinicVisibility);
    elements.form.addEventListener('submit', submitUser);
    elements.resetForm.addEventListener('submit', resetPassword);
    elements.modal.addEventListener('hidden.bs.modal', resetUserForm);
    elements.modal.addEventListener('show.bs.modal', event => {
        if (event.relatedTarget?.id === 'add-user-button' || !state.editingUserId) {
            resetUserForm();
        }
    });
    elements.tableBody.addEventListener('click', event => {
        const button = event.target.closest('[data-action]');
        if (!button) {
            return;
        }
        const user = state.users.find(item => String(item.user_id) === button.dataset.userId);
        if (!user) {
            return;
        }
        if (button.dataset.action === 'details') showDetails(user.user_id);
        if (button.dataset.action === 'edit') openEditForm(user);
        if (button.dataset.action === 'status') changeStatus(user);
        if (button.dataset.action === 'reset') openResetForm(user);
    });

    document.querySelectorAll('[data-bs-target="#user-modal"]').forEach(button => {
        button.addEventListener('click', resetUserForm);
    });

    [elements.saveButton, elements.resetButton].forEach(button => {
        button.dataset.defaultContent = button.innerHTML;
    });

    Promise.all([loadLookups(), loadUsers()]).catch(error => {
        showFeedback(getErrorMessage(error, 'Unable to load user management data.'), 'danger');
    });
});
