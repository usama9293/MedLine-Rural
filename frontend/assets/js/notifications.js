document.addEventListener('DOMContentLoaded', () => {
    const state = {
        notifications: [],
        searchQuery: '',
        statusFilter: ''
    };

    const elements = {
        tableBody: document.querySelector('#notifications-table-body'),
        emptyState: document.querySelector('#notifications-empty'),
        count: document.querySelector('#notifications-count'),
        search: document.querySelector('#notification-search'),
        statusFilter: document.querySelector('#notification-filter'),
        refreshButton: document.querySelector('#refresh-notifications-button'),
        feedback: document.querySelector('#notifications-feedback'),
        statTotal: document.querySelector('#stat-total'),
        statUnread: document.querySelector('#stat-unread'),
        statRead: document.querySelector('#stat-read')
    };

    function displayValue(value) {
        if (value === null || value === undefined || String(value).trim() === '') {
            return 'Not provided';
        }
        return String(value);
    }

    function getErrorMessage(error, fallback) {
        const messages = {
            400: 'Invalid notification request.',
            401: 'Your session has expired. Please sign in again.',
            403: 'You do not have permission to access notifications.',
            404: 'The notification was not found.',
            409: 'The notification request conflicts with the current system state.',
            422: 'The notification request could not be processed.',
            500: 'The server could not complete the notification request.'
        };

        return messages[error?.status] || error?.message || fallback;
    }

    function handleAuthError(error) {
        if (error?.status === 401) {
            window.location.href = '../frontend/login.php';
        }
    }

    function showFeedback(message, type = 'success') {
        if (!elements.feedback) return;
        elements.feedback.className = `alert alert-${type}`;
        elements.feedback.textContent = message;
        elements.feedback.classList.remove('d-none');
    }

    function hideFeedback() {
        if (!elements.feedback) return;
        elements.feedback.className = 'alert d-none';
        elements.feedback.textContent = '';
    }

    function typeBadgeClass(type) {
        const normalized = String(type || '').toLowerCase();
        if (normalized.includes('telemetry') || normalized.includes('alert') || normalized.includes('danger')) {
            return 'badge-soft-danger';
        }
        if (normalized.includes('warning')) {
            return 'badge-soft-warning';
        }
        if (normalized.includes('success') || normalized.includes('delivered')) {
            return 'badge-soft-success';
        }
        return 'badge-soft-info';
    }

    function updateSummary() {
        const total = state.notifications.length;
        const unread = state.notifications.filter(n => !Number(n.is_read)).length;
        const read = state.notifications.filter(n => Number(n.is_read) === 1).length;

        if (elements.statTotal) elements.statTotal.textContent = total;
        if (elements.statUnread) elements.statUnread.textContent = unread;
        if (elements.statRead) elements.statRead.textContent = read;
    }

    function getFilteredNotifications() {
        const query = state.searchQuery.trim().toLowerCase();
        const filter = state.statusFilter;

        return state.notifications.filter(n => {
            const isRead = Number(n.is_read) === 1;
            const matchesStatus = !filter ||
                (filter === 'unread' && !isRead) ||
                (filter === 'read' && isRead);

            const matchesQuery = !query || [
                n.notification_type,
                n.message,
                n.created_at
            ].some(val => String(val ?? '').toLowerCase().includes(query));

            return matchesStatus && matchesQuery;
        });
    }

    function createNotificationRow(notification) {
        const row = document.createElement('tr');
        const isRead = Number(notification.is_read) === 1;

        if (!isRead) {
            row.className = 'table-active';
        }

        // Type
        const typeCell = document.createElement('td');
        const typeBadge = document.createElement('span');
        typeBadge.className = `badge ${typeBadgeClass(notification.notification_type)}`;
        typeBadge.textContent = displayValue(notification.notification_type);
        typeCell.appendChild(typeBadge);

        // Message
        const messageCell = document.createElement('td');
        messageCell.className = isRead ? 'text-muted-custom' : 'fw-semibold';
        messageCell.textContent = displayValue(notification.message);

        // Created At
        const createdCell = document.createElement('td');
        createdCell.className = 'text-nowrap small text-muted-custom';
        createdCell.textContent = displayValue(notification.created_at);

        // Status
        const statusCell = document.createElement('td');
        const statusBadge = document.createElement('span');
        statusBadge.className = `badge ${isRead ? 'badge-soft-success' : 'badge-soft-warning'}`;
        statusBadge.textContent = isRead ? 'Read' : 'Unread';
        statusCell.appendChild(statusBadge);

        // Actions
        const actionsCell = document.createElement('td');
        actionsCell.className = 'text-end text-nowrap';

        if (!isRead) {
            const markButton = document.createElement('button');
            markButton.type = 'button';
            markButton.className = 'btn btn-sm btn-outline-secondary';
            markButton.dataset.notificationId = notification.notification_id;
            markButton.innerHTML = '<i class="bi bi-check2 me-1" aria-hidden="true"></i>Mark read';
            actionsCell.appendChild(markButton);
        } else {
            const readIndicator = document.createElement('span');
            readIndicator.className = 'text-muted-custom small';
            readIndicator.innerHTML = '<i class="bi bi-check-all me-1" aria-hidden="true"></i>Read';
            actionsCell.appendChild(readIndicator);
        }

        row.append(typeCell, messageCell, createdCell, statusCell, actionsCell);
        return row;
    }

    function renderNotifications() {
        const filtered = getFilteredNotifications();

        elements.tableBody.replaceChildren(...filtered.map(createNotificationRow));
        elements.tableBody.classList.toggle('d-none', filtered.length === 0);
        elements.emptyState.classList.toggle('d-none', filtered.length > 0);

        if (state.notifications.length === 0) {
            elements.count.textContent = '0 notifications';
        } else if (filtered.length === state.notifications.length) {
            elements.count.textContent = `${state.notifications.length} notification${state.notifications.length === 1 ? '' : 's'}`;
        } else {
            elements.count.textContent = `Showing ${filtered.length} of ${state.notifications.length} notification${state.notifications.length === 1 ? '' : 's'}`;
        }
    }

    async function markAsRead(notificationId, button) {
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Saving...';

        try {
            const response = await MedLineAPI.requestJson('notifications/mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: Number(notificationId) })
            });

            showFeedback(response.message || 'Notification marked as read.', 'success');
            await loadNotifications(false);
        } catch (error) {
            showFeedback(getErrorMessage(error, 'Unable to mark notification as read.'), 'danger');
            button.disabled = false;
            button.innerHTML = originalHtml;
            handleAuthError(error);
        }
    }

    async function loadNotifications(showTableLoading = true) {
        if (showTableLoading) {
            elements.tableBody.innerHTML = '<tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading notifications...</span></td></tr>';
            elements.tableBody.classList.remove('d-none');
            elements.emptyState.classList.add('d-none');
        }

        try {
            const response = await MedLineAPI.requestJson('notifications/get.php');
            state.notifications = Array.isArray(response.data) ? response.data : [];

            updateSummary();
            renderNotifications();
        } catch (error) {
            elements.count.textContent = 'Unable to load notifications';
            elements.tableBody.innerHTML = `<tr><td colspan="5"><div class="error-state"><i class="bi bi-exclamation-circle error-state-icon" aria-hidden="true"></i><p class="mb-3">${getErrorMessage(error, 'Unable to load notifications. Please try again.')}</p><button class="btn btn-primary btn-sm" type="button" id="retry-notifications"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Retry</button></div></td></tr>`;
            const retryButton = elements.tableBody.querySelector('#retry-notifications');
            if (retryButton) {
                retryButton.addEventListener('click', () => {
                    hideFeedback();
                    loadNotifications();
                });
            }
            handleAuthError(error);
        }
    }

    // Event Listeners
    if (elements.refreshButton) {
        elements.refreshButton.addEventListener('click', () => {
            hideFeedback();
            loadNotifications();
        });
    }

    if (elements.search) {
        elements.search.addEventListener('input', () => {
            state.searchQuery = elements.search.value;
            renderNotifications();
        });
    }

    if (elements.statusFilter) {
        elements.statusFilter.addEventListener('change', () => {
            state.statusFilter = elements.statusFilter.value;
            renderNotifications();
        });
    }

    if (elements.tableBody) {
        elements.tableBody.addEventListener('click', event => {
            const button = event.target.closest('[data-notification-id]');
            if (button && !button.disabled) {
                markAsRead(button.dataset.notificationId, button);
            }
        });
    }

    loadNotifications();
});
