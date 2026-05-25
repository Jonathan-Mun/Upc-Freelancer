<?php
// ============================================================
// UPC FREELANCE — Postuler à un projet
// ../../app/postulations/apply.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireRole('freelancer');
allowMethods('POST');
verifyCsrf();

$user      = currentUser();
$pdo       = getDB();
$projectId = (int)($_POST['project_id'] ?? 0);

if (!$projectId) {
    flash('error', 'Projet invalide.');
    redirect('../../app/projects/list.php');
}

// Vérifier que le projet existe et est ouvert
$stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ? AND status = "open"');
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) {
    flash('error', 'Ce projet n\'est plus disponible.');
    redirect('../../app/projects/list.php');
}

// Vérifier pas déjà postulé
$stmt = $pdo->prepare('SELECT id FROM postulations WHERE project_id = ? AND freelancer_id = ?');
$stmt->execute([$projectId, $user['id']]);
if ($stmt->fetch()) {
    flash('error', 'Vous avez déjà postulé à ce projet.');
    redirect('../../app/projects/details.php?id=' . $projectId);
}

$coverLetter   = sanitize($_POST['cover_letter']   ?? '');
$proposedPrice = (float)($_POST['proposed_price']  ?? 0);
$proposedDays  = (int)($_POST['proposed_days']     ?? 0);

if (empty($coverLetter) || $proposedPrice <= 0) {
    flash('error', 'Veuillez remplir tous les champs obligatoires.');
    redirect('../../app/projects/details.php?id=' . $projectId);
}

$pdo->prepare('
    INSERT INTO postulations (project_id, freelancer_id, cover_letter, proposed_price, proposed_days)
    VALUES (?, ?, ?, ?, ?)
')->execute([$projectId, $user['id'], $coverLetter, $proposedPrice, $proposedDays ?: null]);

// Notifier le client
sendNotification(
    $project['client_id'],
    'new_application',
    'Nouvelle candidature reçue',
    $user['first_name'] . ' ' . $user['last_name'] . ' a postulé à votre projet "' . $project['title'] . '".',
    '/upc_freelance/app/postulations/received.php?project_id=' . $projectId
);

flash('success', 'Candidature envoyée avec succès !');
redirect('../../app/postulations/my-applications.php');
