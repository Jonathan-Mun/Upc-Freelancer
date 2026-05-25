<?php
// ============================================================
// UPC FREELANCE — Soumettre un document de vérification
// ../../app/verification/submit.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user    = currentUser();
$pdo     = getDB();
$userId  = $user['id'];
$role    = $user['role'];
$profile = getExtendedProfile($userId, $role);
$isCompany = $role === 'client' && !empty($profile['company_name']);

// Docs déjà soumis
$stmt = $pdo->prepare('SELECT * FROM verification_docs WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$existingDocs = $stmt->fetchAll();
$docsByType   = [];
foreach ($existingDocs as $d) $docsByType[$d['doc_type']] = $d;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $docType = sanitize($_POST['doc_type'] ?? '');

    // Valider le type selon le rôle
    $allowedTypes = $role === 'freelancer'
        ? ['student_card', 'diploma', 'id_card', 'other']
        : ['id_card', 'diploma', 'other'];

    if (!in_array($docType, $allowedTypes)) {
        flash('error', 'Type de document invalide.');
        redirect('../../app/verification/submit.php');
    }

    if (empty($_FILES['document']['name'])) {
        flash('error', 'Veuillez sélectionner un fichier.');
        redirect('../../app/verification/submit.php');
    }

    // Upload
    $uploaded = uploadFile(
        $_FILES['document'],
        'verification_docs',
        ['jpg', 'jpeg', 'png', 'pdf', 'webp'],
        10
    );

    if (!$uploaded) {
        flash('error', 'Fichier invalide. Formats acceptés : JPG, PNG, PDF, WebP · Max 10 Mo.');
        redirect('../../app/verification/submit.php');
    }

    // Si un doc de même type existe déjà → le remplacer (sauf si approved)
    if (isset($docsByType[$docType])) {
        $existing = $docsByType[$docType];
        if ($existing['status'] === 'approved') {
            flash('error', 'Ce document a déjà été approuvé. Aucune action nécessaire.');
            redirect('../../app/verification/index.php');
        }
        // Supprimer l'ancien fichier
        $oldPath = '../../storage/' . $existing['file_path'];
        if (file_exists($oldPath)) @unlink($oldPath);
        // Mettre à jour
        $pdo->prepare('
            UPDATE verification_docs SET file_path = ?, status = "pending", admin_note = NULL, reviewed_at = NULL
            WHERE id = ?
        ')->execute([$uploaded, $existing['id']]);
    } else {
        // Nouveau document
        $pdo->prepare('
            INSERT INTO verification_docs (user_id, doc_type, file_path, status)
            VALUES (?, ?, ?, "pending")
        ')->execute([$userId, $docType, $uploaded]);
    }

    // Notif admin (via notification utilisateur admin)
    sendNotification($userId, 'doc_submitted', 'Document soumis',
        'Votre document a été soumis et est en cours d\'examen (24-48h ouvrables).',
        '/upc_freelance/app/verification/index.php');

    flash('success', 'Document soumis avec succès ! Notre équipe l\'examinera dans les 24-48h.');
    redirect('../../app/verification/index.php');
}

$pageTitle = 'Soumettre un document — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<div class="mb-8">
    <a href="/upc_freelance/app/verification/index.php" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-3">
        <span class="material-symbols-outlined text-base">arrow_back</span> Vérification du compte
    </a>
    <h1 class="text-2xl font-bold text-primary">Soumettre un document</h1>
    <p class="text-on-surface-variant text-sm mt-1">
        <?= $role === 'freelancer'
            ? 'Vérifiez votre statut d\'étudiant pour obtenir le badge certifié.'
            : 'Vérifiez votre identité pour inspirer confiance aux freelancers.' ?>
    </p>
</div>

<div class="max-w-3xl">
    <?php renderFlash(); ?>

    <?php
    // ── Définir les types de documents selon le rôle ────────
    if ($role === 'freelancer') {
        $docTypes = [
            'student_card' => [
                'label'   => 'Carte étudiante',
                'icon'    => 'school',
                'color'   => 'blue',
                'desc'    => 'Carte officielle délivrée par votre université ou école. Doit être en cours de validité.',
                'tips'    => ['Photo lisible', 'Nom complet visible', 'Date de validité visible', 'Nom de l\'établissement'],
                'recomm'  => true,
            ],
            'diploma' => [
                'label'   => 'Relevé de notes / Diplôme',
                'icon'    => 'workspace_premium',
                'color'   => 'purple',
                'desc'    => 'Relevé de notes officiel ou diplôme délivré par votre établissement.',
                'tips'    => ['Document officiel', 'Nom complet visible', 'Établissement visible'],
                'recomm'  => false,
            ],
            'id_card' => [
                'label'   => 'Carte nationale d\'identité',
                'icon'    => 'badge',
                'color'   => 'green',
                'desc'    => 'CNI, passeport ou titre de séjour en cours de validité.',
                'tips'    => ['Document en cours de validité', 'Photo lisible', 'Nom complet visible'],
                'recomm'  => false,
            ],
            'other' => [
                'label'   => 'Autre justificatif',
                'icon'    => 'description',
                'color'   => 'slate',
                'desc'    => 'Tout autre document officiel prouvant votre statut étudiant.',
                'tips'    => ['Document officiel', 'Lisible et non altéré'],
                'recomm'  => false,
            ],
        ];
    } else {
        // Client
        if ($isCompany) {
            $docTypes = [
                'diploma' => [
                    'label'   => 'Registre de commerce (RCCM)',
                    'icon'    => 'business',
                    'color'   => 'blue',
                    'desc'    => 'Registre du Commerce et du Crédit Mobilier ou tout justificatif officiel d\'enregistrement de votre entreprise.',
                    'tips'    => ['Document officiel', 'Raison sociale visible', 'Numéro d\'enregistrement visible', 'Cachet ou signature officielle'],
                    'recomm'  => true,
                ],
                'other' => [
                    'label'   => 'Autres justificatifs',
                    'icon'    => 'description',
                    'color'   => 'slate',
                    'desc'    => 'Statuts de la société, attestation fiscale ou tout autre document officiel d\'entreprise.',
                    'tips'    => ['Document officiel et lisible'],
                    'recomm'  => false,
                ],
            ];
        } else {
            // Particulier
            $docTypes = [
                'id_card' => [
                    'label'   => 'Carte nationale d\'identité',
                    'icon'    => 'badge',
                    'color'   => 'blue',
                    'desc'    => 'CNI biométrique, passeport ou titre de séjour valide.',
                    'tips'    => ['Document en cours de validité', 'Recto et verso si CNI', 'Photo lisible', 'Nom complet visible'],
                    'recomm'  => true,
                ],
                'other' => [
                    'label'   => 'Passeport / Titre de séjour',
                    'icon'    => 'travel_explore',
                    'color'   => 'slate',
                    'desc'    => 'Passeport en cours de validité ou titre de séjour.',
                    'tips'    => ['En cours de validité', 'Photo et nom lisibles'],
                    'recomm'  => false,
                ],
            ];
        }
    }
    $selectedType = $_POST['doc_type'] ?? array_key_first($docTypes);
    ?>

    <form method="POST" enctype="multipart/form-data" id="verify-form" novalidate>
        <?= csrfField() ?>

        <!-- Étape 1 : Choisir le type -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low mb-5">
            <h2 class="font-semibold text-primary mb-1 flex items-center gap-2">
                <span class="w-6 h-6 bg-primary text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">1</span>
                Choisissez le type de document
            </h2>
            <p class="text-sm text-on-surface-variant mb-5 ml-8">
                <?= $role === 'freelancer' ? 'Préférez la carte étudiante en premier choix.' : ($isCompany ? 'Le RCCM est préféré pour les entreprises.' : 'La CNI est le document recommandé.') ?>
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach ($docTypes as $typeKey => $typeInfo):
                    $existingDoc = $docsByType[$typeKey] ?? null;
                    $docStatus   = $existingDoc['status'] ?? null;
                ?>
                <label class="relative flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all
                    doc-type-label
                    <?= $selectedType === $typeKey ? 'border-secondary bg-secondary/5' : 'border-slate-200 hover:border-secondary/40' ?>"
                    data-type="<?= $typeKey ?>">
                    <input type="radio" name="doc_type" value="<?= $typeKey ?>"
                           <?= $selectedType === $typeKey ? 'checked' : '' ?>
                           class="mt-0.5 text-secondary flex-shrink-0"
                           onchange="selectDocType('<?= $typeKey ?>')"/>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="material-symbols-outlined text-<?= $typeInfo['color'] ?>-600 text-xl"><?= $typeInfo['icon'] ?></span>
                            <p class="text-sm font-semibold text-primary"><?= $typeInfo['label'] ?></p>
                            <?php if ($typeInfo['recomm']): ?>
                            <span class="text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-bold uppercase tracking-wide">Recommandé</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-on-surface-variant leading-relaxed"><?= $typeInfo['desc'] ?></p>
                        <?php if ($docStatus): ?>
                        <div class="mt-2">
                            <?php
                            $sc = ['pending'=>'amber','approved'=>'green','rejected'=>'red'][$docStatus];
                            $sl = ['pending'=>'En attente','approved'=>'Approuvé','rejected'=>'Refusé'][$docStatus];
                            ?>
                            <span class="text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2 py-0.5 rounded-full font-medium">
                                <?= $sl ?>
                            </span>
                            <?php if ($docStatus === 'approved'): ?>
                            <p class="text-xs text-emerald-600 mt-1">✓ Document vérifié</p>
                            <?php elseif ($docStatus === 'rejected' && $existingDoc['admin_note']): ?>
                            <p class="text-xs text-red-500 mt-1">Motif : <?= h($existingDoc['admin_note']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Étape 2 : Conseils & upload -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low mb-5">
            <h2 class="font-semibold text-primary mb-1 flex items-center gap-2">
                <span class="w-6 h-6 bg-primary text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">2</span>
                Importez votre document
            </h2>

            <!-- Conseils dynamiques par type -->
            <?php foreach ($docTypes as $typeKey => $typeInfo): ?>
            <div id="tips-<?= $typeKey ?>" class="tips-panel mt-4 mb-5 ml-8 <?= $selectedType !== $typeKey ? 'hidden' : '' ?>">
                <div class="p-4 bg-blue-50 rounded-xl border border-blue-100">
                    <p class="text-xs font-semibold text-blue-700 mb-2 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">tips_and_updates</span>
                        Conseils pour <?= $typeInfo['label'] ?>
                    </p>
                    <ul class="space-y-1">
                        <?php foreach ($typeInfo['tips'] as $tip): ?>
                        <li class="text-xs text-blue-700 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-blue-400">check_small</span>
                            <?= h($tip) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Zone d'upload -->
            <div class="ml-8">
                <div id="drop-zone"
                     class="border-2 border-dashed border-outline-variant rounded-xl p-10 text-center cursor-pointer hover:border-secondary hover:bg-secondary/5 transition-all"
                     onclick="document.getElementById('file-input').click()"
                     ondragover="event.preventDefault(); this.classList.add('border-secondary','bg-secondary/5')"
                     ondragleave="this.classList.remove('border-secondary','bg-secondary/5')"
                     ondrop="handleDrop(event)">
                    <div id="drop-default">
                        <span class="material-symbols-outlined text-5xl text-slate-300 block mb-3">upload_file</span>
                        <p class="font-semibold text-primary mb-1">Glissez votre fichier ici</p>
                        <p class="text-sm text-on-surface-variant mb-4">ou cliquez pour sélectionner</p>
                        <span class="inline-block bg-surface-container text-secondary text-sm font-button px-5 py-2 rounded-xl hover:bg-surface-container-high transition-colors">
                            Choisir un fichier
                        </span>
                    </div>
                    <div id="drop-preview" class="hidden">
                        <div class="flex items-center justify-center gap-3">
                            <span id="file-icon" class="material-symbols-outlined text-3xl text-secondary">description</span>
                            <div class="text-left">
                                <p id="file-name" class="font-semibold text-primary text-sm"></p>
                                <p id="file-size" class="text-xs text-slate-400"></p>
                            </div>
                            <button type="button" onclick="clearFile(event)"
                                    class="ml-4 text-red-400 hover:text-red-600">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                        <!-- Aperçu image -->
                        <div id="img-preview-wrap" class="mt-4 hidden">
                            <img id="img-preview" src="" alt="Aperçu"
                                 class="max-h-48 mx-auto rounded-xl border border-slate-200 object-contain"/>
                        </div>
                    </div>
                </div>
                <input type="file" id="file-input" name="document" accept=".jpg,.jpeg,.png,.pdf,.webp"
                       class="hidden" onchange="handleFileSelect(this)"/>
                <p class="text-xs text-slate-400 mt-2 text-center">
                    JPG, PNG, WebP, PDF · Max 10 Mo · Votre document est chiffré et sécurisé
                </p>
            </div>
        </div>

        <!-- Étape 3 : Confirmation & submit -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low mb-5">
            <h2 class="font-semibold text-primary mb-4 flex items-center gap-2">
                <span class="w-6 h-6 bg-primary text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">3</span>
                Confirmez la soumission
            </h2>
            <div class="ml-8 space-y-3">
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" required name="confirm_real"
                           class="mt-1 rounded border-outline-variant text-secondary flex-shrink-0"/>
                    <span class="text-sm text-on-surface-variant">
                        Je confirme que ce document est <strong class="text-primary">authentique</strong> et m'appartient.
                    </span>
                </label>
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" required name="confirm_privacy"
                           class="mt-1 rounded border-outline-variant text-secondary flex-shrink-0"/>
                    <span class="text-sm text-on-surface-variant">
                        J'accepte que mon document soit examiné par l'équipe UPC Freelance à des fins de vérification uniquement et sera <strong class="text-primary">supprimé après validation</strong>.
                    </span>
                </label>
            </div>
        </div>

        <!-- Boutons -->
        <div class="flex gap-3">
            <button type="submit" id="submit-btn"
                    class="flex-1 bg-primary text-white font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
                <span class="material-symbols-outlined align-middle mr-1">upload_file</span>
                Soumettre pour vérification
            </button>
            <a href="/upc_freelance/app/verification/index.php"
               class="px-6 py-3.5 rounded-xl border-2 border-slate-200 text-sm font-medium text-on-surface-variant hover:border-slate-300 transition-colors">
                Annuler
            </a>
        </div>
    </form>
</div>

<script>
// Sélection de type de document
function selectDocType(type) {
    document.querySelectorAll('.doc-type-label').forEach(el => {
        el.classList.remove('border-secondary','bg-secondary/5');
        el.classList.add('border-slate-200');
    });
    const selected = document.querySelector(`.doc-type-label[data-type="${type}"]`);
    if (selected) {
        selected.classList.add('border-secondary','bg-secondary/5');
        selected.classList.remove('border-slate-200');
    }
    document.querySelectorAll('.tips-panel').forEach(p => p.classList.add('hidden'));
    const tips = document.getElementById(`tips-${type}`);
    if (tips) tips.classList.remove('hidden');
}

// Gestion fichier
function handleFileSelect(input) {
    if (input.files && input.files[0]) showFile(input.files[0]);
}
function handleDrop(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.remove('border-secondary','bg-secondary/5');
    const file = e.dataTransfer.files[0];
    if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('file-input').files = dt.files;
        showFile(file);
    }
}
function showFile(file) {
    const maxMb = 10;
    if (file.size > maxMb * 1024 * 1024) {
        alert(`Fichier trop lourd. Maximum : ${maxMb} Mo`);
        return;
    }
    document.getElementById('drop-default').classList.add('hidden');
    document.getElementById('drop-preview').classList.remove('hidden');
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent = (file.size / 1024 / 1024).toFixed(2) + ' Mo';

    // Icône selon type
    const isPdf = file.type === 'application/pdf';
    document.getElementById('file-icon').textContent = isPdf ? 'picture_as_pdf' : 'image';

    // Aperçu image
    if (!isPdf) {
        const reader = new FileReader();
        reader.onload = e => {
            const wrap = document.getElementById('img-preview-wrap');
            document.getElementById('img-preview').src = e.target.result;
            wrap.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
}
function clearFile(e) {
    e.stopPropagation();
    document.getElementById('file-input').value = '';
    document.getElementById('drop-default').classList.remove('hidden');
    document.getElementById('drop-preview').classList.add('hidden');
    document.getElementById('img-preview-wrap').classList.add('hidden');
}

// Validation avant envoi
document.getElementById('verify-form').addEventListener('submit', function(e) {
    const file = document.getElementById('file-input').files[0];
    if (!file) {
        e.preventDefault();
        alert('Veuillez sélectionner un document à soumettre.');
        return;
    }
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined align-middle mr-1 animate-spin">autorenew</span> Envoi en cours...';
});
</script>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>
