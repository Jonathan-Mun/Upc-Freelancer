<?php
// ============================================================
// UPC FREELANCE — Handler retrait (virement + Mobile Money)
// app/wallet/withdraw.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Méthode non autorisée.']);
    exit;
}

$user   = currentUser();
$pdo    = getDB();
$amount = (float)($_POST['amount'] ?? 0);
$method = trim($_POST['method'] ?? '');

// ── Validation commune ───────────────────────────────────────
if ($amount < 20) {
    echo json_encode(['success'=>false,'message'=>'Minimum : $20 USD.']); exit;
}
if (!in_array($method, ['bank','mobile_money'])) {
    echo json_encode(['success'=>false,'message'=>'Mode de retrait invalide.']); exit;
}

// ── Validation par méthode ───────────────────────────────────
if ($method === 'bank') {
    $holder = trim($_POST['account_holder'] ?? '');
    $bank   = trim($_POST['bank_name'] ?? '');
    $iban   = strtoupper(preg_replace('/\s+/','',$_POST['iban'] ?? ''));
    if (empty($holder)) { echo json_encode(['success'=>false,'message'=>'Titulaire requis.']); exit; }
    if (empty($bank))   { echo json_encode(['success'=>false,'message'=>'Banque requise.']); exit; }
    if (strlen($iban) < 14) { echo json_encode(['success'=>false,'message'=>'IBAN invalide.']); exit; }
    $description = sprintf('Retrait bancaire → %s (%s) IBAN ****%s | Commission 5%% : %s USD',
        $holder, $bank, substr($iban,-4),
        number_format($amount*0.05,2,'.',','));
}

if ($method === 'mobile_money') {
    $operator = trim($_POST['operator'] ?? '');
    $phone    = preg_replace('/\D/','',$_POST['phone'] ?? '');
    $opLabels = ['orange'=>'Orange Money','mtn'=>'MTN Mobile Money','airtel'=>'Airtel Money'];
    if (!isset($opLabels[$operator])) { echo json_encode(['success'=>false,'message'=>'Opérateur invalide.']); exit; }
    if (strlen($phone) < 8)          { echo json_encode(['success'=>false,'message'=>'Numéro invalide.']); exit; }
    $description = sprintf('Retrait %s → +225 %s | Commission 5%% : %s USD',
        $opLabels[$operator], $phone,
        number_format($amount*0.05,2,'.',','));
}

// ── Vérification solde ───────────────────────────────────────
$wallet = getUserWallet($user['id']);
if ((float)$wallet['balance'] < $amount) {
    echo json_encode([
        'success' => false,
        'message' => 'Solde insuffisant. Disponible : ' . number_format((float)$wallet['balance'],0,',',' ') . ' USD.',
    ]); exit;
}

// ── Calcul commission ────────────────────────────────────────
define('COMMISSION_RATE', 0.05);
$commission = round($amount * COMMISSION_RATE, 2);
$net_amount = round($amount - $commission, 2);

// ── Transaction ──────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    $balance_before = (float)$wallet['balance'];
    $balance_after  = round($balance_before - $amount, 2);

    $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE user_id = ?')
        ->execute([$amount, $user['id']]);

    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

    $pdo->prepare('
        INSERT INTO transactions (uuid,user_id,type,amount,balance_before,balance_after,description,reference,status)
        VALUES (?,?,"withdrawal",?,?,?,?,?,"completed")
    ')->execute([
        $uuid, $user['id'], $amount,
        $balance_before, $balance_after,
        $description,
        'WD-' . strtoupper(substr($uuid,0,8)),
    ]);

    $pdo->commit();

    if (function_exists('sendNotification')) {
        sendNotification($user['id'],'withdrawal','Retrait enregistré',
            'Retrait de ' . number_format($amount,2,'.',',') . ' USD (net : ' .
            number_format($net_amount,2,'.',',') . ' USD) enregistré.',
            '/upc_freelance/app/wallet/index.php');
    }

    echo json_encode([
        'success'     => true,
        'amount'      => $amount,
        'commission'  => $commission,
        'net_amount'  => $net_amount,
        'new_balance' => $balance_after,
    ]);

} catch (\Throwable $e) {
    $pdo->rollBack();
    error_log('[withdraw] ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Erreur serveur. Réessayez.']);
}
exit;