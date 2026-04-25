<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));
if ($role !== 'menejer' && $role !== 'admin' && $role !== 'superadmin') {
    header("Location: /auth/login.php");
    exit;
}
