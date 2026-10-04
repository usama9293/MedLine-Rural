<?php

function renderAuthenticatedLayoutStart(string $pageTitle, string $activePage = 'dashboard'): void
{
    $role = $_SESSION['role_name'] ?? '';
    $userName = htmlspecialchars($_SESSION['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
    $roleLabel = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
    $dashboardPath = match ($role) {
        'Admin' => '../admin/dashboard.php',
        'Dispatcher' => '../dispatcher/dashboard.php',
        'Driver' => '../driver/dashboard.php',
        'Clinic Staff' => '../clinic/dashboard.php',
        default => '../frontend/login.php'
    };
    $navigation = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'href' => $dashboardPath]
    ];

    if ($role === 'Admin') {
        $navigation[] = ['key' => 'users', 'label' => 'User Management', 'icon' => 'bi-people-fill', 'href' => '../admin/users.php'];
        $navigation[] = ['key' => 'clinics', 'label' => 'Clinic Management', 'icon' => 'bi-hospital-fill', 'href' => '../admin/clinics.php'];
        $navigation[] = ['key' => 'vehicles', 'label' => 'Vehicle Management', 'icon' => 'bi-truck-front-fill', 'href' => '../admin/vehicles.php'];
        $navigation[] = ['key' => 'drivers', 'label' => 'Driver Management', 'icon' => 'bi-person-badge-fill', 'href' => '../admin/drivers.php'];
        $navigation[] = ['key' => 'requests', 'label' => 'Supply Requests', 'icon' => 'bi-clipboard2-pulse-fill', 'href' => '../admin/requests.php'];
        $navigation[] = ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'bi-box-seam', 'href' => '../admin/inventory.php'];
        $navigation[] = ['key' => 'shipments', 'label' => 'Shipment Management', 'icon' => 'bi-truck-front-fill', 'href' => '../admin/shipments.php'];
        $navigation[] = ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bi-bell-fill', 'href' => '../admin/notifications.php'];
    }
    if ($role === 'Dispatcher') {
        $navigation[] = ['key' => 'requests', 'label' => 'Supply Requests', 'icon' => 'bi-clipboard2-pulse-fill', 'href' => '../dispatcher/requests.php'];
        $navigation[] = ['key' => 'shipments', 'label' => 'Shipment Management', 'icon' => 'bi-truck-front-fill', 'href' => '../dispatcher/shipments.php'];
        $navigation[] = ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bi-bell-fill', 'href' => '../dispatcher/notifications.php'];
    }
    if ($role === 'Driver') {
        $navigation[] = ['key' => 'shipments', 'label' => 'My Shipments', 'icon' => 'bi-truck-front-fill', 'href' => '../driver/shipments.php'];
        $navigation[] = ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bi-bell-fill', 'href' => '../driver/shipments.php#notifications'];
    }
    if ($role === 'Clinic Staff') {
        $navigation[] = ['key' => 'requests', 'label' => 'Supply Requests', 'icon' => 'bi-clipboard2-pulse-fill', 'href' => '../clinic/requests.php'];
        $navigation[] = ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'bi-box-seam', 'href' => '../admin/inventory.php'];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - MedLine Rural">
        <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - MedLine Rural</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="../frontend/assets/css/style.css" rel="stylesheet">
    </head>
    <body class="dashboard-page">
        <header class="app-header dashboard-header">
            <nav class="navbar navbar-expand-lg" aria-label="Primary navigation">
                <div class="container-fluid px-3 px-lg-4">
                    <a class="app-brand" href="<?= $dashboardPath ?>" aria-label="MedLine Rural dashboard">
                        <span class="app-brand-mark"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
                        MedLine Rural
                    </a>
                    <div class="d-flex align-items-center gap-3 ms-auto">
                        <div class="dashboard-user d-none d-sm-block text-end">
                            <strong><?= $userName ?></strong>
                            <span><?= $roleLabel ?></span>
                        </div>
                        <a class="btn btn-outline-secondary btn-sm dashboard-logout" href="../api/auth/logout.php">
                            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </nav>
        </header>

        <div class="dashboard-shell">
            <aside class="app-sidebar dashboard-sidebar" aria-label="Authenticated navigation">
                <div class="dashboard-sidebar-inner">
                    <p class="sidebar-label"><?= $roleLabel ?> workspace</p>
                    <nav class="nav flex-column gap-1">
                        <?php foreach ($navigation as $item): ?>
                            <a class="app-sidebar-link<?= $activePage === $item['key'] ? ' active' : '' ?>" href="<?= $item['href'] ?>"<?= $activePage === $item['key'] ? ' aria-current="page"' : '' ?>>
                                <i class="bi <?= $item['icon'] ?>" aria-hidden="true"></i>
                                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <div class="sidebar-context">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <div>
                            <strong><?= $roleLabel ?> access</strong>
                            <span>Authenticated workspace</span>
                        </div>
                    </div>
                </div>
            </aside>
    <?php
}

function renderAuthenticatedLayoutEnd(): void
{
    ?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
