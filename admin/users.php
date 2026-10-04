<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Admin');

renderAuthenticatedLayoutStart('User Management', 'users');
?>
        <main class="dashboard-main" aria-labelledby="users-title">
            <div class="dashboard-content">
                <div class="dashboard-heading users-heading">
                    <div>
                        <p class="page-kicker mb-2">ADMINISTRATION</p>
                        <h1 class="page-title mb-2" id="users-title">User Management</h1>
                        <p class="text-muted-custom mb-0">Manage system users, roles, and account access.</p>
                    </div>
                    <button class="btn btn-primary" type="button" id="add-user-button" data-bs-toggle="modal" data-bs-target="#user-modal">
                        <i class="bi bi-person-plus me-2" aria-hidden="true"></i>
                        Add User
                    </button>
                </div>

                <div class="alert d-none" id="users-feedback" role="alert" aria-live="polite"></div>

                <section class="surface-card users-card" aria-labelledby="users-table-title">
                    <div class="users-card-toolbar">
                        <div>
                            <h2 class="section-title mb-1" id="users-table-title">System users</h2>
                            <p class="text-muted-custom small mb-0" id="users-count">Loading users...</p>
                        </div>
                        <div class="users-search">
                            <label class="visually-hidden" for="user-search">Search users</label>
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input class="form-control" type="search" id="user-search" placeholder="Search users" autocomplete="off">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table users-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">User</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Clinic</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-table-body">
                                <tr class="users-loading-row">
                                    <td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading users...</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="empty-state d-none" id="users-empty">
                        <i class="bi bi-people empty-state-icon" aria-hidden="true"></i>
                        <h3 class="section-title">No users found</h3>
                        <p class="text-muted-custom mb-3">Try a different search or add the first user.</p>
                        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#user-modal">
                            <i class="bi bi-person-plus me-1" aria-hidden="true"></i>
                            Add User
                        </button>
                    </div>
                </section>
            </div>
        </main>

        <div class="modal fade" id="user-modal" tabindex="-1" aria-labelledby="user-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1" id="user-modal-kicker">NEW ACCOUNT</p>
                            <h2 class="modal-title section-title" id="user-modal-title">Add User</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="user-form" novalidate>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none" id="form-error" role="alert"></div>
                            <input type="hidden" id="user-id" name="user_id">
                            <div class="mb-3">
                                <label class="form-label" for="full-name">Full name</label>
                                <input class="form-control" type="text" id="full-name" name="full_name" maxlength="150" required>
                                <div class="invalid-feedback">Enter the user's full name.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="user-email">Email address</label>
                                <input class="form-control" type="email" id="user-email" name="email" maxlength="150" required>
                                <div class="invalid-feedback">Enter a valid email address.</div>
                            </div>
                            <div class="mb-3" id="password-field">
                                <label class="form-label" for="user-password">Password</label>
                                <input class="form-control" type="password" id="user-password" name="password" minlength="8" autocomplete="new-password">
                                <div class="form-text">Use at least 8 characters.</div>
                                <div class="invalid-feedback">Use at least 8 characters.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="user-role">Role</label>
                                <select class="form-select" id="user-role" name="role_id" required>
                                    <option value="">Select a role</option>
                                </select>
                                <div class="invalid-feedback">Select a role.</div>
                            </div>
                            <div class="mb-0 d-none" id="clinic-field">
                                <label class="form-label" for="user-clinic">Clinic</label>
                                <select class="form-select" id="user-clinic" name="clinic_id">
                                    <option value="">Select a clinic</option>
                                </select>
                                <div class="invalid-feedback">Select a clinic for Clinic Staff.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="submit" id="save-user-button">
                                <i class="bi bi-check2 me-1" aria-hidden="true"></i>
                                Save User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="details-modal" tabindex="-1" aria-labelledby="details-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">ACCOUNT DETAILS</p>
                            <h2 class="modal-title section-title" id="details-modal-title">User details</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="details-content">
                        <p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading details...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="reset-modal" tabindex="-1" aria-labelledby="reset-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">ACCOUNT SECURITY</p>
                            <h2 class="modal-title section-title" id="reset-modal-title">Reset password</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="reset-form">
                        <div class="modal-body">
                            <p class="text-muted-custom small" id="reset-user-label"></p>
                            <div class="alert alert-danger d-none" id="reset-error" role="alert"></div>
                            <input type="hidden" id="reset-user-id">
                            <label class="form-label" for="new-password">New password</label>
                            <input class="form-control" type="password" id="new-password" minlength="8" autocomplete="new-password" required>
                            <div class="form-text">Use at least 8 characters.</div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="submit" id="reset-password-button">Reset Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="../frontend/assets/js/api.js"></script>
        <script src="../frontend/assets/js/admin-users.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>
