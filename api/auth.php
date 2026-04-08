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

if ($method === 'POST' && $action === 'generate') {
    $words = generateSeed(12);
    json_response(['words' => $words]);
}

if ($method === 'POST' && $action === 'login') {
    $data = request_json();
    $words = $data['words'] ?? [];

    if (!is_array($words) || count($words) !== 12) {
        json_error('Il faut exactement 12 mots.');
    }

    // Nettoyer et valider
    $list = getBip39Fr();
    $cleaned = [];
    foreach ($words as $w) {
        $w = mb_strtolower(trim($w), 'UTF-8');
        if (!in_array($w, $list, true)) {
            json_error("Mot invalide : « $w »");
        }
        $cleaned[] = $w;
    }

    $hash = seedToHash($cleaned);
    $uid  = find_or_create_user($hash);
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
