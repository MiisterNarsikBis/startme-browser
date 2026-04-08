<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/bip39_fr.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];

// POST /api/auth.php?action=login   { words: [...12] }
// POST /api/auth.php?action=generate
// POST /api/auth.php?action=logout

$action = $_GET['action'] ?? '';

// ----------------------------------------------------------------
// Rate limiting
// ----------------------------------------------------------------
define('RL_MAX_ATTEMPTS', 10);   // tentatives avant blocage
define('RL_BLOCK_MINUTES', 15);  // durée du blocage en minutes

function get_client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return '0.0.0.0';
}

function check_rate_limit(): void {
    $ip  = get_client_ip();
    $row = db_fetch('SELECT * FROM login_attempts WHERE ip = ?', [$ip]);

    if ($row) {
        // Bloqué ?
        if ($row['blocked_until'] && new DateTime() < new DateTime($row['blocked_until'])) {
            $wait = (new DateTime($row['blocked_until']))->diff(new DateTime())->i + 1;
            json_error("Trop de tentatives. Réessayez dans {$wait} min.", 429);
        }
        // Réinitialiser si le blocage est expiré
        if ($row['blocked_until'] && new DateTime() >= new DateTime($row['blocked_until'])) {
            db_query('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
        }
    }
}

function record_failed_attempt(): void {
    $ip  = get_client_ip();
    $row = db_fetch('SELECT * FROM login_attempts WHERE ip = ?', [$ip]);

    if (!$row) {
        db_query('INSERT INTO login_attempts (ip, attempts) VALUES (?, 1)', [$ip]);
        return;
    }

    $attempts = $row['attempts'] + 1;
    if ($attempts >= RL_MAX_ATTEMPTS) {
        $blockedUntil = (new DateTime())->modify('+' . RL_BLOCK_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        db_query('UPDATE login_attempts SET attempts = ?, blocked_until = ? WHERE ip = ?',
            [$attempts, $blockedUntil, $ip]);
    } else {
        db_query('UPDATE login_attempts SET attempts = ? WHERE ip = ?', [$attempts, $ip]);
    }
}

function clear_rate_limit(): void {
    db_query('DELETE FROM login_attempts WHERE ip = ?', [get_client_ip()]);
}

// ----------------------------------------------------------------

if ($method === 'POST' && $action === 'generate') {
    $words = generateSeed(12);
    json_response(['words' => $words]);
}

if ($method === 'POST' && $action === 'login') {
    check_rate_limit();

    $data  = request_json();
    $words = $data['words'] ?? [];

    if (!is_array($words) || count($words) !== 12) {
        json_error('Il faut exactement 12 mots.');
    }

    // Nettoyer et valider
    $list    = getBip39Fr();
    $cleaned = [];
    foreach ($words as $w) {
        $w = mb_strtolower(trim($w), 'UTF-8');
        if (!in_array($w, $list, true)) {
            record_failed_attempt();
            json_error("Mot invalide : « $w »");
        }
        $cleaned[] = $w;
    }

    $hash = seedToHash($cleaned);
    $user = db_fetch('SELECT id FROM users WHERE seed_hash = ?', [$hash]);

    if (!$user) {
        // Phrase inconnue → on crée quand même (comportement actuel)
        // mais on ne comptabilise pas comme échec
        $uid = find_or_create_user($hash);
    } else {
        $uid = (int)$user['id'];
    }

    clear_rate_limit();
    login_user($uid);
    set_remember_token($uid);

    $pages = get_user_pages($uid);
    $first = $pages[0]['slug'] ?? 'accueil';

    json_response(['ok' => true, 'redirect' => BASE_URL . '/p/' . $first]);
}

if ($method === 'POST' && $action === 'logout') {
    logout_user();
    json_response(['ok' => true, 'redirect' => BASE_URL . '/auth.php']);
}

json_error('Action inconnue.', 404);
