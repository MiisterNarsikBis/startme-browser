<?php
/*
 * GET  /api/v1/json?widget_id=X   — Récupère les données JSON (avec cache)
 * POST /api/v1/json/preview        — Prévisualise une URL (admin) pour choisir les champs
 */

// ---- Prévisualisation (admin) ------------------------------------------------
if ($method === 'POST' && $sub === 'preview') {
    $d   = request_json();
    $url = trim($d['url'] ?? '');

    if (!$url || !preg_match('#^https?://#i', $url)) {
        json_error('URL invalide ou manquante.');
    }

    $ctx  = json_build_ctx();
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) json_error('Impossible de récupérer l\'URL.');

    $data = json_decode($body, true);
    if ($data === null) json_error('La réponse n\'est pas un JSON valide.');

    $fields = json_flatten($data);
    json_response(['fields' => $fields]);
}

// ---- Fetch principal (avec cache) -------------------------------------------
if ($method !== 'GET') json_error('Méthode non autorisée.', 405);
header('Cache-Control: no-store');

$widget_id = (int)($_GET['widget_id'] ?? 0);

$widget = db_fetch(
    'SELECT w.id, w.config_json FROM widgets w
     JOIN pages p ON p.id = w.page_id
     WHERE w.id=? AND p.user_id=? AND w.type="json"',
    [$widget_id, $uid]
);
if (!$widget) json_error('Widget introuvable.', 404);

$config        = json_decode($widget['config_json'], true) ?? [];
$url           = trim($config['url'] ?? '');
$cache_minutes = max(0, (int)($config['cache_minutes'] ?? 5));

if (!$url) json_error('URL non configurée.');

// Vérifier le cache
if ($cache_minutes > 0) {
    $cache = db_fetch(
        'SELECT content_json, fetched_at FROM json_cache WHERE widget_id=?',
        [$widget_id]
    );
    if ($cache && strtotime($cache['fetched_at']) > time() - ($cache_minutes * 60)) {
        json_response([
            'data'       => json_decode($cache['content_json'], true),
            'cached_at'  => $cache['fetched_at'],
            'from_cache' => true,
        ]);
    }
}

// Fetch de l'URL
$ctx  = json_build_ctx();
$body = @file_get_contents($url, false, $ctx);
if ($body === false) json_error('Impossible de récupérer l\'URL : ' . htmlspecialchars($url));

$data = json_decode($body, true);
if ($data === null) json_error('La réponse n\'est pas un JSON valide.');

// Sauvegarder en cache
$encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
db_query(
    'INSERT INTO json_cache (widget_id, url, content_json, fetched_at) VALUES (?,?,?,NOW())
     ON DUPLICATE KEY UPDATE url=?, content_json=?, fetched_at=NOW()',
    [$widget_id, $url, $encoded, $url, $encoded]
);

json_response([
    'data'       => $data,
    'cached_at'  => date('Y-m-d H:i:s'),
    'from_cache' => false,
]);

// --- Helpers -----------------------------------------------------------------

function json_build_ctx(): mixed {
    return stream_context_create(['http' => [
        'timeout'       => 10,
        'user_agent'    => 'StartMe JSON Widget/1.0',
        'ignore_errors' => true,
    ]]);
}

function json_flatten(mixed $data, string $prefix = '', int $depth = 0): array {
    if (!is_array($data)) {
        return [['path' => $prefix ?: 'value', 'sample' => mb_strimwidth((string)$data, 0, 80, '…')]];
    }

    $fields = [];
    $is_list = array_keys($data) === range(0, count($data) - 1);

    if ($is_list) {
        // Array : afficher juste le premier élément comme aperçu
        $sample = is_scalar($data[0] ?? null)
            ? mb_strimwidth((string)($data[0] ?? ''), 0, 80, '…')
            : '[Tableau]';
        $fields[] = ['path' => $prefix ?: '[]', 'sample' => "[{$sample}, …]"];
        // Descendre dans le premier objet si c'est un tableau d'objets
        if (isset($data[0]) && is_array($data[0]) && $depth < 2) {
            foreach (json_flatten($data[0], ($prefix ? $prefix . '[0]' : '[0]'), $depth + 1) as $sub) {
                $fields[] = $sub;
            }
        }
        return $fields;
    }

    foreach ($data as $key => $value) {
        $path = $prefix ? "$prefix.$key" : (string)$key;
        if (is_array($value) && $depth < 2) {
            foreach (json_flatten($value, $path, $depth + 1) as $sub) {
                $fields[] = $sub;
            }
        } else {
            $display  = is_array($value) ? '[Objet]' : mb_strimwidth((string)$value, 0, 80, '…');
            $fields[] = ['path' => $path, 'sample' => $display];
        }
    }
    return $fields;
}
