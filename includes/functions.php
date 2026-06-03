<?php
// ============================================================
// UPC FREELANCE — Fonctions utilitaires
// includes/functions.php
// ============================================================

require_once __DIR__ . '/db.php';

// ─── Sécurité & sanitisation ─────────────────────────────────
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize(string $val): string {
    return trim(strip_tags($val));
}

function generateUUID(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function generateToken(int $length = 64): string {
    return bin2hex(random_bytes($length / 2));
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(32);
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('CSRF token invalide.');
    }
}

// ─── Formatage ────────────────────────────────────────────────
function money(float $amount, string $currency = 'USD'): string {
    return number_format($amount, 0, ',', ' ') . ' ' . $currency;
}

function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0) return "il y a {$diff->y} an" . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return "il y a {$diff->m} mois";
    if ($diff->d > 0) return "il y a {$diff->d} jour" . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return "il y a {$diff->h}h";
    if ($diff->i > 0) return "il y a {$diff->i} min";
    return "à l'instant";
}

function formatDate(string $date, string $format = 'd/m/Y'): string {
    return (new DateTime($date))->format($format);
}

function truncate(string $text, int $length = 120): string {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '…';
}

// ─── Upload fichiers ──────────────────────────────────────────
function uploadFile(array $file, string $folder, array $allowed = ['jpg','jpeg','png','pdf'], int $maxMb = 5): string|false {
    $uploadDir = '../storage/' . $folder . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return false;
    if ($file['size'] > $maxMb * 1024 * 1024) return false;

    // Vérification MIME réelle
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'application/pdf'];
    if (!in_array($mime, $allowedMimes)) return false;

    $filename = generateToken(32) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) return false;

    return $folder . '/' . $filename;
}

// ─── Flash messages ───────────────────────────────────────────
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlash(): array {
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}

function renderFlash(): void {
    foreach (getFlash() as $f) {
        $color = match($f['type']) {
            'success' => 'green',
            'error'   => 'red',
            'warning' => 'yellow',
            default   => 'blue',
        };
        echo '<div class="mb-4 p-4 rounded-lg bg-' . $color . '-50 border border-' . $color . '-200 text-' . $color . '-800 text-body-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-' . $color . '-600">
                ' . ($f['type'] === 'success' ? 'check_circle' : ($f['type'] === 'error' ? 'error' : 'info')) . '
            </span>
            ' . h($f['message']) . '
        </div>';
    }
}

// ─── Pagination ───────────────────────────────────────────────
function paginate(int $total, int $perPage, int $currentPage, string $baseUrl): array {
    $totalPages = (int) ceil($total / $perPage);
    $offset     = ($currentPage - 1) * $perPage;
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
        'base_url'    => $baseUrl,
    ];
}

// ─── Wallet ───────────────────────────────────────────────────
function getUserWallet(int $userId): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT * FROM wallets WHERE user_id = ?');
    $stmt->execute([$userId]);
    $wallet = $stmt->fetch();
    if (!$wallet) {
        $pdo->prepare('INSERT INTO wallets (user_id, balance, locked) VALUES (?, 0.00, 0.00)')
            ->execute([$userId]);
        return ['balance' => 0.00, 'locked' => 0.00];
    }
    return $wallet;
}

// Retourne le profil étendu (freelancer_profiles ou client_profiles)
function getExtendedProfile(int $userId, string $role): ?array {
    $table = $role === 'freelancer' ? 'freelancer_profiles' : 'client_profiles';
    $stmt  = getDB()->prepare("SELECT * FROM $table WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function recordTransaction(int $userId, string $type, float $amount, ?int $contractId, string $desc): void {
    $pdo    = getDB();
    $wallet = getUserWallet($userId);
    $before = (float) $wallet['balance'];
    $after  = in_array($type, ['deposit','unlock','refund']) ? $before + $amount : $before - $amount;

    $pdo->prepare('
        INSERT INTO transactions (uuid, user_id, contract_id, type, amount, balance_before, balance_after, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([generateUUID(), $userId, $contractId, $type, $amount, $before, $after, $desc]);

    $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?')->execute([$after, $userId]);
}

// ─── Notifications ────────────────────────────────────────────
function sendNotification(int $userId, string $type, string $title, string $body = '', string $link = ''): void {
    $pdo = getDB();
    $pdo->prepare('
        INSERT INTO notifications (user_id, type, title, body, link)
        VALUES (?, ?, ?, ?, ?)
    ')->execute([$userId, $type, $title, $body, $link]);
}

function countUnreadNotifications(int $userId): int {
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function countUnreadMessages(int $userId): int {
    $db = getDB();
    // Messages contrat non lus
    $s1 = $db->prepare('
        SELECT COUNT(*) FROM messages m
        JOIN contracts c ON c.id = m.contract_id
        WHERE (c.client_id = ? OR c.freelancer_id = ?)
          AND m.sender_id != ?
          AND m.is_read = 0
    ');
    $s1->execute([$userId, $userId, $userId]);
    $contractUnread = (int)$s1->fetchColumn();

    // Messages directs non lus (table peut ne pas exister encore)
    $dmUnread = 0;
    try {
        $s2 = $db->prepare('SELECT COUNT(*) FROM direct_messages WHERE receiver_id = ? AND is_read = 0');
        $s2->execute([$userId]);
        $dmUnread = (int)$s2->fetchColumn();
    } catch (\Throwable $e) {}

    return $contractUnread + $dmUnread;
}

// ─── Redirection ──────────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function redirectWithFlash(string $url, string $type, string $message): never {
    flash($type, $message);
    redirect($url);
}

// ─── Stars rating ─────────────────────────────────────────────
function renderStars(float $rating): string {
    $html = '<span class="flex gap-0.5">';
    for ($i = 1; $i <= 5; $i++) {
        $fill = $i <= round($rating) ? '1' : '0';
        $html .= '<span class="material-symbols-outlined text-amber-400 text-sm" style="font-variation-settings:\'FILL\' ' . $fill . '">'
               . 'star</span>';
    }
    $html .= '</span>';
    return $html;
}
// ─── Avatar + badge vérifié ───────────────────────────────────
/**
 * Affiche l'avatar d'un utilisateur (photo ou initiale) avec
 * optionnellement le badge "vérifié" en superposition.
 *
 * @param string|null $avatar      Chemin relatif stocké en BDD (ex: avatars/xxx.jpg)
 * @param string      $firstName   Prénom (pour l'initiale de fallback)
 * @param string      $lastName    Nom
 * @param bool        $isVerified  Afficher le badge vérifié ?
 * @param string      $size        Classes Tailwind pour width/height (ex: "w-10 h-10")
 * @param string      $shape       "rounded-full" ou "rounded-xl"
 * @param string      $extra       Classes CSS supplémentaires sur le wrapper
 */
function renderAvatar(
    ?string $avatar,
    string  $firstName,
    string  $lastName   = '',
    bool    $isVerified = false,
    string  $size       = 'w-10 h-10',
    string  $shape      = 'rounded-full',
    string  $extra      = ''
): string {
    $initiale = mb_strtoupper(mb_substr($firstName, 0, 1));
    $BASE     = '/upc_freelance';

    if ($avatar) {
        $img = '<img src="' . $BASE . '/storage/' . h($avatar) . '" alt="Avatar"
                     class="' . $size . ' ' . $shape . ' object-cover"/>';
    } else {
        $img = '<div class="' . $size . ' ' . $shape . ' bg-primary/10 flex items-center justify-center font-bold text-primary text-sm">'
             . $initiale
             . '</div>';
    }

    $badge = '';
    if ($isVerified) {
        $badge = '<span class="absolute -bottom-0.5 -right-0.5 bg-white rounded-full leading-none"
                        title="Compte vérifié">'
               . '<span class="material-symbols-outlined text-secondary" '
               . 'style="font-size:14px;font-variation-settings:\'FILL\' 1">verified</span>'
               . '</span>';
    }

    return '<div class="relative inline-flex flex-shrink-0 ' . $extra . '">'
         . $img
         . $badge
         . '</div>';
}