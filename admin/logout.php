<?php
// ============================================================
// UPC FREELANCE — Admin Logout
// /var/www/html/upc_freelance/admin/logout.php
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['admin_id'], $_SESSION['admin_name']);
session_destroy();
header('Location: /upc_freelance/admin/login.php');
exit;
