<?php
// ============================================================
// UPC FREELANCE — Serveur sécurisé de fichiers sensibles
// ../../app/verification/serve.php
// Seul l'admin ou le propriétaire peut accéder à ses docs
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$docId = (int)($_GET['id'] ?? 0);
if (!$docId) { http_response_code(400); die('Document invalide.'); }

// Récupérer le document
$pdo  = getDB();
$stmt = $pdo->prepare('SELECT * FROM verification_docs WHERE id = ?');
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) { http_response_code(404); die('Document introuvable.'); }

// Vérification accès : admin ou propriétaire uniquement
$isAdmin = !empty($_SESSION['admin_id']);
$isOwner = isLoggedIn() && currentUser()['id'] === (int)$doc['user_id'];

if (!$isAdmin && !$isOwner) {
    http_response_code(403);
    die('Accès interdit.');
}

// Construire le chemin physique
$filePath = '../../storage/' . $doc['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('Fichier introuvable sur le serveur.');
}

// Détecter le type MIME réel
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Whitelist MIME
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
if (!in_array($mimeType, $allowed)) {
    http_response_code(403);
    die('Type de fichier non autorisé.');
}

// Headers sécurisés
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . basename($doc['file_path']) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Envoyer le fichier
readfile($filePath);
exit;
