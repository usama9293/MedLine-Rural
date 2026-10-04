<?php

require_once __DIR__ . '/../../config/auth.php';

logoutUser();

header('Location: ../../frontend/login.php');
exit;