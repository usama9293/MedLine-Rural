<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Dispatcher');

renderAuthenticatedLayoutStart('Dispatcher Dashboard');
?>
		<main class="dashboard-main" aria-labelledby="dashboard-title">
			<div class="dashboard-content">
				<div class="dashboard-heading">
					<div>
						<p class="page-kicker mb-2">DISPATCH OPERATIONS</p>
						<h1 class="page-title mb-2" id="dashboard-title">Dispatcher dashboard</h1>
						<p class="text-muted-custom mb-0">Operational workspace for requests triage, active shipments, and cold-chain telemetry.</p>
					</div>
					<div class="dashboard-refresh-status text-muted-custom small" id="last-updated" aria-live="polite"></div>
				</div>

				<div class="alert alert-danger dashboard-alert d-none" id="dashboard-error" role="alert">
					<div class="d-flex align-items-start gap-3">
						<i class="bi bi-exclamation-triangle-fill fs-5" aria-hidden="true"></i>
						<div class="flex-grow-1">
							<strong class="d-block mb-1">Dashboard unavailable</strong>
							<span id="dashboard-error-message">Unable to load dispatch data. Please try again.</span>
						</div>
						<button class="btn btn-sm btn-outline-danger" type="button" id="retry-dashboard">
							<i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
							Retry
						</button>
					</div>
				</div>

				<!-- Quick Actions & Workflows -->
				<section class="surface-card p-3 mb-4" aria-labelledby="quick-actions-title">
					<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
						<div class="d-flex align-items-center gap-2">
							<div class="dashboard-group-icon icon-info"><i class="bi bi-truck" aria-hidden="true"></i></div>
							<div>
								<h2 class="fs-6 fw-bold mb-0" id="quick-actions-title">Dispatch Workflows</h2>
								<p class="text-muted-custom small mb-0">Review queues and dispatch approved requests</p>
							</div>
						</div>
						<div class="d-flex flex-wrap gap-2">
							<a class="btn btn-primary btn-sm" href="../dispatcher/requests.php">
								<i class="bi bi-clipboard2-pulse-fill me-1" aria-hidden="true"></i>
								Supply Requests
							</a>
							<a class="btn btn-outline-secondary btn-sm" href="../dispatcher/shipments.php">
								<i class="bi bi-truck-front-fill me-1" aria-hidden="true"></i>
								Shipment Management
							</a>
						</div>
					</div>
				</section>

				<div id="dashboard-loading" aria-live="polite">
					<p class="loading-indicator mb-3"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading dispatch overview...</p>
					<div class="dashboard-kpi-row mb-4">
						<div class="surface-card dashboard-group dashboard-skeleton" style="min-height: 80px;" aria-hidden="true"></div>
						<div class="surface-card dashboard-group dashboard-skeleton" style="min-height: 80px;" aria-hidden="true"></div>
						<div class="surface-card dashboard-group dashboard-skeleton" style="min-height: 80px;" aria-hidden="true"></div>
						<div class="surface-card dashboard-group dashboard-skeleton" style="min-height: 80px;" aria-hidden="true"></div>
					</div>
				</div>

				<div id="dashboard-data" class="d-none">
					<!-- KPI Row -->
					<div class="dashboard-kpi-row" aria-label="Dispatch Key Performance Indicators">
						<div class="dashboard-kpi-tile kpi-warning">
							<span class="kpi-label">Pending Requests</span>
							<strong class="kpi-value" id="disp-stat-pending-requests">0</strong>
						</div>
						<div class="dashboard-kpi-tile kpi-info">
							<span class="kpi-label">Approved Requests</span>
							<strong class="kpi-value" id="disp-stat-approved-requests">0</strong>
						</div>
						<div class="dashboard-kpi-tile kpi-primary">
							<span class="kpi-label">Active Shipments</span>
							<strong class="kpi-value" id="disp-stat-active-shipments">0</strong>
						</div>
						<div class="dashboard-kpi-tile kpi-danger">
							<span class="kpi-label">Open Alerts</span>
							<strong class="kpi-value" id="disp-stat-open-alerts">0</strong>
						</div>
					</div>

					<!-- Distribution Charts -->
					<section class="mt-4" aria-labelledby="disp-charts-heading">
						<h2 class="section-title mb-3 fs-5" id="disp-charts-heading">Operational Distributions</h2>
						<div class="charts-grid">
							<div class="surface-card p-3">
								<h3 class="fs-6 fw-bold mb-1 text-muted-custom">Request Status</h3>
								<div class="chart-container" id="container-disp-requests">
									<canvas id="chart-disp-requests"></canvas>
									<div class="chart-empty-state d-none" id="empty-disp-requests">
										<i class="bi bi-clipboard2" aria-hidden="true"></i>
										<p>No request data available</p>
									</div>
								</div>
							</div>
							<div class="surface-card p-3">
								<h3 class="fs-6 fw-bold mb-1 text-muted-custom">Shipment Status</h3>
								<div class="chart-container" id="container-disp-shipments">
									<canvas id="chart-disp-shipments"></canvas>
									<div class="chart-empty-state d-none" id="empty-disp-shipments">
										<i class="bi bi-truck" aria-hidden="true"></i>
										<p>No shipment data available</p>
									</div>
								</div>
							</div>
							<div class="surface-card p-3">
								<h3 class="fs-6 fw-bold mb-1 text-muted-custom">Urgency Distribution</h3>
								<div class="chart-container" id="container-disp-urgency">
									<canvas id="chart-disp-urgency"></canvas>
									<div class="chart-empty-state d-none" id="empty-disp-urgency">
										<i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
										<p>No requests scored</p>
									</div>
								</div>
							</div>
						</div>
					</section>

					<!-- Tables Section -->
					<div class="row g-4 mt-1">
						<!-- Pending & High Urgency Requests -->
						<div class="col-12 col-xl-6">
							<section class="surface-card users-card h-100" aria-labelledby="disp-req-title">
								<div class="users-card-toolbar">
									<div>
										<h2 class="section-title mb-1" id="disp-req-title">Pending & Urgent Requests</h2>
										<p class="text-muted-custom small mb-0">Prioritized queue awaiting dispatch decision</p>
									</div>
									<a class="btn btn-sm btn-link text-decoration-none p-0" href="../dispatcher/requests.php">Manage &rarr;</a>
								</div>
								<div class="table-responsive">
									<table class="table users-table align-middle mb-0">
										<thead>
											<tr>
												<th scope="col">ID</th>
												<th scope="col">Clinic</th>
												<th scope="col">Urgency</th>
												<th scope="col">Status</th>
												<th scope="col" class="text-end">Action</th>
											</tr>
										</thead>
										<tbody id="disp-requests-body">
											<tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading requests...</span></td></tr>
										</tbody>
									</table>
								</div>
								<div class="empty-state d-none" id="disp-requests-empty">
									<i class="bi bi-check2-circle empty-state-icon text-success" aria-hidden="true"></i>
									<h3 class="section-title">No pending requests</h3>
									<p class="text-muted-custom mb-0">All requests are currently dispatched or completed.</p>
								</div>
							</section>
						</div>

						<!-- Active Shipments -->
						<div class="col-12 col-xl-6">
							<section class="surface-card users-card h-100" aria-labelledby="disp-ship-title">
								<div class="users-card-toolbar">
									<div>
										<h2 class="section-title mb-1" id="disp-ship-title">Active Shipments</h2>
										<p class="text-muted-custom small mb-0">Currently in-transit and dispatched deliveries</p>
									</div>
									<a class="btn btn-sm btn-link text-decoration-none p-0" href="../dispatcher/shipments.php">Manage &rarr;</a>
								</div>
								<div class="table-responsive">
									<table class="table users-table align-middle mb-0">
										<thead>
											<tr>
												<th scope="col">Shipment</th>
												<th scope="col">Driver / Vehicle</th>
												<th scope="col">Status</th>
												<th scope="col">Est. Delivery</th>
											</tr>
										</thead>
										<tbody id="disp-shipments-body">
											<tr><td colspan="4"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading shipments...</span></td></tr>
										</tbody>
									</table>
								</div>
								<div class="empty-state d-none" id="disp-shipments-empty">
									<i class="bi bi-truck empty-state-icon" aria-hidden="true"></i>
									<h3 class="section-title">No active shipments</h3>
									<p class="text-muted-custom mb-0">No shipments are currently in transit or dispatched.</p>
								</div>
							</section>
						</div>

						<!-- Open Alerts -->
						<div class="col-12">
							<section class="surface-card users-card" aria-labelledby="disp-alerts-title">
								<div class="users-card-toolbar">
									<div>
										<h2 class="section-title mb-1" id="disp-alerts-title">Open Telemetry Alerts</h2>
										<p class="text-muted-custom small mb-0">Active temperature or sensor deviations requiring attention</p>
									</div>
									<span class="badge badge-soft-danger" id="disp-open-alerts-badge">0 Open</span>
								</div>
								<div class="table-responsive">
									<table class="table users-table align-middle mb-0">
										<thead>
											<tr>
												<th scope="col">Alert ID</th>
												<th scope="col">Shipment</th>
												<th scope="col">Type</th>
												<th scope="col">Message</th>
												<th scope="col">Triggered</th>
											</tr>
										</thead>
										<tbody id="disp-alerts-body">
											<tr><td colspan="5"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading alerts...</span></td></tr>
										</tbody>
									</table>
								</div>
								<div class="empty-state d-none" id="disp-alerts-empty">
									<i class="bi bi-shield-check empty-state-icon text-success" aria-hidden="true"></i>
									<h3 class="section-title">No open alerts</h3>
									<p class="text-muted-custom mb-0">All cold-chain telemetry parameters are currently nominal.</p>
								</div>
							</section>
						</div>
					</div>
				</div>
			</div>
		</main>

	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
	<script src="../frontend/assets/js/api.js"></script>
	<script src="../frontend/assets/js/dispatcher-dashboard.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>