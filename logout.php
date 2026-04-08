<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';
logout_user();
header('Location: ' . BASE_URL . '/auth.php');
exit;
