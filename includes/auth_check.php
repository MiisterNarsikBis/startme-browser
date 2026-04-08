<?php
require_once __DIR__ . '/db.php';

function session_start_secure(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function get_current_user_id(): ?int {
    session_start_secure();
    return $_SESSION['user_id'] ?? null;
}

function require_auth(): int {
    $uid = get_current_user_id();
    if (!$uid) {
        header('Location: ' . BASE_URL . '/auth.php');
        exit;
    }
    return $uid;
}

function login_user(int $user_id): void {
    session_start_secure();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
}

function logout_user(): void {
    session_start_secure();
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
