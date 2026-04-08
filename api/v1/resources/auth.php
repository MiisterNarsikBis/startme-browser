<?php
/* POST /api/v1/auth/login    { words: [...12] }
   POST /api/v1/auth/logout
   POST /api/v1/auth/generate */

require_once dirname(__DIR__, 3) . '/includes/bip39_fr.php';

if ($method !== 'POST') json_error('Méthode non autorisée.', 405);

switch ($sub) {
    case 'generate':
        json_response(['words' => generateSeed(12)]);

    case 'login':
        // Déléguer à l'API existante pour ne pas dupliquer le rate limiting
        $d     = request_json();
        $words = $d['words'] ?? [];
        if (!is_array($words) || count($words) !== 12) json_error('Il faut exactement 12 mots.');
        $list    = getBip39Fr();
        $cleaned = [];
        foreach ($words as $w) {
            $w = mb_strtolower(trim($w), 'UTF-8');
            if (!in_array($w, $list, true)) json_error("Mot invalide : « $w »");
            $cleaned[] = $w;
        }
        $hash = seedToHash($cleaned);
        $uid  = find_or_create_user($hash);
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
