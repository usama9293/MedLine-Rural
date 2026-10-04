<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../frontend/components/auth-layout.php';

requireRole('Driver');

renderAuthenticatedLayoutStart('Driver Dashboard');
?>
		<main class="dashboard-main" aria-labelledby="dashboard-title">
			<div class="dashboard-content">
				<div class="dashboard-heading">
					<div>
						<p class="page-kicker mb-2">DELIVERY OPERATIONS</p>
						<h1 class="page-title mb-2" id="dashboard-title">Driver dashboard</h1>
						<p class="text-muted-custom mb-0">Assigned shipments, cold-chain temperature telemetry, and delivery schedules.</p>
					</div>
					<div class="dashboard-refresh-status text-muted-custom small" id="last-updated" aria-live="polite"></div>
				</div>

				<div class="alert alert-danger dashboard-alert d-none" id="dashboard-error" role="alert">
					<div class="d-flex align-items-start gap-3">
						<i class="bi bi-exclamation-triangle-fill fs-5" aria-hidden="true"></i>
						<div class="flex-grow-1">
							<strong class="d-block mb-1">Dashboard unavailable</strong>
							<span id="dashboard-error-message">Unable to load driver delivery data. Please try again.</span>
						</div>
						<button class="btn btn-sm btn-outline-danger" type="button" id="retry-dashboard">
							<i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
							Retry
						</button>
					</div>
				</div>

				<div id="dashboard-loading" aria-live="polite">
					<p class="loading-indicator mb-3"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading assignments and telemetry...</p>
					<div class="dashboard-kpi-row mb-4">
						<div class="surface-card dashboard-group dashboard-skeleton" style="min-height: 80px;" aria-hidden="true"></div>
						<div class="surface-card dashboard-group dashboard-skeleton" style="min-height: 80px;" aria-hidden="true"></div>
						<div class="surface-card dashboard-group dashboard-skeleton" style="min-height: 80px;" aria-hidden="true"></div>
						<div class="surface-card dashboard-group dashboard-skeleton" style="min-height: 80px;" aria-hidden="true"></div>
					</div>
				</div>

				<div id="dashboard-data" class="d-none">
					<!-- KPI Row -->
					<div class="dashboard-kpi-row" aria-label="Driver Delivery Metrics">
						<div class="dashboard-kpi-tile kpi-primary">
							<span class="kpi-label">Total Assigned</span>
							<strong class="kpi-value" id="driver-stat-total">0</strong>
						</div>
						<div class="dashboard-kpi-tile kpi-info">
							<span class="kpi-label">In Transit</span>
							<strong class="kpi-value" id="driver-stat-intransit">0</strong>
						</div>
						<div class="dashboard-kpi-tile kpi-success">
							<span class="kpi-label">Delivered</span>
							<strong class="kpi-value" id="driver-stat-delivered">0</strong>
						</div>
						<div class="dashboard-kpi-tile kpi-warning">
							<span class="kpi-label">Pending</span>
							<strong class="kpi-value" id="driver-stat-pending">0</strong>
						</div>
					</div>

					<!-- Current Active Shipment Banner & Quick Actions -->
					<section class="surface-card p-4 mb-4" aria-labelledby="active-shipment-title">
						<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pb-3 border-bottom mb-3">
							<div class="d-flex align-items-center gap-3">
								<div class="dashboard-group-icon icon-info"><i class="bi bi-truck-front" aria-hidden="true"></i></div>
								<div>
									<h2 class="fs-5 fw-bold mb-1" id="active-shipment-title">Current Active Shipment</h2>
									<p class="text-muted-custom small mb-0">Real-time status of your active delivery assignment</p>
								</div>
							</div>
							<div>
								<a class="btn btn-primary btn-sm" href="../driver/shipments.php">
									<i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>
									My Shipments
								</a>
							</div>
						</div>

						<div id="driver-active-shipment-content">
							<!-- Populated dynamically via JS -->
							<div class="text-center py-3 text-muted-custom">
								<i class="bi bi-box-seam fs-3 d-block mb-2 opacity-50"></i>
								<p class="mb-0">No active delivery in progress right now.</p>
							</div>
						</div>
					</section>

					<!-- Charts Section -->
					<div class="charts-grid">
						<!-- Shipment Status Doughnut -->
						<div class="surface-card p-3">
							<h3 class="fs-6 fw-bold mb-1 text-muted-custom">Assigned Shipments Distribution</h3>
							<div class="chart-container" id="container-driver-status">
								<canvas id="chart-driver-status"></canvas>
								<div class="chart-empty-state d-none" id="empty-driver-status">
									<i class="bi bi-truck" aria-hidden="true"></i>
									<p>No shipments assigned</p>
								</div>
							</div>
						</div>

						<!-- Temperature Telemetry Line Chart -->
						<div class="surface-card p-3">
							<div class="d-flex justify-content-between align-items-center mb-1">
								<h3 class="fs-6 fw-bold mb-0 text-muted-custom">Temperature Telemetry (°C)</h3>
								<span class="badge badge-soft-info" id="driver-telemetry-target">Safe: 2°C - 8°C</span>
							</div>
							<div class="chart-container" id="container-driver-telemetry">
								<canvas id="chart-driver-telemetry"></canvas>
								<div class="chart-empty-state d-none" id="empty-driver-telemetry">
									<i class="bi bi-thermometer-half" aria-hidden="true"></i>
									<p>No telemetry recorded for active shipment</p>
								</div>
							</div>
						</div>
					</div>

					<!-- Open Alerts & Telemetry Readings Table -->
					<div class="row g-4 mt-1">
						<!-- Latest Telemetry Readings -->
						<div class="col-12 col-xl-7">
							<section class="surface-card users-card h-100" aria-labelledby="driver-readings-title">
								<div class="users-card-toolbar">
									<div>
										<h2 class="section-title mb-1" id="driver-readings-title">Latest Telemetry Readings</h2>
										<p class="text-muted-custom small mb-0">Recent cold-chain temperature samples</p>
									</div>
									<span class="badge badge-soft-primary" id="driver-telemetry-count">0 Samples</span>
								</div>
								<div class="table-responsive">
									<table class="table users-table align-middle mb-0">
										<thead>
											<tr>
												<th scope="col">Time</th>
												<th scope="col">Temperature</th>
												<th scope="col">Cold-Chain Status</th>
												<th scope="col">Coordinates</th>
											</tr>
										</thead>
										<tbody id="driver-telemetry-body">
											<tr><td colspan="4"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading telemetry samples...</span></td></tr>
										</tbody>
									</table>
								</div>
								<div class="empty-state d-none" id="driver-telemetry-empty">
									<i class="bi bi-thermometer-snow empty-state-icon" aria-hidden="true"></i>
									<h3 class="section-title">No telemetry recorded</h3>
									<p class="text-muted-custom mb-0">Temperature data will appear once logged by device.</p>
								</div>
							</section>
						</div>

						<!-- Open Shipment Alerts -->
						<div class="col-12 col-xl-5">
							<section class="surface-card users-card h-100" aria-labelledby="driver-alerts-title">
								<div class="users-card-toolbar">
									<div>
										<h2 class="section-title mb-1" id="driver-alerts-title">Shipment Alerts</h2>
										<p class="text-muted-custom small mb-0">Alerts linked to your shipments</p>
									</div>
									<span class="badge badge-soft-danger" id="driver-alerts-badge">0 Alerts</span>
								</div>
								<div class="table-responsive">
									<table class="table users-table align-middle mb-0">
										<thead>
											<tr>
												<th scope="col">Type</th>
												<th scope="col">Message</th>
												<th scope="col">Status</th>
											</tr>
										</thead>
										<tbody id="driver-alerts-body">
											<tr><td colspan="3"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Checking alerts...</span></td></tr>
										</tbody>
									</table>
								</div>
								<div class="empty-state d-none" id="driver-alerts-empty">
									<i class="bi bi-shield-check empty-state-icon text-success" aria-hidden="true"></i>
									<h3 class="section-title">No active alerts</h3>
									<p class="text-muted-custom mb-0">All shipments within parameters.</p>
								</div>
							</section>
						</div>
					</div>
				</div>
			</div>
		</main>

	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
	<script src="../frontend/assets/js/api.js"></script>
	<script src="../frontend/assets/js/driver-dashboard.js"></script>
<?php renderAuthenticatedLayoutEnd(); ?>