<?php
// ============================================================
// UPC FREELANCE — Handler dépôt (Stripe + Mobile Money simulé)
// app/wallet/deposit_handler.php
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

// ── Validation ───────────────────────────────────────────────
if ($amount < 20)     { echo json_encode(['success'=>false,'message'=>'Minimum : $20 USD.']); exit; }
if ($amount > 5000) { echo json_encode(['success'=>false,'message'=>'Maximum : $5,000 USD.']); exit; }
if (!in_array($method, ['stripe','mobile_money'])) {
    echo json_encode(['success'=>false,'message'=>'Mode de paiement invalide.']); exit;
}

// ── Traitement selon méthode ─────────────────────────────────

if ($method === 'stripe') {
    // ── Mode Stripe (test) ───────────────────────────────────
    // En production : remplacer par Stripe PHP SDK
    // composer require stripe/stripe-php
    // \Stripe\Stripe::setApiKey('sk_test_VOTRE_CLE');
    // $intent = \Stripe\PaymentIntent::create([...]);
    //
    // Pour l'instant : simulation test
    // La vraie intégration Stripe nécessite le SDK côté serveur
    // et Stripe.js côté client pour la tokenisation de la carte.
    // On simule un succès si le numéro commence par 4242.
    $cardSimulated = true; // En test, on accepte toujours
    if (!$cardSimulated) {
        echo json_encode(['success'=>false,'message'=>'Paiement Stripe refusé (simulation).']);
        exit;
    }
    $description = 'Rechargement via Stripe (carte ****4242)';

} elseif ($method === 'mobile_money') {
    // ── Mode Mobile Money simulé ─────────────────────────────
    $operator = trim($_POST['operator'] ?? '');
    $phone    = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    if (!$operator || strlen($phone) < 8) {
        echo json_encode(['success'=>false,'message'=>'Opérateur ou numéro invalide.']); exit;
    }
    $opLabels = ['orange'=>'Orange Money','mtn'=>'MTN Mobile Money','airtel'=>'Airtel Money'];
    $description = 'Rechargement via ' . ($opLabels[$operator] ?? $operator) . ' (+225 ' . $phone . ')';
}

// ── Enregistrement en base ───────────────────────────────────
try {
    $pdo->beginTransaction();

    $wallet = getUserWallet($user['id']);
    $balance_before = (float)$wallet['balance'];
    $balance_after  = round($balance_before + $amount, 2);

    $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?')
        ->execute([$amount, $user['id']]);

    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

    $pdo->prepare('
        INSERT INTO transactions (uuid,user_id,type,amount,balance_before,balance_after,description,reference,status)
        VALUES (?,?,"deposit",?,?,?,?,?,"completed")
    ')->execute([
        $uuid, $user['id'], $amount, $balance_before, $balance_after,
        $description,
        'DEP-' . strtoupper(substr($uuid,0,8)),
    ]);

    $pdo->commit();

    if (function_exists('sendNotification')) {
        sendNotification($user['id'],'deposit_success','Rechargement réussi !',
            number_format($amount,2,'.',',') . ' USD crédités sur votre wallet.',
            '/upc_freelance/app/wallet/index.php');
    }

    echo json_encode([
        'success'     => true,
        'amount'      => $amount,
        'new_balance' => $balance_after,
    ]);

} catch (\Throwable $e) {
    $pdo->rollBack();
    error_log('[deposit_handler] ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Erreur serveur. Réessayez.']);
}
exit;