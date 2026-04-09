<?php
/* GET /api/v1/github?username=X             — Proxy contributions GitHub
   GET /api/v1/github?username=X&platform=gitlab — Proxy contributions GitLab
   Retourne { contributions: { "2024-01-15": 3, ... }, total: 123 }
   Cache fichier de 1h pour éviter de marteler les APIs. */

if ($method !== 'GET') json_error('Méthode non autorisée.', 405);

$platform = $_GET['platform'] ?? 'github';
if ($platform === 'gitlab') {
    // --- GITLAB ---
    $username = trim($_GET['username'] ?? '');
    if (!$username || !preg_match('/^[a-zA-Z0-9_.\-]{1,255}$/', $username)) {
        json_error('Nom d\'utilisateur invalide.');
    }

    $cache_dir  = dirname(__DIR__, 3) . '/cache/gitlab/';
    $cache_file = $cache_dir . md5($username) . '.json';
    if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 3600) {
        header('X-Cache: HIT');
        echo file_get_contents($cache_file);
        exit;
    }

    $ctx = stream_context_create(['http' => [
        'timeout'    => 10,
        'user_agent' => 'StartMe/1.0 (homepage perso)',
    ]]);

    // calendar.json : { "2024-01-15": 3, ... } — inclut les contributions privées du profil public
    $cal = @file_get_contents(
        'https://gitlab.com/users/' . urlencode($username) . '/calendar.json',
        false, $ctx
    );
    if ($cal === false) json_error('Impossible de contacter GitLab.');

    $data  = json_decode($cal, true) ?? [];
    $total = array_sum($data);

    // Profil utilisateur
    $profileRaw = @file_get_contents(
        'https://gitlab.com/api/v4/users?username=' . urlencode($username),
        false, $ctx
    );
    $users   = json_decode($profileRaw ?: '[]', true) ?? [];
    $profile = $users[0] ?? [];

    $result = json_encode([
        'username'      => $username,
        'contributions' => $data,
        'total'         => $total,
        'avatar'        => $profile['avatar_url'] ?? '',
        'platform'      => 'gitlab',
        'cached_at'     => date('c'),
    ], JSON_UNESCAPED_UNICODE);

    file_put_contents($cache_file, $result);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Cache: MISS');
    echo $result;
    exit;
}

$username = trim($_GET['username'] ?? '');
if (!$username || !preg_match('/^[a-zA-Z0-9\-]{1,39}$/', $username)) {
    json_error('Nom d\'utilisateur invalide.');
}

// Cache 2h par username
$cache_dir  = dirname(__DIR__, 3) . '/cache/github/';
$cache_file = $cache_dir . md5($username) . '.json';
if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 3600) {
    header('X-Cache: HIT');
    echo file_get_contents($cache_file);
    exit;
}

// Fetch la page de contributions GitHub (inclut les privées si l'utilisateur les a activées)
$url = 'https://github.com/users/' . urlencode($username) . '/contributions';
$ctx = stream_context_create(['http' => [
    'timeout'    => 10,
    'user_agent' => 'Mozilla/5.0 (compatible; StartMe/1.0)',
    'header'     => "Accept: text/html,application/xhtml+xml\r\nAccept-Language: en-US,en;q=0.9\r\n",
    'follow_location' => 1,
]]);
$html = @file_get_contents($url, false, $ctx);

if ($html === false) {
    json_error('Impossible de contacter GitHub. Réessayez plus tard.');
}

$contributions = [];
$total         = 0;

// GitHub (2024+) structure :
//   <td id="contribution-day-component-X-Y" data-date="YYYY-MM-DD" data-level="0-4" ...>
//   <tool-tip for="contribution-day-component-X-Y">N contributions on ...</tool-tip>
//
// On croise id→date (depuis les <td>) avec id→count (depuis les <tool-tip>).

// Étape 1 : construire la map id → date
preg_match_all(
    '/id="(contribution-day-component-[\d-]+)"[^>]*data-date="(\d{4}-\d{2}-\d{2})"/',
    $html, $tdMatch
);
// Essai ordre inversé
if (empty($tdMatch[1])) {
    preg_match_all(
        '/data-date="(\d{4}-\d{2}-\d{2})"[^>]*id="(contribution-day-component-[\d-]+)"/',
        $html, $rev
    );
    $tdMatch[1] = $rev[2] ?? [];
    $tdMatch[2] = $rev[1] ?? [];
}
$idToDate = [];
foreach ($tdMatch[1] as $i => $id) {
    $idToDate[$id] = $tdMatch[2][$i];
}

// Étape 2 : extraire count depuis les tooltips  "N contribution[s] on ..."
preg_match_all(
    '/for="(contribution-day-component-[\d-]+)"[^>]*>\s*(\d+)\s+contribution/',
    $html, $tipMatch
);
foreach ($tipMatch[1] as $i => $id) {
    if (isset($idToDate[$id])) {
        $count = (int)$tipMatch[2][$i];
        $contributions[$idToDate[$id]] = $count;
        $total += $count;
    }
}

// Fallback si aucun tooltip trouvé : utiliser data-level (0-4) comme approximation
if (empty($contributions) && !empty($idToDate)) {
    preg_match_all(
        '/id="(contribution-day-component-[\d-]+)"[^>]*data-level="(\d)"/',
        $html, $lvlMatch
    );
    $levelToCount = [0, 1, 3, 6, 10];
    foreach ($lvlMatch[1] as $i => $id) {
        if (isset($idToDate[$id])) {
            $count = $levelToCount[(int)$lvlMatch[2][$i]] ?? 0;
            $contributions[$idToDate[$id]] = $count;
            $total += $count;
        }
    }
}

$result = json_encode([
    'username'      => $username,
    'contributions' => $contributions,
    'total'         => $total,
    'cached_at'     => date('c'),
], JSON_UNESCAPED_UNICODE);

file_put_contents($cache_file, $result);

header('Content-Type: application/json; charset=utf-8');
header('X-Cache: MISS');
echo $result;
exit;
