<?php
$assetPrefix = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/frontend/') !== false) ? '' : 'frontend/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in to MedLine Rural">
    <title>MedLine Rural - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= $assetPrefix ?>assets/css/style.css" rel="stylesheet">
</head>

<body class="login-page">
    <main class="login-layout container-fluid" aria-labelledby="login-title">
        <div class="row min-vh-100 align-items-center justify-content-center g-0">
            <div class="col-12 col-lg-6">
                <section class="login-intro mx-auto" aria-labelledby="brand-title">
                    <a class="login-brand" href="index.html" aria-label="MedLine Rural home">
                        <span class="login-brand-mark"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
                        <span id="brand-title">MedLine Rural</span>
                    </a>
                    <p class="login-eyebrow">RURAL HEALTHCARE OPERATIONS</p>
                    <h1 class="login-title" id="login-title">Reliable care starts with reliable supply.</h1>
                    <p class="login-description">Medical supply management and delivery for rural healthcare.</p>

                    <ul class="login-features list-unstyled mb-0" aria-label="Platform capabilities">
                        <li><i class="bi bi-box-seam" aria-hidden="true"></i><span>Supply Request Management</span></li>
                        <li><i class="bi bi-diagram-3" aria-hidden="true"></i><span>Smart Triage &amp; Prioritization</span></li>
                        <li><i class="bi bi-truck" aria-hidden="true"></i><span>Shipment Tracking</span></li>
                        <li><i class="bi bi-thermometer-half" aria-hidden="true"></i><span>Cold-Chain Monitoring</span></li>
                    </ul>
                </section>
            </div>

            <div class="col-12 col-lg-6">
                <section class="login-panel surface-card mx-auto" aria-labelledby="welcome-title">
                    <div class="surface-card-body p-4 p-sm-5">
                        <div class="login-icon" aria-hidden="true">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <p class="login-panel-kicker mb-2">SECURE ACCESS</p>
                        <h2 class="login-panel-title mb-2" id="welcome-title">Welcome back</h2>
                        <p class="text-muted-custom mb-4">Sign in to your account to continue.</p>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                <span>Invalid email or password.</span>
                            </div>
                        <?php endif; ?>

                        <form id="login-form" method="POST" action="../api/auth/login.php">
                            <div class="mb-3">
                                <label class="form-label" for="email">Email address</label>
                                <div class="input-group">
                                    <span class="input-group-text" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                                    <input class="form-control" type="email" id="email" name="email" placeholder="Enter your email" autocomplete="email" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="password">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text" aria-hidden="true"><i class="bi bi-lock"></i></span>
                                    <input class="form-control" type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                                    <button class="btn login-password-toggle" type="button" id="toggle-password" aria-label="Show password" aria-pressed="false">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            <button class="btn btn-primary w-100 login-submit" type="submit" id="login-submit">
                                <span class="login-submit-label"><i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Sign In</span>
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $assetPrefix ?>assets/js/common.js"></script>
    <script>
        const passwordInput = document.querySelector('#password');
        const passwordToggle = document.querySelector('#toggle-password');
        const loginForm = document.querySelector('#login-form');
        const loginSubmit = document.querySelector('#login-submit');

        passwordToggle.addEventListener('click', () => {
            const isVisible = passwordInput.type === 'text';
            passwordInput.type = isVisible ? 'password' : 'text';
            passwordToggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            passwordToggle.setAttribute('aria-pressed', String(!isVisible));
            passwordToggle.querySelector('i').className = isVisible ? 'bi bi-eye' : 'bi bi-eye-slash';
        });

        loginForm.addEventListener('submit', () => {
            loginSubmit.disabled = true;
            loginSubmit.querySelector('.login-submit-label').innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Signing in...';
        });
    </script>

</body>
</html>