<?php
// ============================================================
// UPC FREELANCE — Chat (redirect vers contract details)
// ../../app/messages/chat.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireLogin();

$contractId = (int)($_GET['contract_id'] ?? 0);
if ($contractId) {
    redirect('../../app/contracts/details.php?id=' . $contractId . '#chat');
}
redirect('../../app/messages/inbox.php');
