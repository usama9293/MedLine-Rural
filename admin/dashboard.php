<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Admin');


renderAuthenticatedLayoutStart('Admin Dashboard');
?>
		<main class="dashboard-main" aria-labelledby="dashboard-title">
			<div class="dashboard-content">
				<div class="dashboard-heading">
					<div>
						<p class="page-kicker mb-2">ADMINISTRATION</p>
						<h1 class="page-title mb-2" id="dashboard-title">Operations overview</h1>
						<p class="text-muted-custom mb-0">Monitor requests, shipments, alerts, and fleet availability.</p>
					</div>
					<div class="dashboard-refresh-status text-muted-custom small" id="last-updated" aria-live="polite"></div>
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

				<div id="dashboard-loading" aria-live="polite">
					<p class="loading-indicator mb-3"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading dashboard...</p>
					<div class="row g-4">
						<div class="col-12 col-xl-6"><div class="surface-card dashboard-group dashboard-skeleton" aria-hidden="true"></div></div>
						<div class="col-12 col-xl-6"><div class="surface-card dashboard-group dashboard-skeleton" aria-hidden="true"></div></div>
						<div class="col-12 col-xl-6"><div class="surface-card dashboard-group dashboard-skeleton" aria-hidden="true"></div></div>
						<div class="col-12 col-xl-6"><div class="surface-card dashboard-group dashboard-skeleton" aria-hidden="true"></div></div>
					</div>
				</div>

				<div id="dashboard-data" class="d-none">
					<div class="row g-4">
						<div class="col-12 col-xl-6">
						<section class="surface-card dashboard-group" aria-labelledby="requests-title">
							<div class="dashboard-group-header"><div class="dashboard-group-icon icon-info"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></div><div><h2 id="requests-title">Requests</h2><p>Supply request activity</p></div></div>
							<div class="dashboard-metrics">
								<div class="dashboard-metric metric-primary"><span>Total Requests</span><strong data-stat="requests.total">0</strong></div>
								<div class="dashboard-metric metric-warning"><span>Pending</span><strong data-stat="requests.pending">0</strong></div>
								<div class="dashboard-metric metric-success"><span>Approved</span><strong data-stat="requests.approved">0</strong></div>
								<div class="dashboard-metric metric-success"><span>Completed</span><strong data-stat="requests.completed">0</strong></div>
							</div>
						</section>
					</div>

					<div class="col-12 col-xl-6">
						<section class="surface-card dashboard-group" aria-labelledby="shipments-title">
							<div class="dashboard-group-header"><div class="dashboard-group-icon icon-info"><i class="bi bi-truck" aria-hidden="true"></i></div><div><h2 id="shipments-title">Shipments</h2><p>Delivery network activity</p></div></div>
							<div class="dashboard-metrics">
								<div class="dashboard-metric metric-primary"><span>Total Shipments</span><strong data-stat="shipments.total">0</strong></div>
								<div class="dashboard-metric metric-warning"><span>Pending</span><strong data-stat="shipments.pending">0</strong></div>
								<div class="dashboard-metric metric-info"><span>Dispatched</span><strong data-stat="shipments.dispatched">0</strong></div>
								<div class="dashboard-metric metric-info"><span>In Transit</span><strong data-stat="shipments.in_transit">0</strong></div>
								<div class="dashboard-metric metric-success"><span>Delivered</span><strong data-stat="shipments.delivered">0</strong></div>
							</div>
						</section>
					</div>

					<div class="col-12 col-xl-6">
						<section class="surface-card dashboard-group" aria-labelledby="alerts-title">
							<div class="dashboard-group-header"><div class="dashboard-group-icon icon-danger"><i class="bi bi-bell" aria-hidden="true"></i></div><div><h2 id="alerts-title">Alerts</h2><p>Telemetry attention points</p></div></div>
							<div class="dashboard-metrics">
								<div class="dashboard-metric metric-primary"><span>Total Alerts</span><strong data-stat="alerts.total">0</strong></div>
								<div class="dashboard-metric metric-danger"><span>Open</span><strong data-stat="alerts.open">0</strong></div>
								<div class="dashboard-metric metric-warning"><span>Acknowledged</span><strong data-stat="alerts.acknowledged">0</strong></div>
								<div class="dashboard-metric metric-success"><span>Resolved</span><strong data-stat="alerts.resolved">0</strong></div>
							</div>
						</section>
					</div>

					<div class="col-12 col-xl-6">
						<section class="surface-card dashboard-group" aria-labelledby="vehicles-title">
							<div class="dashboard-group-header"><div class="dashboard-group-icon icon-success"><i class="bi bi-car-front" aria-hidden="true"></i></div><div><h2 id="vehicles-title">Vehicles</h2><p>Fleet availability</p></div></div>
							<div class="dashboard-metrics">
								<div class="dashboard-metric metric-primary"><span>Total Vehicles</span><strong data-stat="vehicles.total">0</strong></div>
								<div class="dashboard-metric metric-success"><span>Available</span><strong data-stat="vehicles.available">0</strong></div>
								<div class="dashboard-metric metric-neutral"><span>Inactive</span><strong data-stat="vehicles.inactive">0</strong></div>
							</div>
						</section>
					</div>
				</div>

				<!-- Distribution Charts -->
				<section class="mt-4" aria-labelledby="charts-heading">
					<h2 class="section-title mb-3 fs-5" id="charts-heading">Distribution Analytics</h2>
					<div class="charts-grid">
						<div class="surface-card p-3">
							<h3 class="fs-6 fw-bold mb-1 text-muted-custom">Request Status</h3>
							<div class="chart-container" id="container-admin-requests">
								<canvas id="chart-admin-requests"></canvas>
								<div class="chart-empty-state d-none" id="empty-admin-requests">
									<i class="bi bi-clipboard2" aria-hidden="true"></i>
									<p>No request data available</p>
								</div>
							</div>
						</div>
						<div class="surface-card p-3">
							<h3 class="fs-6 fw-bold mb-1 text-muted-custom">Shipment Status</h3>
							<div class="chart-container" id="container-admin-shipments">
								<canvas id="chart-admin-shipments"></canvas>
								<div class="chart-empty-state d-none" id="empty-admin-shipments">
									<i class="bi bi-truck" aria-hidden="true"></i>
									<p>No shipment data available</p>
								</div>
							</div>
						</div>
						<div class="surface-card p-3">
							<h3 class="fs-6 fw-bold mb-1 text-muted-custom">Alert Status</h3>
							<div class="chart-container" id="container-admin-alerts">
								<canvas id="chart-admin-alerts"></canvas>
								<div class="chart-empty-state d-none" id="empty-admin-alerts">
									<i class="bi bi-bell" aria-hidden="true"></i>
									<p>No alert data available</p>
								</div>
							</div>
						</div>
					</div>
				</section>

				<!-- Recent Operational Tables -->
				<div class="row g-4 mt-1">
					<!-- Recent Supply Requests -->
					<div class="col-12 col-xl-6">
						<section class="surface-card users-card h-100" aria-labelledby="admin-requests-title">
							<div class="users-card-toolbar">
								<div>
									<h2 class="section-title mb-1" id="admin-requests-title">Recent Supply Requests</h2>
									<p class="text-muted-custom small mb-0">Latest 5 requests submitted across clinics</p>
								</div>
								<a class="btn btn-sm btn-link text-decoration-none p-0" href="../admin/requests.php">View all &rarr;</a>
							</div>
							<div class="table-responsive">
								<table class="table users-table align-middle mb-0">
									<thead>
										<tr>
											<th scope="col">ID</th>
											<th scope="col">Clinic</th>
											<th scope="col">Urgency</th>
											<th scope="col">Status</th>
											<th scope="col">Requested</th>
										</tr>
									</thead>
									<tbody id="admin-recent-requests-body">
										<tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading requests...</span></td></tr>
									</tbody>
								</table>
							</div>
							<div class="empty-state d-none" id="admin-recent-requests-empty">
								<i class="bi bi-clipboard2 empty-state-icon" aria-hidden="true"></i>
								<h3 class="section-title">No supply requests found</h3>
								<p class="text-muted-custom mb-0">No supply requests have been recorded yet.</p>
							</div>
						</section>
					</div>

					<!-- Recent Shipments -->
					<div class="col-12 col-xl-6">
						<section class="surface-card users-card h-100" aria-labelledby="admin-shipments-title">
							<div class="users-card-toolbar">
								<div>
									<h2 class="section-title mb-1" id="admin-shipments-title">Recent Shipments</h2>
									<p class="text-muted-custom small mb-0">Latest 5 shipments in the dispatch pipeline</p>
								</div>
								<a class="btn btn-sm btn-link text-decoration-none p-0" href="../admin/shipments.php">View all &rarr;</a>
							</div>
							<div class="table-responsive">
								<table class="table users-table align-middle mb-0">
									<thead>
										<tr>
											<th scope="col">Shipment</th>
											<th scope="col">Request</th>
											<th scope="col">Driver / Vehicle</th>
											<th scope="col">Status</th>
											<th scope="col">Est. Delivery</th>
										</tr>
									</thead>
									<tbody id="admin-recent-shipments-body">
										<tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading shipments...</span></td></tr>
									</tbody>
								</table>
							</div>
							<div class="empty-state d-none" id="admin-recent-shipments-empty">
								<i class="bi bi-truck empty-state-icon" aria-hidden="true"></i>
								<h3 class="section-title">No shipments found</h3>
								<p class="text-muted-custom mb-0">No shipments currently scheduled.</p>
							</div>
						</section>
					</div>

					<!-- Recent Alerts -->
					<div class="col-12">
						<section class="surface-card users-card" aria-labelledby="admin-alerts-title">
							<div class="users-card-toolbar">
								<div>
									<h2 class="section-title mb-1" id="admin-alerts-title">Recent Telemetry Alerts</h2>
									<p class="text-muted-custom small mb-0">Latest 5 alerts triggered across fleet operations</p>
								</div>
								<span class="badge badge-soft-danger" id="admin-open-alerts-count">0 Open</span>
							</div>
							<div class="table-responsive">
								<table class="table users-table align-middle mb-0">
									<thead>
										<tr>
											<th scope="col">Alert ID</th>
											<th scope="col">Type</th>
											<th scope="col">Message</th>
											<th scope="col">Status</th>
											<th scope="col">Triggered</th>
										</tr>
									</thead>
									<tbody id="admin-recent-alerts-body">
										<tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading alerts...</span></td></tr>
									</tbody>
								</table>
							</div>
							<div class="empty-state d-none" id="admin-recent-alerts-empty">
								<i class="bi bi-bell-slash empty-state-icon" aria-hidden="true"></i>
								<h3 class="section-title">No recent alerts</h3>
								<p class="text-muted-custom mb-0">All telemetry parameters operating within safe limits.</p>
							</div>
						</section>
					</div>
				</div>
				</div>
			</div>
		</main>
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
	<script src="../frontend/assets/js/api.js"></script>
	<script src="../frontend/assets/js/admin-dashboard.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>