<?php
/* POST /api/v1/auth/login    { words: [...12] }
   POST /api/v1/auth/logout
   POST /api/v1/auth/generate */

require_once dirname(__DIR__, 3) . '/includes/bip39_fr.php';

if ($method !== 'POST') json_error('Méthode non autorisée.', 405);

// --- Rate limiting ---
define('AUTH_RL_MAX',     10);
define('AUTH_RL_MINUTES', 15);

function auth_get_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
    }
    return '0.0.0.0';
}

function auth_check_rl(): void {
    $ip  = auth_get_ip();
    $row = db_fetch('SELECT * FROM login_attempts WHERE ip=?', [$ip]);
    if (!$row) return;
    if ($row['blocked_until'] && new DateTime() < new DateTime($row['blocked_until'])) {
        $wait = (new DateTime($row['blocked_until']))->diff(new DateTime())->i + 1;
        json_error("Trop de tentatives. Réessayez dans {$wait} min.", 429);
    }
    if ($row['blocked_until'] && new DateTime() >= new DateTime($row['blocked_until'])) {
        db_query('DELETE FROM login_attempts WHERE ip=?', [$ip]);
    }
}

function auth_record_fail(): void {
    $ip  = auth_get_ip();
    $row = db_fetch('SELECT * FROM login_attempts WHERE ip=?', [$ip]);
    if (!$row) { db_query('INSERT INTO login_attempts (ip, attempts) VALUES (?,1)', [$ip]); return; }
    $attempts = $row['attempts'] + 1;
    if ($attempts >= AUTH_RL_MAX) {
        $until = (new DateTime())->modify('+' . AUTH_RL_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        db_query('UPDATE login_attempts SET attempts=?, blocked_until=? WHERE ip=?', [$attempts, $until, $ip]);
    } else {
        db_query('UPDATE login_attempts SET attempts=? WHERE ip=?', [$attempts, $ip]);
    }
}

function auth_clear_rl(): void {
    db_query('DELETE FROM login_attempts WHERE ip=?', [auth_get_ip()]);
}
// ---

switch ($sub) {
    case 'generate':
        json_response(['words' => generateSeed(12)]);

    case 'login':
        auth_check_rl();
        $d     = request_json();
        $words = $d['words'] ?? [];
        if (!is_array($words) || count($words) !== 12) json_error('Il faut exactement 12 mots.');
        $list    = getBip39Fr();
        $cleaned = [];
        foreach ($words as $w) {
            $w = mb_strtolower(trim($w), 'UTF-8');
            if (!in_array($w, $list, true)) {
                auth_record_fail();
                json_error("Mot invalide : « $w »");
            }
            $cleaned[] = $w;
        }
        $hash = seedToHash($cleaned);
        $uid  = find_or_create_user($hash);
        auth_clear_rl();
        login_user($uid);
        set_remember_token($uid);
        $pages = get_user_pages($uid);
        json_response(['ok' => true, 'redirect' => BASE_URL . '/p/' . ($pages[0]['slug'] ?? 'accueil')]);

    case 'logout':
        logout_user();
        json_response(['ok' => true]);

    default:
        json_error('Action inconnue.', 404);
}
