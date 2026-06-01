<?php
/*
 * GET  /api/v1/json?widget_id=X   — Récupère toutes les sources JSON (avec cache par URL)
 * POST /api/v1/json/preview        — Prévisualise une URL (admin) pour choisir les champs
 */

// ---- Prévisualisation (admin) ------------------------------------------------
if ($method === 'POST' && $sub === 'preview') {
    $d   = request_json();
    $url = trim($d['url'] ?? '');

    if (!$url || !preg_match('#^https?://#i', $url)) {
        json_error('URL invalide ou manquante.');
    }

    $body = @file_get_contents($url, false, json_build_ctx());
    if ($body === false) json_error('Impossible de récupérer l\'URL.');

    $data = json_decode($body, true);
    if ($data === null) json_error('La réponse n\'est pas un JSON valide.');

    json_response(['fields' => json_flatten($data)]);
}

// ---- Fetch principal (avec cache par source) --------------------------------
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

$config        = json_normalize_config(json_decode($widget['config_json'], true) ?? []);
$sources       = $config['sources'] ?? [];
$cache_minutes = max(0, (int)($config['cache_minutes'] ?? 5));

if (empty($sources)) json_error('Aucune source configurée.');

$results = [];

foreach ($sources as $source) {
    $url = trim($source['url'] ?? '');
    if (!$url) continue;

    $url_hash = md5($url);
    $data     = null;
    $cached_at = null;

    // Vérifier le cache (try/catch : dégradation gracieuse si migration pas encore jouée)
    if ($cache_minutes > 0) {
        try {
            $cache = db_fetch(
                'SELECT content_json, fetched_at FROM json_cache WHERE widget_id=? AND url_hash=?',
                [$widget_id, $url_hash]
            );
            if ($cache && strtotime($cache['fetched_at']) > time() - ($cache_minutes * 60)) {
                $data      = json_decode($cache['content_json'], true);
                $cached_at = $cache['fetched_at'];
            }
        } catch (Throwable $e) {
            // Cache indisponible, on fera un fetch direct
        }
    }

    // Fetch si pas en cache
    if ($data === null) {
        $body = @file_get_contents($url, false, json_build_ctx());
        if ($body === false) {
            $results[] = ['name' => $source['name'] ?? '', 'error' => 'Impossible de récupérer l\'URL'];
            continue;
        }

        $data = json_decode($body, true);
        if ($data === null) {
            $results[] = ['name' => $source['name'] ?? '', 'error' => 'Réponse JSON invalide'];
            continue;
        }

        $encoded   = json_encode($data, JSON_UNESCAPED_UNICODE);
        $cached_at = date('Y-m-d H:i:s');
        try {
            db_query(
                'INSERT INTO json_cache (widget_id, url_hash, url, content_json, fetched_at) VALUES (?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE url=?, content_json=?, fetched_at=NOW()',
                [$widget_id, $url_hash, $url, $encoded, $url, $encoded]
            );
        } catch (Throwable $e) {
            // Échec silencieux du cache
        }
    }

    $results[] = [
        'name'      => $source['name'] ?? '',
        'data'      => $data,
        'cached_at' => $cached_at,
    ];
}

json_response(['sources' => $results]);

// --- Helpers -----------------------------------------------------------------

function json_normalize_config(array $config): array {
    // Rétrocompatibilité : ancien format { url, cache_minutes, display_fields }
    if (!isset($config['sources']) && isset($config['url'])) {
        return [
            'sources' => [[
                'name'           => '',
                'url'            => $config['url'],
                'display_fields' => $config['display_fields'] ?? [],
            ]],
            'cache_minutes' => $config['cache_minutes'] ?? 5,
        ];
    }
    return $config;
}

function json_build_ctx() {
    return stream_context_create(['http' => [
        'timeout'       => 10,
        'user_agent'    => 'StartMe JSON Widget/1.0',
        'ignore_errors' => true,
    ]]);
}

function json_flatten($data, string $prefix = '', int $depth = 0): array {
    if (!is_array($data)) {
        return [['path' => $prefix ?: 'value', 'sample' => mb_strimwidth((string)$data, 0, 80, '…')]];
    }

    $fields  = [];
    $is_list = array_keys($data) === range(0, count($data) - 1);

    if ($is_list) {
        $sample   = is_scalar($data[0] ?? null) ? mb_strimwidth((string)($data[0] ?? ''), 0, 80, '…') : '[Tableau]';
        $fields[] = ['path' => $prefix ?: '[]', 'sample' => "[{$sample}, …]"];
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
