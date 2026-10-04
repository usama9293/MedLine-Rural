<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Clinic Staff');

renderAuthenticatedLayoutStart('Clinic Dashboard');
?>
        <main class="dashboard-main" aria-labelledby="dashboard-title">
            <div class="dashboard-content">
                <div class="dashboard-heading">
                    <div>
                        <p class="page-kicker mb-2">CLINIC OPERATIONS</p>
                        <h1 class="page-title mb-2" id="dashboard-title">Clinic dashboard</h1>
                        <p class="text-muted-custom mb-0">Manage supply requests and review inventory stock levels for your clinic.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <button class="btn btn-outline-secondary" type="button" id="refresh-dashboard-button">
                            <i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>
                            Refresh
                        </button>
                    </div>
                </div>

                <div class="alert alert-danger dashboard-alert d-none" id="dashboard-error" role="alert">
                    <div class="d-flex align-items-start gap-3">
                        <i class="bi bi-exclamation-triangle-fill fs-5" aria-hidden="true"></i>
                        <div class="flex-grow-1">
                            <strong class="d-block mb-1">Dashboard unavailable</strong>
                            <span id="dashboard-error-message">Unable to load dashboard data. Please try again.</span>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" type="button" id="retry-dashboard">
                            <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
                            Retry
                        </button>
                    </div>
                </div>

                <!-- Quick Access Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-12 col-md-6">
                        <div class="surface-card dashboard-group h-100">
                            <div class="dashboard-group-header">
                                <div class="dashboard-group-icon icon-info"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></div>
                                <div>
                                    <h2>Supply Requests</h2>
                                    <p>Create and track medical supply requests for your clinic.</p>
                                </div>
                            </div>
                            <div class="pt-2 mt-auto">
                                <a class="btn btn-primary btn-sm" href="../clinic/requests.php">
                                    <i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>
                                    Open Supply Requests
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="surface-card dashboard-group h-100">
                            <div class="dashboard-group-header">
                                <div class="dashboard-group-icon icon-success"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
                                <div>
                                    <h2>Clinic Inventory</h2>
                                    <p>Review current stock levels, usages, and stock-out risks.</p>
                                </div>
                            </div>
                            <div class="pt-2 mt-auto">
                                <a class="btn btn-outline-secondary btn-sm" href="../admin/inventory.php">
                                    <i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>
                                    Open Inventory
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Request Summary Cards -->
                <section class="surface-card dashboard-group mb-4" aria-labelledby="requests-summary-title">
                    <div class="dashboard-group-header">
                        <div class="dashboard-group-icon icon-info"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></div>
                        <div>
                            <h2 id="requests-summary-title">Request summary</h2>
                            <p>Overview of your clinic's supply request statuses</p>
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

                <!-- Clinic Distribution Charts -->
                <div class="charts-grid mb-4">
                    <div class="surface-card p-3">
                        <h3 class="fs-6 fw-bold mb-1 text-muted-custom">Supply Request Status</h3>
                        <div class="chart-container" id="container-clinic-requests">
                            <canvas id="chart-clinic-requests"></canvas>
                            <div class="chart-empty-state d-none" id="empty-clinic-requests">
                                <i class="bi bi-clipboard2" aria-hidden="true"></i>
                                <p>No supply request data</p>
                            </div>
                        </div>
                    </div>
                    <div class="surface-card p-3">
                        <h3 class="fs-6 fw-bold mb-1 text-muted-custom">Inventory Stock-Out Risk</h3>
                        <div class="chart-container" id="container-clinic-stock-risk">
                            <canvas id="chart-clinic-stock-risk"></canvas>
                            <div class="chart-empty-state d-none" id="empty-clinic-stock-risk">
                                <i class="bi bi-box-seam" aria-hidden="true"></i>
                                <p>No inventory records found</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Recent Requests -->
                    <div class="col-12 col-xl-6">
                        <section class="surface-card users-card h-100" aria-labelledby="recent-requests-title">
                            <div class="users-card-toolbar">
                                <div>
                                    <h2 class="section-title mb-1" id="recent-requests-title">Recent supply requests</h2>
                                    <p class="text-muted-custom small mb-0">Latest requests for your clinic</p>
                                </div>
                                <div>
                                    <a class="btn btn-sm btn-link text-decoration-none p-0" href="../clinic/requests.php">View all &rarr;</a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table users-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Request ID</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Urgency</th>
                                            <th scope="col">Requested At</th>
                                            <th scope="col" class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recent-requests-table-body">
                                        <tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading recent requests...</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="empty-state d-none" id="recent-requests-empty">
                                <i class="bi bi-clipboard2 empty-state-icon" aria-hidden="true"></i>
                                <h3 class="section-title">No supply requests found</h3>
                                <p class="text-muted-custom mb-0">Create a new supply request to get started.</p>
                            </div>
                        </section>
                    </div>

                    <!-- Inventory Stock-Out Risk Preview -->
                    <div class="col-12 col-xl-6">
                        <section class="surface-card users-card h-100" aria-labelledby="stock-risk-title">
                            <div class="users-card-toolbar">
                                <div>
                                    <h2 class="section-title mb-1" id="stock-risk-title">Stock-out risk preview</h2>
                                    <p class="text-muted-custom small mb-0">Items requiring stock attention</p>
                                </div>
                                <div>
                                    <a class="btn btn-sm btn-link text-decoration-none p-0" href="../admin/inventory.php">Full inventory &rarr;</a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table users-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Item</th>
                                            <th scope="col">Category</th>
                                            <th scope="col">Available</th>
                                            <th scope="col">Days Left</th>
                                            <th scope="col">Risk Level</th>
                                        </tr>
                                    </thead>
                                    <tbody id="stock-risk-table-body">
                                        <tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading stock risk preview...</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="empty-state d-none" id="stock-risk-empty">
                                <i class="bi bi-box-seam empty-state-icon" aria-hidden="true"></i>
                                <h3 class="section-title">No inventory records found</h3>
                                <p class="text-muted-custom mb-0">No stock risk records available for your clinic.</p>
                            </div>
                        </section>
                    </div>
                </div>
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

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script src="../frontend/assets/js/api.js"></script>
        <script src="../frontend/assets/js/clinic-dashboard.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>