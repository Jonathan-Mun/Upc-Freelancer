<?php
// ============================================================
// UPC FREELANCE — Conversations (alias inbox)
// ../../app/messages/conversations.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireLogin();
redirect('../../app/messages/inbox.php');
