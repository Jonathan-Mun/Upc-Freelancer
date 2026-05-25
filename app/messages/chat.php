<?php
// ============================================================
// UPC FREELANCE — Chat (redirect vers contract details)
// /var/www/html/upc_freelance/app/messages/chat.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';

requireLogin();

$contractId = (int)($_GET['contract_id'] ?? 0);
if ($contractId) {
    redirect('/var/www/html/upc_freelance/app/contracts/details.php?id=' . $contractId . '#chat');
}
redirect('/var/www/html/upc_freelance/app/messages/inbox.php');
