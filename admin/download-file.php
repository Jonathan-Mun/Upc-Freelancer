<?php
// ============================================================
// UPC FREELANCE — Admin : Télécharger un fichier de contrat
// /admin/download-file.php
// ============================================================

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/admin_middleware.php';

$admin  = currentAdmin();
$pdo    = getDB();
$fileId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM contract_files WHERE id = ?');
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    die('Fichier introuvable.');
}

$fullPath = __DIR__ . '/../storage/' . $file['file_path'];
if (!file_exists($fullPath)) {
    http_response_code(404);
    die('Fichier introuvable sur le disque.');
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: no-cache, must-revalidate');
readfile($fullPath);
exit;