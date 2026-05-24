<?php
// ============================================================
// UPC FREELANCE — Conversations (alias inbox)
// /var/www/html/upc_freelance/app/messages/conversations.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';

requireLogin();
redirect('/var/www/html/upc_freelance/app/messages/inbox.php');
