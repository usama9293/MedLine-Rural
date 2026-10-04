<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Dispatcher');

renderAuthenticatedLayoutStart('Supply Requests', 'requests');
?>
        <main class="dashboard-main" aria-labelledby="requests-title">
            <div class="dashboard-content">
                <div class="dashboard-heading users-heading">
                    <div>
                        <p class="page-kicker mb-2">OPERATIONS</p>
                        <h1 class="page-title mb-2" id="requests-title">Supply Requests</h1>
                        <p class="text-muted-custom mb-0">Review dispatch-eligible requests, triage urgency, and update status.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <button class="btn btn-outline-secondary" type="button" id="refresh-requests-button">
                            <i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>
                            Refresh
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="open-queue-button">
                            <i class="bi bi-list-ol me-2" aria-hidden="true"></i>
                            Triage Queue
                        </button>
                        <button class="btn btn-primary" type="button" id="process-triage-button">
                            <i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>
                            Process Pending Triage
                        </button>
                    </div>
                </div>

                <div class="alert d-none" id="requests-feedback" role="alert" aria-live="polite"></div>

                <section class="surface-card dashboard-group mb-4" aria-labelledby="requests-summary-title">
                    <div class="dashboard-group-header">
                        <div class="dashboard-group-icon icon-info"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></div>
                        <div>
                            <h2 id="requests-summary-title">Request summary</h2>
                            <p>Counts from the current request list</p>
                        </div>
                    </div>
                    <div class="dashboard-metrics">
                        <div class="dashboard-metric metric-primary"><span>Total Requests</span><strong id="stat-total">0</strong></div>
                        <div class="dashboard-metric metric-warning"><span>Pending</span><strong id="stat-pending">0</strong></div>
                        <div class="dashboard-metric metric-info"><span>Approved</span><strong id="stat-approved">0</strong></div>
                        <div class="dashboard-metric metric-success"><span>Completed</span><strong id="stat-completed">0</strong></div>
                        <div class="dashboard-metric metric-danger"><span>Cancelled</span><strong id="stat-cancelled">0</strong></div>
                    </div>
                </section>

                <section class="surface-card users-card" aria-labelledby="requests-table-title">
                    <div class="users-card-toolbar">
                        <div>
                            <h2 class="section-title mb-1" id="requests-table-title">All supply requests</h2>
                            <p class="text-muted-custom small mb-0" id="requests-count">Loading requests...</p>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 w-100 justify-content-end">
                            <div class="users-search">
                                <label class="visually-hidden" for="request-search">Search requests</label>
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input class="form-control" type="search" id="request-search" placeholder="Search requests" autocomplete="off">
                            </div>
                            <div>
                                <label class="visually-hidden" for="request-status-filter">Filter by status</label>
                                <select class="form-select" id="request-status-filter" aria-label="Filter by status">
                                    <option value="">All statuses</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table users-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Request ID</th>
                                    <th scope="col">Clinic</th>
                                    <th scope="col">Item</th>
                                    <th scope="col">Qty</th>
                                    <th scope="col">Required By</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Urgency</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="requests-table-body">
                                <tr>
                                    <td colspan="9"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading requests...</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="empty-state d-none" id="requests-empty">
                        <i class="bi bi-clipboard2 empty-state-icon" aria-hidden="true"></i>
                        <h3 class="section-title">No supply requests found</h3>
                        <p class="text-muted-custom mb-0">Try a different search or status filter.</p>
                    </div>
                </section>
            </div>
        </main>

        <div class="modal fade" id="request-details-modal" tabindex="-1" aria-labelledby="request-details-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">REQUEST PROFILE</p>
                            <h2 class="modal-title section-title" id="request-details-title">Request details</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="request-details-content">
                        <p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading request details...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="triage-queue-modal" tabindex="-1" aria-labelledby="triage-queue-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">TRIAGE</p>
                            <h2 class="modal-title section-title" id="triage-queue-title">Triage queue</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="triage-queue-content">
                        <p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading triage queue...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="calculate-result-modal" tabindex="-1" aria-labelledby="calculate-result-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">URGENCY SCORE</p>
                            <h2 class="modal-title section-title" id="calculate-result-title">Calculation result</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="calculate-result-content"></div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="request-confirm-modal" tabindex="-1" aria-labelledby="request-confirm-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">CONFIRM ACTION</p>
                            <h2 class="modal-title section-title" id="request-confirm-title">Confirm</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="request-confirm-form">
                        <div class="modal-body">
                            <p class="text-muted-custom small mb-0" id="request-confirm-label"></p>
                            <div class="alert alert-danger d-none mt-3" id="request-confirm-error" role="alert"></div>
                            <input type="hidden" id="request-confirm-id">
                            <input type="hidden" id="request-confirm-action">
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="submit" id="request-confirm-button">Confirm</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="../frontend/assets/js/api.js"></script>
        <script src="../frontend/assets/js/dispatcher-requests.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>
