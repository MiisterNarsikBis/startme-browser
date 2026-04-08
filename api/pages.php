<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$uid    = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET  /api/pages.php              → liste des pages
// POST /api/pages.php?action=create
// POST /api/pages.php?action=update&id=X
// POST /api/pages.php?action=delete&id=X
// POST /api/pages.php?action=reorder

if ($method === 'GET') {
    json_response(get_user_pages($uid));
}

if ($method === 'POST' && $action === 'create') {
    $d = request_json();
    $name = trim($d['name'] ?? '');
    if (!$name) json_error('Nom requis.');

    $slug = slugify($name);
    // Unicité du slug
    $base = $slug;
    $i = 1;
    while (db_fetch('SELECT id FROM pages WHERE user_id=? AND slug=?', [$uid, $slug])) {
        $slug = $base . '-' . $i++;
    }
    $maxPos = db_fetch('SELECT MAX(position) as m FROM pages WHERE user_id=?', [$uid])['m'] ?? 0;

    $id = db_insert(
        'INSERT INTO pages (user_id, name, slug, icon, bg_type, bg_value, position) VALUES (?,?,?,?,?,?,?)',
        [$uid, $name, $slug, $d['icon'] ?? '📄', 'color', '#0f172a', $maxPos + 1]
    );
    json_response(['id' => $id, 'slug' => $slug]);
}

if ($method === 'POST' && $action === 'update') {
    $id = (int)($_GET['id'] ?? 0);
    $page = db_fetch('SELECT id FROM pages WHERE id=? AND user_id=?', [$id, $uid]);
    if (!$page) json_error('Page introuvable.', 404);

    $d = request_json();
    $fields = [];
    $params = [];

    if (isset($d['name'])) {
        $fields[] = 'name=?';
        $params[] = trim($d['name']);
    }
    if (isset($d['icon'])) {
        $fields[] = 'icon=?';
        $params[] = $d['icon'];
    }
    if (isset($d['bg_type']) && in_array($d['bg_type'], ['color','gradient','image'])) {
        $fields[] = 'bg_type=?';
        $params[] = $d['bg_type'];
        $fields[] = 'bg_value=?';
        $params[] = $d['bg_value'] ?? '#0f172a';
    }

    if ($fields) {
        $params[] = $id;
        db_query('UPDATE pages SET ' . implode(',', $fields) . ' WHERE id=?', $params);
    }

    json_response(['ok' => true]);
}

if ($method === 'POST' && $action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    $page = db_fetch('SELECT id FROM pages WHERE id=? AND user_id=?', [$id, $uid]);
    if (!$page) json_error('Page introuvable.', 404);

    $count = db_fetch('SELECT COUNT(*) as c FROM pages WHERE user_id=?', [$uid])['c'];
    if ($count <= 1) json_error('Impossible de supprimer la dernière page.');

    db_query('DELETE FROM pages WHERE id=?', [$id]);
    json_response(['ok' => true]);
}

if ($method === 'POST' && $action === 'reorder') {
    $d = request_json();
    // $d['order'] = [id, id, id, ...]
    foreach (($d['order'] ?? []) as $pos => $pageId) {
        db_query('UPDATE pages SET position=? WHERE id=? AND user_id=?', [$pos, $pageId, $uid]);
    }
    json_response(['ok' => true]);
}

json_error('Action inconnue.', 404);
