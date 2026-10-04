<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Clinic Staff', 'Admin');

$role = $_SESSION['role_name'] ?? 'Clinic Staff';

renderAuthenticatedLayoutStart('Inventory', 'inventory');
?>
        <main class="dashboard-main" aria-labelledby="inventory-title">
            <div class="dashboard-content">
                <div class="dashboard-heading users-heading">
                    <div>
                        <p class="page-kicker mb-2">INVENTORY OPERATIONS</p>
                        <h1 class="page-title mb-2" id="inventory-title">Inventory</h1>
                        <p class="text-muted-custom mb-0">Review current stock levels and stock-out risk for your clinic or organization.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <?php if ($role === 'Admin'): ?>
                            <button class="btn btn-primary" type="button" id="add-item-button" data-bs-toggle="modal" data-bs-target="#add-item-modal">
                                <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>
                                Add Item
                            </button>
                            <button class="btn btn-outline-primary" type="button" id="add-stock-button" data-bs-toggle="modal" data-bs-target="#add-stock-modal">
                                <i class="bi bi-box-arrow-in-down me-2" aria-hidden="true"></i>
                                Add Stock
                            </button>
                        <?php elseif ($role === 'Clinic Staff'): ?>
                            <button class="btn btn-primary" type="button" id="record-usage-button" data-bs-toggle="modal" data-bs-target="#record-usage-modal">
                                <i class="bi bi-clipboard-minus me-2" aria-hidden="true"></i>
                                Record Usage
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary" type="button" id="refresh-inventory-button">
                            <i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>
                            Refresh
                        </button>
                    </div>
                </div>

                <div class="alert d-none" id="inventory-feedback" role="alert" aria-live="polite"></div>

                <section class="surface-card dashboard-group mb-4" aria-labelledby="inventory-summary-title">
                    <div class="dashboard-group-header">
                        <div class="dashboard-group-icon icon-info"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
                        <div>
                            <h2 id="inventory-summary-title">Inventory summary</h2>
                            <p>Current stock-out risk counts from the backend inventory view</p>
                        </div>
                    </div>
                    <div class="dashboard-metrics">
                        <div class="dashboard-metric metric-primary"><span>Total Items</span><strong id="stat-total">0</strong></div>
                        <div class="dashboard-metric metric-danger"><span>Critical Risk</span><strong id="stat-critical">0</strong></div>
                        <div class="dashboard-metric metric-warning"><span>High Risk</span><strong id="stat-high">0</strong></div>
                        <div class="dashboard-metric metric-info"><span>Medium Risk</span><strong id="stat-medium">0</strong></div>
                        <div class="dashboard-metric metric-success"><span>Low Risk</span><strong id="stat-low">0</strong></div>
                    </div>
                </section>

                <section class="surface-card users-card" aria-labelledby="inventory-table-title">
                    <div class="users-card-toolbar">
                        <div>
                            <h2 class="section-title mb-1" id="inventory-table-title">Inventory levels</h2>
                            <p class="text-muted-custom small mb-0" id="inventory-count">Loading inventory...</p>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 w-100 justify-content-end">
                            <div class="users-search">
                                <label class="visually-hidden" for="inventory-search">Search inventory</label>
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input class="form-control" type="search" id="inventory-search" placeholder="Search inventory" autocomplete="off">
                            </div>
                            <?php if ($role === 'Admin'): ?>
                                <div>
                                    <label class="visually-hidden" for="inventory-clinic-filter">Filter by clinic</label>
                                    <select class="form-select" id="inventory-clinic-filter" aria-label="Filter by clinic">
                                        <option value="">Select a clinic</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label class="visually-hidden" for="inventory-risk-filter">Filter by stock risk</label>
                                <select class="form-select" id="inventory-risk-filter" aria-label="Filter by stock risk">
                                    <option value="">All risks</option>
                                    <option value="Critical">Critical</option>
                                    <option value="High">High</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Low">Low</option>
                                    <option value="Unknown">Unknown</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table users-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Clinic</th>
                                    <th scope="col">Quantity Available</th>
                                    <th scope="col">Daily Usage</th>
                                    <th scope="col">Days Remaining</th>
                                    <th scope="col">Stock-Out Risk</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="inventory-table-body">
                                <tr>
                                    <td colspan="7"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading inventory...</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="empty-state d-none" id="inventory-empty">
                        <i class="bi bi-box-seam empty-state-icon" aria-hidden="true"></i>
                        <h3 class="section-title">No inventory found</h3>
                        <p class="text-muted-custom mb-0">Try a different search, clinic filter, or risk filter.</p>
                    </div>
                </section>
            </div>
        </main>

        <div class="modal fade" id="inventory-details-modal" tabindex="-1" aria-labelledby="inventory-details-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="page-kicker mb-1">ITEM PROFILE</p>
                            <h2 class="modal-title section-title" id="inventory-details-title">Inventory details</h2>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="inventory-details-content">
                        <p class="loading-indicator mb-0"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading inventory details...</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($role === 'Admin'): ?>
            <div class="modal fade" id="add-item-modal" tabindex="-1" aria-labelledby="add-item-modal-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <p class="page-kicker mb-1">CATALOG MANAGEMENT</p>
                                <h2 class="modal-title section-title" id="add-item-modal-title">Add Medical Item</h2>
                            </div>
                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="add-item-form" novalidate>
                            <div class="modal-body">
                                <div class="alert alert-danger d-none" id="add-item-error" role="alert"></div>

                                <div class="mb-3">
                                    <label class="form-label" for="item-name">Item Name <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" id="item-name" maxlength="150" placeholder="e.g., Amoxicillin 500mg" required>
                                    <div class="invalid-feedback">Please enter an item name.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="item-category">Category</label>
                                    <input class="form-control" type="text" id="item-category" maxlength="100" placeholder="e.g., Antibiotics, Vaccines, PPE">
                                    <div class="invalid-feedback">Category must not exceed 100 characters.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="item-criticality">Criticality Score (0 - 100)</label>
                                    <input class="form-control" type="number" id="item-criticality" min="0" max="100" step="1" value="50" placeholder="50">
                                    <div class="invalid-feedback">Enter a score between 0 and 100.</div>
                                    <div class="form-text">Higher scores prioritize this item during emergency distribution and stock-outs.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="item-cold-chain">Cold Chain Required</label>
                                    <select class="form-select" id="item-cold-chain">
                                        <option value="0" selected>No - Standard Storage</option>
                                        <option value="1">Yes - Temperature Controlled</option>
                                    </select>
                                </div>

                                <div class="row g-3 d-none" id="item-temperature-fields">
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label" for="item-min-temp">Min Temperature (°C) <span class="text-danger">*</span></label>
                                        <input class="form-control" type="number" id="item-min-temp" step="0.1" placeholder="e.g., 2.0" disabled>
                                        <div class="invalid-feedback">Enter a valid minimum temperature.</div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label" for="item-max-temp">Max Temperature (°C) <span class="text-danger">*</span></label>
                                        <input class="form-control" type="number" id="item-max-temp" step="0.1" placeholder="e.g., 8.0" disabled>
                                        <div class="invalid-feedback">Max must be greater than min temperature.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-primary" type="submit" id="save-item-button">
                                    <i class="bi bi-check2 me-1" aria-hidden="true"></i>
                                    Save Item
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="add-stock-modal" tabindex="-1" aria-labelledby="add-stock-modal-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <p class="page-kicker mb-1">STOCK MANAGEMENT</p>
                                <h2 class="modal-title section-title" id="add-stock-modal-title">Add Inventory Stock</h2>
                            </div>
                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="add-stock-form" novalidate>
                            <div class="modal-body">
                                <div class="alert alert-danger d-none" id="add-stock-error" role="alert"></div>

                                <div class="mb-3">
                                    <label class="form-label" for="stock-clinic-id">Destination Clinic <span class="text-danger">*</span></label>
                                    <select class="form-select" id="stock-clinic-id" required>
                                        <option value="">Select clinic...</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a clinic.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="stock-item-id">Medical Item <span class="text-danger">*</span></label>
                                    <select class="form-select" id="stock-item-id" required>
                                        <option value="">Select item...</option>
                                    </select>
                                    <div class="invalid-feedback">Please select an item.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="stock-quantity">Quantity to Add <span class="text-danger">*</span></label>
                                    <input class="form-control" type="number" id="stock-quantity" min="0.01" step="any" placeholder="e.g., 50" required>
                                    <div class="invalid-feedback">Enter a quantity greater than zero.</div>
                                    <div class="form-text">Existing stock will be increased by this quantity.</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-primary" type="submit" id="save-stock-button">
                                    <i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>
                                    Add Stock
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($role === 'Clinic Staff'): ?>
            <div class="modal fade" id="record-usage-modal" tabindex="-1" aria-labelledby="record-usage-modal-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <p class="page-kicker mb-1">CLINIC CONSUMPTION</p>
                                <h2 class="modal-title section-title" id="record-usage-modal-title">Record Item Usage</h2>
                            </div>
                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="record-usage-form" novalidate>
                            <div class="modal-body">
                                <div class="alert alert-danger d-none" id="record-usage-error" role="alert"></div>

                                <div class="mb-3">
                                    <label class="form-label" for="usage-item-id">Medical Item <span class="text-danger">*</span></label>
                                    <select class="form-select" id="usage-item-id" required>
                                        <option value="">Select item...</option>
                                    </select>
                                    <div class="invalid-feedback">Please select an item from your clinic inventory.</div>
                                </div>

                                <div class="alert alert-info py-2 px-3 mb-3 d-flex justify-content-between align-items-center" id="usage-stock-info">
                                    <span class="small text-muted-custom">Current Stock Available:</span>
                                    <strong id="usage-available-qty">--</strong>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="usage-quantity">Quantity Used <span class="text-danger">*</span></label>
                                    <input class="form-control" type="number" id="usage-quantity" min="0.01" step="any" placeholder="e.g., 3" required>
                                    <div class="invalid-feedback">Enter a quantity greater than zero.</div>
                                    <div class="form-text">Available stock will be decreased by this amount.</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-primary" type="submit" id="save-usage-button">
                                    <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>
                                    Record Usage
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <script>
            window.MedLineInventoryContext = {
                role: <?= json_encode($role, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            };
        </script>
        <script src="../frontend/assets/js/api.js"></script>
        <script src="../frontend/assets/js/inventory.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>
