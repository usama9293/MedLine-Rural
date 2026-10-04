<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Admin');

renderAuthenticatedLayoutStart('Driver Management', 'drivers');
?>
        <main class="dashboard-main" aria-labelledby="drivers-title">
            <div class="dashboard-content">
                <div class="dashboard-heading users-heading">
                    <div>
                        <p class="page-kicker mb-2">FLEET OPERATIONS</p>
                        <h1 class="page-title mb-2" id="drivers-title">Driver Management</h1>
                        <p class="text-muted-custom mb-0">Manage driver records and operational availability.</p>
                    </div>
                    <button class="btn btn-primary" type="button" id="add-driver-button" data-bs-toggle="modal" data-bs-target="#driver-modal">
                        <i class="bi bi-person-plus me-2" aria-hidden="true"></i>
                        Add Driver
                    </button>
                </div>

                <div class="alert d-none" id="drivers-feedback" role="alert" aria-live="polite"></div>

                <section class="surface-card users-card" aria-labelledby="drivers-table-title">
                    <div class="users-card-toolbar">
                        <div>
                            <h2 class="section-title mb-1" id="drivers-table-title">Registered drivers</h2>
                            <p class="text-muted-custom small mb-0" id="drivers-count">Loading drivers...</p>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 w-100 justify-content-end">
                            <div class="users-search">
                                <label class="visually-hidden" for="driver-search">Search drivers</label>
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input class="form-control" type="search" id="driver-search" placeholder="Search drivers" autocomplete="off">
                            </div>
                            <div>
                                <label class="visually-hidden" for="driver-status-filter">Filter by status</label>
                                <select class="form-select" id="driver-status-filter" aria-label="Filter by status">
                                    <option value="">All statuses</option>
                                    <option value="Available">Available</option>
                                    <option value="On Trip">On Trip</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table users-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Driver</th>
                                    <th scope="col">License Number</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Created</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="drivers-table-body">
                                <tr>
                                    <td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading drivers...</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="empty-state d-none" id="drivers-empty">
                        <i class="bi bi-person-badge empty-state-icon" aria-hidden="true"></i>
                        <h3 class="section-title">No drivers found</h3>
                        <p class="text-muted-custom mb-0">Try a different search or status filter.</p>
                    </div>
                </section>
            </div>
        </main>

        <div class="modal fade" id="driver-modal" tabindex="-1" aria-labelledby="driver-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">NEW DRIVER PROFILE</p>
                            <h2 class="modal-title section-title" id="driver-modal-title">Add Driver</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="driver-form" novalidate>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none" id="driver-form-error" role="alert"></div>
                            <div class="mb-3">
                                <label class="form-label" for="driver-user">Driver User</label>
                                <select class="form-select" id="driver-user" name="user_id" required>
                                    <option value="">Loading driver users...</option>
                                </select>
                                <div class="invalid-feedback">Select an existing Driver user.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="driver-license-number">License Number</label>
                                <input class="form-control" type="text" id="driver-license-number" name="license_number" maxlength="100" required>
                                <div class="invalid-feedback">Enter a license number.</div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="driver-status">Status</label>
                                <select class="form-select" id="driver-status" name="status" required>
                                    <option value="Available" selected>Available</option>
                                    <option value="On Trip">On Trip</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="submit" id="save-driver-button">
                                <i class="bi bi-check2 me-1" aria-hidden="true"></i>
                                Save Driver
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="driver-details-modal" tabindex="-1" aria-labelledby="driver-details-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">DRIVER PROFILE</p>
                            <h2 class="modal-title section-title" id="driver-details-title">Driver details</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="driver-details-content">
                        <p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading driver details...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="driver-status-modal" tabindex="-1" aria-labelledby="driver-status-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">OPERATIONAL STATUS</p>
                            <h2 class="modal-title section-title" id="driver-status-title">Change status</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="driver-status-form">
                        <div class="modal-body">
                            <p class="text-muted-custom small mb-2" id="driver-status-label"></p>
                            <p class="text-muted-custom small" id="driver-status-current"></p>
                            <div class="alert alert-danger d-none" id="driver-status-error" role="alert"></div>
                            <input type="hidden" id="driver-status-id">
                            <label class="form-label" for="driver-status-select">New operational status</label>
                            <select class="form-select" id="driver-status-select" required>
                                <option value="Available">Available</option>
                                <option value="On Trip">On Trip</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="submit" id="update-driver-status-button">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="../frontend/assets/js/api.js"></script>
        <script src="../frontend/assets/js/admin-drivers.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>
