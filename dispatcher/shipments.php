<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Dispatcher');

renderAuthenticatedLayoutStart('Shipment Management', 'shipments');
?>
        <main class="dashboard-main" aria-labelledby="shipments-title">
            <div class="dashboard-content">
                <div class="dashboard-heading users-heading">
                    <div>
                        <p class="page-kicker mb-2">DELIVERY OPERATIONS</p>
                        <h1 class="page-title mb-2" id="shipments-title">Shipment Management</h1>
                        <p class="text-muted-custom mb-0">Assign, dispatch, and track approved supply requests.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <button class="btn btn-outline-secondary" type="button" id="refresh-shipments-button">
                            <i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>
                            Refresh
                        </button>
                        <button class="btn btn-primary" type="button" id="open-create-shipment-button">
                            <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>
                            Create Shipment
                        </button>
                    </div>
                </div>

                <div class="alert d-none" id="shipments-feedback" role="alert" aria-live="polite"></div>

                <section class="surface-card dashboard-group mb-4" aria-labelledby="shipments-summary-title">
                    <div class="dashboard-group-header">
                        <div class="dashboard-group-icon icon-info"><i class="bi bi-truck" aria-hidden="true"></i></div>
                        <div>
                            <h2 id="shipments-summary-title">Shipment summary</h2>
                            <p>Counts from the current shipment list</p>
                        </div>
                    </div>
                    <div class="dashboard-metrics">
                        <div class="dashboard-metric metric-primary"><span>Total Shipments</span><strong id="stat-total">0</strong></div>
                        <div class="dashboard-metric metric-warning"><span>Pending</span><strong id="stat-pending">0</strong></div>
                        <div class="dashboard-metric metric-info"><span>Dispatched</span><strong id="stat-dispatched">0</strong></div>
                        <div class="dashboard-metric metric-info"><span>In Transit</span><strong id="stat-in-transit">0</strong></div>
                        <div class="dashboard-metric metric-success"><span>Delivered</span><strong id="stat-delivered">0</strong></div>
                    </div>
                </section>

                <section class="surface-card users-card" aria-labelledby="shipments-table-title">
                    <div class="users-card-toolbar">
                        <div>
                            <h2 class="section-title mb-1" id="shipments-table-title">All shipments</h2>
                            <p class="text-muted-custom small mb-0" id="shipments-count">Loading shipments...</p>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 w-100 justify-content-end">
                            <div class="users-search">
                                <label class="visually-hidden" for="shipment-search">Search shipments</label>
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input class="form-control" type="search" id="shipment-search" placeholder="Search shipments" autocomplete="off">
                            </div>
                            <div>
                                <label class="visually-hidden" for="shipment-status-filter">Filter by status</label>
                                <select class="form-select" id="shipment-status-filter" aria-label="Filter by status">
                                    <option value="">All statuses</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Dispatched">Dispatched</option>
                                    <option value="In Transit">In Transit</option>
                                    <option value="Delivered">Delivered</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table users-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Shipment ID</th>
                                    <th scope="col">Request ID</th>
                                    <th scope="col">Vehicle</th>
                                    <th scope="col">Driver</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Estimated Delivery</th>
                                    <th scope="col">Dispatched At</th>
                                    <th scope="col">Delivered At</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="shipments-table-body">
                                <tr><td colspan="9"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading shipments...</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="empty-state d-none" id="shipments-empty">
                        <i class="bi bi-truck empty-state-icon" aria-hidden="true"></i>
                        <h3 class="section-title">No shipments found</h3>
                        <p class="text-muted-custom mb-0">Try a different search or status filter.</p>
                    </div>
                </section>
            </div>
        </main>

        <div class="modal fade" id="shipment-details-modal" tabindex="-1" aria-labelledby="shipment-details-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
                <div class="modal-header"><div><p class="page-kicker mb-1">SHIPMENT PROFILE</p><h2 class="modal-title section-title" id="shipment-details-title">Shipment details</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body" id="shipment-details-content"><p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading shipment details...</p></div>
            </div></div>
        </div>

        <div class="modal fade" id="shipment-tracking-modal" tabindex="-1" aria-labelledby="shipment-tracking-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content">
                <div class="modal-header"><div><p class="page-kicker mb-1">API-BASED TRACKING</p><h2 class="modal-title section-title" id="shipment-tracking-title">Shipment tracking</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body" id="shipment-tracking-content"><p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading shipment tracking...</p></div>
            </div></div>
        </div>

        <div class="modal fade" id="create-shipment-modal" tabindex="-1" aria-labelledby="create-shipment-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><div><p class="page-kicker mb-1">DELIVERY ASSIGNMENT</p><h2 class="modal-title section-title" id="create-shipment-title">Create shipment</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <form id="create-shipment-form" novalidate>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="create-shipment-error" role="alert"></div>
                        <div class="alert alert-info small" role="status">Shipment creation validates the request, vehicle, and driver on the server. Assignment validation is available after the shipment exists.</div>
                        <div class="mb-3"><label class="form-label" for="shipment-request">Approved Request <span class="text-danger" aria-hidden="true">*</span></label><select class="form-select" id="shipment-request" required><option value="">Loading approved requests...</option></select><div class="invalid-feedback">Select an approved and triaged request.</div><div class="form-text" id="request-options-status"></div></div>
                        <div class="mb-3"><label class="form-label" for="shipment-vehicle">Available Vehicle <span class="text-danger" aria-hidden="true">*</span></label><select class="form-select" id="shipment-vehicle" required><option value="">Loading vehicles...</option></select><div class="invalid-feedback">Select an available vehicle.</div><div class="form-text" id="vehicle-options-status"></div></div>
                        <div class="mb-3"><label class="form-label" for="shipment-driver">Available Driver <span class="text-danger" aria-hidden="true">*</span></label><select class="form-select" id="shipment-driver" required><option value="">Loading drivers...</option></select><div class="invalid-feedback">Select an available driver.</div><div class="form-text" id="driver-options-status"></div></div>
                        <div><label class="form-label" for="shipment-estimated-delivery">Estimated Delivery</label><input class="form-control" type="datetime-local" id="shipment-estimated-delivery"><div class="invalid-feedback">Estimated delivery must be in the future.</div></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" id="submit-shipment-button" disabled><i class="bi bi-truck-front me-2" aria-hidden="true"></i>Create Shipment</button></div>
                </form>
            </div></div>
        </div>

        <div class="modal fade" id="shipment-confirm-modal" tabindex="-1" aria-labelledby="shipment-confirm-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
                <div class="modal-header"><div><p class="page-kicker mb-1">CONFIRM ACTION</p><h2 class="modal-title section-title" id="shipment-confirm-title">Confirm action</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <form id="shipment-confirm-form"><div class="modal-body"><p class="text-muted-custom small mb-0" id="shipment-confirm-label"></p><div class="alert alert-danger d-none mt-3" id="shipment-confirm-error" role="alert"></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" id="shipment-confirm-button">Confirm</button></div></form>
            </div></div>
        </div>

        <script src="../frontend/assets/js/api.js"></script>
        <script src="../frontend/assets/js/dispatcher-shipments.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>
