<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Admin');

renderAuthenticatedLayoutStart('Vehicle Management', 'vehicles');
?>
        <main class="dashboard-main" aria-labelledby="vehicles-title">
            <div class="dashboard-content">
                <div class="dashboard-heading users-heading">
                    <div>
                        <p class="page-kicker mb-2">FLEET OPERATIONS</p>
                        <h1 class="page-title mb-2" id="vehicles-title">Vehicle Management</h1>
                        <p class="text-muted-custom mb-0">Manage fleet vehicles and operational availability.</p>
                    </div>
                    <button class="btn btn-primary" type="button" id="add-vehicle-button" data-bs-toggle="modal" data-bs-target="#vehicle-modal">
                        <i class="bi bi-truck-front me-2" aria-hidden="true"></i>
                        Add Vehicle
                    </button>
                </div>

                <div class="alert d-none" id="vehicles-feedback" role="alert" aria-live="polite"></div>

                <section class="surface-card users-card" aria-labelledby="vehicles-table-title">
                    <div class="users-card-toolbar">
                        <div>
                            <h2 class="section-title mb-1" id="vehicles-table-title">Fleet vehicles</h2>
                            <p class="text-muted-custom small mb-0" id="vehicles-count">Loading vehicles...</p>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 w-100 justify-content-end">
                            <div class="users-search">
                                <label class="visually-hidden" for="vehicle-search">Search vehicles</label>
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input class="form-control" type="search" id="vehicle-search" placeholder="Search vehicles" autocomplete="off">
                            </div>
                            <div>
                                <label class="visually-hidden" for="vehicle-status-filter">Filter by status</label>
                                <select class="form-select" id="vehicle-status-filter" aria-label="Filter by status">
                                    <option value="">All statuses</option>
                                    <option value="Available">Available</option>
                                    <option value="In Transit">In Transit</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table users-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Vehicle</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Capacity</th>
                                    <th scope="col">Refrigerated</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Created</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="vehicles-table-body">
                                <tr>
                                    <td colspan="7"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading vehicles...</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="empty-state d-none" id="vehicles-empty">
                        <i class="bi bi-truck-front empty-state-icon" aria-hidden="true"></i>
                        <h3 class="section-title">No vehicles found</h3>
                        <p class="text-muted-custom mb-0">Try a different search or status filter.</p>
                    </div>
                </section>
            </div>
        </main>

        <div class="modal fade" id="vehicle-modal" tabindex="-1" aria-labelledby="vehicle-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">NEW FLEET ASSET</p>
                            <h2 class="modal-title section-title" id="vehicle-modal-title">Add Vehicle</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="vehicle-form" novalidate>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none" id="vehicle-form-error" role="alert"></div>
                            <div class="mb-3">
                                <label class="form-label" for="vehicle-number">Vehicle Number</label>
                                <input class="form-control" type="text" id="vehicle-number" name="vehicle_number" maxlength="50" required>
                                <div class="invalid-feedback">Enter a vehicle number.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="vehicle-type">Vehicle Type</label>
                                <input class="form-control" type="text" id="vehicle-type" name="vehicle_type" maxlength="100" required>
                                <div class="invalid-feedback">Enter the vehicle type.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="vehicle-capacity">Capacity</label>
                                <input class="form-control" type="number" id="vehicle-capacity" name="capacity" min="0" step="0.01" required>
                                <div class="invalid-feedback">Enter a valid capacity.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="vehicle-refrigerated">Refrigerated</label>
                                <select class="form-select" id="vehicle-refrigerated" name="refrigerated" required>
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="vehicle-status">Status</label>
                                <select class="form-select" id="vehicle-status" name="status" required>
                                    <option value="Available">Available</option>
                                    <option value="In Transit">In Transit</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="submit" id="save-vehicle-button">
                                <i class="bi bi-check2 me-1" aria-hidden="true"></i>
                                Save Vehicle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="vehicle-details-modal" tabindex="-1" aria-labelledby="vehicle-details-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">FLEET PROFILE</p>
                            <h2 class="modal-title section-title" id="vehicle-details-title">Vehicle details</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="vehicle-details-content">
                        <p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading vehicle details...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="vehicle-status-modal" tabindex="-1" aria-labelledby="vehicle-status-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">FLEET AVAILABILITY</p>
                            <h2 class="modal-title section-title" id="vehicle-status-title">Change status</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="vehicle-status-form">
                        <div class="modal-body">
                            <p class="text-muted-custom small" id="vehicle-status-label"></p>
                            <div class="alert alert-danger d-none" id="vehicle-status-error" role="alert"></div>
                            <input type="hidden" id="vehicle-status-id">
                            <label class="form-label" for="vehicle-status-select">New status</label>
                            <select class="form-select" id="vehicle-status-select" required>
                                <option value="Available">Available</option>
                                <option value="In Transit">In Transit</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="submit" id="update-vehicle-status-button">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="../frontend/assets/js/api.js"></script>
        <script src="../frontend/assets/js/admin-vehicles.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>
