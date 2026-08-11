<?php
// ============================================================
// UPC FREELANCE — Télécharger un fichier de contrat
// app/contracts/download-file.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$fileId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT cf.*, c.client_id, c.freelancer_id
    FROM contract_files cf
    JOIN contracts c ON c.id = cf.contract_id
    WHERE cf.id = ?
');
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file || ($user['id'] !== $file['client_id'] && $user['id'] !== $file['freelancer_id'])) {
    http_response_code(403);
    die('Accès refusé.');
}

$fullPath = __DIR__ . '/../../storage/' . $file['file_path'];
if (!file_exists($fullPath)) {
    http_response_code(404);
    die('Fichier introuvable.');
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: no-cache, must-revalidate');
readfile($fullPath);
exit;