<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Admin');

renderAuthenticatedLayoutStart('Notifications', 'notifications');
?>
        <main class="dashboard-main" aria-labelledby="notifications-title">
            <div class="dashboard-content">
                <div class="dashboard-heading users-heading">
                    <div>
                        <p class="page-kicker mb-2">ADMIN OPERATIONS</p>
                        <h1 class="page-title mb-2" id="notifications-title">Notifications</h1>
                        <p class="text-muted-custom mb-0">Review system alerts, telemetry warnings, and operational updates.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <button class="btn btn-outline-secondary" type="button" id="refresh-notifications-button">
                            <i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>
                            Refresh
                        </button>
                    </div>
                </div>

                <div class="alert d-none" id="notifications-feedback" role="alert" aria-live="polite"></div>

                <section class="surface-card dashboard-group mb-4" aria-labelledby="notifications-summary-title">
                    <div class="dashboard-group-header">
                        <div class="dashboard-group-icon icon-info"><i class="bi bi-bell" aria-hidden="true"></i></div>
                        <div>
                            <h2 id="notifications-summary-title">Notification summary</h2>
                            <p>Overview of your unread and read notifications</p>
                        </div>
                    </div>
                    <div class="dashboard-metrics">
                        <div class="dashboard-metric metric-primary"><span>Total Notifications</span><strong id="stat-total">0</strong></div>
                        <div class="dashboard-metric metric-warning"><span>Unread</span><strong id="stat-unread">0</strong></div>
                        <div class="dashboard-metric metric-success"><span>Read</span><strong id="stat-read">0</strong></div>
                    </div>
                </section>

                <section class="surface-card users-card" aria-labelledby="notifications-table-title">
                    <div class="users-card-toolbar">
                        <div>
                            <h2 class="section-title mb-1" id="notifications-table-title">All notifications</h2>
                            <p class="text-muted-custom small mb-0" id="notifications-count">Loading notifications...</p>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 w-100 justify-content-end">
                            <div class="users-search">
                                <label class="visually-hidden" for="notification-search">Search notifications</label>
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input class="form-control" type="search" id="notification-search" placeholder="Search notifications" autocomplete="off">
                            </div>
                            <div>
                                <label class="visually-hidden" for="notification-filter">Filter by status</label>
                                <select class="form-select" id="notification-filter" aria-label="Filter by status">
                                    <option value="">All notifications</option>
                                    <option value="unread">Unread</option>
                                    <option value="read">Read</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table users-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Type</th>
                                    <th scope="col">Message</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="notifications-table-body">
                                <tr>
                                    <td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading notifications...</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="empty-state d-none" id="notifications-empty">
                        <i class="bi bi-bell empty-state-icon" aria-hidden="true"></i>
                        <h3 class="section-title">No notifications</h3>
                        <p class="text-muted-custom mb-0">You have no notifications to review.</p>
                    </div>
                </section>
            </div>
        </main>

        <script src="../frontend/assets/js/api.js"></script>
        <script src="../frontend/assets/js/notifications.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>
