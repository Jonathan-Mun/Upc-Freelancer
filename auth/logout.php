<?php
// ============================================================
// UPC FREELANCE — Logout
// /var/www/html/upc_freelance/auth/logout.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';

logoutUser();
flash('success', 'Vous avez été déconnecté avec succès.');
redirect('/var/www/html/upc_freelance/public/login.php');
