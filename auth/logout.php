<?php
// ============================================================
// UPC FREELANCE — Logout
// /var/www/html/upc_freelance/auth/logout.php
// ============================================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

logoutUser();
flash('success', 'Vous avez été déconnecté avec succès.');
redirect('../public/login.php');
