<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Admin');

renderAuthenticatedLayoutStart('Clinic Management', 'clinics');
?>
        <main class="dashboard-main" aria-labelledby="clinics-title">
            <div class="dashboard-content">
                <div class="dashboard-heading users-heading">
                    <div>
                        <p class="page-kicker mb-2">ADMINISTRATION</p>
                        <h1 class="page-title mb-2" id="clinics-title">Clinic Management</h1>
                        <p class="text-muted-custom mb-0">Manage clinic locations, service regions, and operational access.</p>
                    </div>
                    <button class="btn btn-primary" type="button" id="add-clinic-button" data-bs-toggle="modal" data-bs-target="#clinic-modal">
                        <i class="bi bi-hospital me-2" aria-hidden="true"></i>
                        Add Clinic
                    </button>
                </div>

                <div class="alert d-none" id="clinics-feedback" role="alert" aria-live="polite"></div>

                <section class="surface-card users-card" aria-labelledby="clinics-table-title">
                    <div class="users-card-toolbar">
                        <div>
                            <h2 class="section-title mb-1" id="clinics-table-title">Registered clinics</h2>
                            <p class="text-muted-custom small mb-0" id="clinics-count">Loading clinics...</p>
                        </div>
                        <div class="users-search">
                            <label class="visually-hidden" for="clinic-search">Search clinics</label>
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input class="form-control" type="search" id="clinic-search" placeholder="Search clinics" autocomplete="off">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table users-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Clinic</th>
                                    <th scope="col">Address</th>
                                    <th scope="col">Region</th>
                                    <th scope="col">Coordinates</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="clinics-table-body">
                                <tr>
                                    <td colspan="6"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading clinics...</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="empty-state d-none" id="clinics-empty">
                        <i class="bi bi-hospital empty-state-icon" aria-hidden="true"></i>
                        <h3 class="section-title">No clinics found</h3>
                        <p class="text-muted-custom mb-3">Try a different search or add a clinic.</p>
                        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#clinic-modal">
                            <i class="bi bi-hospital me-1" aria-hidden="true"></i>
                            Add Clinic
                        </button>
                    </div>
                </section>
            </div>
        </main>

        <div class="modal fade" id="clinic-modal" tabindex="-1" aria-labelledby="clinic-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1" id="clinic-modal-kicker">NEW LOCATION</p>
                            <h2 class="modal-title section-title" id="clinic-modal-title">Add Clinic</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="clinic-form" novalidate>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none" id="clinic-form-error" role="alert"></div>
                            <input type="hidden" id="clinic-id">
                            <div class="mb-3">
                                <label class="form-label" for="clinic-name">Clinic name</label>
                                <input class="form-control" type="text" id="clinic-name" maxlength="150" required>
                                <div class="invalid-feedback">Enter a clinic name.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="clinic-address">Address</label>
                                <input class="form-control" type="text" id="clinic-address" maxlength="255">
                                <div class="invalid-feedback">Address must not exceed 255 characters.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="clinic-region">Region</label>
                                <input class="form-control" type="text" id="clinic-region" maxlength="100">
                                <div class="invalid-feedback">Region must not exceed 100 characters.</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label" for="clinic-latitude">Latitude</label>
                                    <input class="form-control" type="number" id="clinic-latitude" min="-90" max="90" step="any" placeholder="-16.9200">
                                    <div class="invalid-feedback">Enter a latitude between -90 and 90.</div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label" for="clinic-longitude">Longitude</label>
                                    <input class="form-control" type="number" id="clinic-longitude" min="-180" max="180" step="any" placeholder="145.7700">
                                    <div class="invalid-feedback">Enter a longitude between -180 and 180.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="submit" id="save-clinic-button">
                                <i class="bi bi-check2 me-1" aria-hidden="true"></i>
                                Save Clinic
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="clinic-details-modal" tabindex="-1" aria-labelledby="clinic-details-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">CLINIC PROFILE</p>
                            <h2 class="modal-title section-title" id="clinic-details-title">Clinic details</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="clinic-details-content">
                        <p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading clinic details...</p>
                    </div>
                </div>
            </div>
        </div>

        <script src="../frontend/assets/js/api.js"></script>
        <script src="../frontend/assets/js/admin-clinics.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>
