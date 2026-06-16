<?php
require_once __DIR__ . '/db.php';

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on';
}

function remember_cookie_params(int $expires): array {
    return [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function session_start_secure(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function get_current_user_id(): ?int {
    session_start_secure();
    if (!empty($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    // Tentative de reconnexion via le cookie remember_me
    $token = $_COOKIE['remember_me'] ?? null;
    if ($token && strlen($token) === 64) {
        $hash = hash('sha256', $token);
        $row  = db_fetch('SELECT user_id FROM remember_tokens WHERE token_hash = ?', [$hash]);
        if ($row) {
            $uid = (int)$row['user_id'];
            // Write user_id before regenerating: concurrent requests waiting on the
            // old session lock find it in the preserved old session file (false = keep).
            // session_regenerate_id(false) rotates the session ID (prevents fixation)
            // without destroying the old session that concurrent readers hold a lock on.
            $_SESSION['user_id'] = $uid;
            session_regenerate_id(false);
            // Rotation : nouveau token, invalide l'ancien (sécurité vol de cookie)
            // Chaque device a sa propre ligne → les autres navigateurs ne sont pas affectés
            $newToken = bin2hex(random_bytes(32));
            $newHash  = hash('sha256', $newToken);
            db_query('UPDATE remember_tokens SET token_hash = ? WHERE token_hash = ?', [$newHash, $hash]);
            setcookie('remember_me', $newToken, remember_cookie_params(time() + 10 * 365 * 24 * 3600));
            return $uid;
        }
    }
    return null;
}

function require_auth(): int {
    $uid = get_current_user_id();
    if (!$uid) {
        header('Location: ' . BASE_URL . '/auth.php');
        exit;
    }
    // Afficher les erreurs PHP uniquement pour l'admin (user_id = 1)
    if ($uid === 1) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }
    return $uid;
}

function login_user(int $user_id): void {
    session_start_secure();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
}

function set_remember_token(int $user_id): void {
    $token = bin2hex(random_bytes(32)); // 64 chars hex
    $hash  = hash('sha256', $token);
    db_insert('INSERT INTO remember_tokens (user_id, token_hash) VALUES (?, ?)', [$user_id, $hash]);
    setcookie('remember_me', $token, remember_cookie_params(time() + 10 * 365 * 24 * 3600));
}

function logout_user(): void {
    session_start_secure();
    // Supprimer le token remember_me de la base
    $token = $_COOKIE['remember_me'] ?? null;
    if ($token && strlen($token) === 64) {
        $hash = hash('sha256', $token);
        db_query('DELETE FROM remember_tokens WHERE token_hash = ?', [$hash]);
    }
    // Effacer le cookie
    setcookie('remember_me', '', remember_cookie_params(time() - 3600));
    session_destroy();
}

function find_or_create_user(string $seed_hash): int {
    $user = db_fetch('SELECT id FROM users WHERE seed_hash = ?', [$seed_hash]);
    if ($user) {
        return $user['id'];
    }
    $id = db_insert('INSERT INTO users (seed_hash) VALUES (?)', [$seed_hash]);
    // Créer une page par défaut
    $slug = 'accueil';
    db_insert(
        'INSERT INTO pages (user_id, name, slug, icon, position) VALUES (?, ?, ?, ?, 0)',
        [$id, 'Accueil', $slug, '🏠']
    );
    return $id;
}
