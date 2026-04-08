<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$uid    = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function owns_widget(int $uid, int $widget_id): bool {
    return db_fetch(
        'SELECT w.id FROM widgets w JOIN pages p ON p.id=w.page_id WHERE w.id=? AND p.user_id=?',
        [$widget_id, $uid]
    ) !== null;
}

// POST create  { widget_id, title, url }
// POST update  ?id=X  { title, url }
// POST delete  ?id=X
// POST reorder { widget_id, order: [id,id,...] }

if ($method === 'POST' && $action === 'create') {
    $d = request_json();
    $widget_id = (int)($d['widget_id'] ?? 0);
    if (!owns_widget($uid, $widget_id)) json_error('Accès refusé.', 403);

    $url     = sanitize_url(trim($d['url'] ?? ''));
    $title   = trim($d['title'] ?? '') ?: parse_url($url, PHP_URL_HOST);
    $favicon = $d['favicon'] ?? get_favicon($url);

    $maxPos = db_fetch('SELECT MAX(position) as m FROM bookmarks WHERE widget_id=?', [$widget_id])['m'] ?? 0;

    $id = db_insert(
        'INSERT INTO bookmarks (widget_id, title, url, favicon, position) VALUES (?,?,?,?,?)',
        [$widget_id, $title, $url, $favicon, $maxPos + 1]
    );
    json_response(['id' => $id, 'title' => $title, 'url' => $url, 'favicon' => $favicon]);
}

if ($method === 'POST' && $action === 'update') {
    $id = (int)($_GET['id'] ?? 0);
    $bm = db_fetch('SELECT b.id, b.widget_id FROM bookmarks b WHERE b.id=?', [$id]);
    if (!$bm || !owns_widget($uid, $bm['widget_id'])) json_error('Accès refusé.', 403);

    $d = request_json();
    $fields = [];
    $params = [];
    if (isset($d['title'])) { $fields[] = 'title=?'; $params[] = $d['title']; }
    if (isset($d['url']))   { $fields[] = 'url=?';   $params[] = sanitize_url($d['url']); }

    if ($fields) {
        $params[] = $id;
        db_query('UPDATE bookmarks SET ' . implode(',', $fields) . ' WHERE id=?', $params);
    }
    json_response(['ok' => true]);
}

if ($method === 'POST' && $action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    $bm = db_fetch('SELECT b.widget_id FROM bookmarks b WHERE b.id=?', [$id]);
    if (!$bm || !owns_widget($uid, $bm['widget_id'])) json_error('Accès refusé.', 403);
    db_query('DELETE FROM bookmarks WHERE id=?', [$id]);
    json_response(['ok' => true]);
}

if ($method === 'POST' && $action === 'reorder') {
    $d = request_json();
    $widget_id = (int)($d['widget_id'] ?? 0);
    if (!owns_widget($uid, $widget_id)) json_error('Accès refusé.', 403);
    foreach (($d['order'] ?? []) as $pos => $bmId) {
        db_query('UPDATE bookmarks SET position=? WHERE id=? AND widget_id=?', [$pos, $bmId, $widget_id]);
    }
    json_response(['ok' => true]);
}

// POST notes update
if ($method === 'POST' && $action === 'save_note') {
    $d = request_json();
    $widget_id = (int)($d['widget_id'] ?? 0);
    if (!owns_widget($uid, $widget_id)) json_error('Accès refusé.', 403);
    $content = $d['content'] ?? '';
    db_query(
        'INSERT INTO notes (widget_id, content) VALUES (?,?)
         ON DUPLICATE KEY UPDATE content=?, updated_at=NOW()',
        [$widget_id, $content, $content]
    );
    json_response(['ok' => true]);
}

// POST todos
if ($method === 'POST' && $action === 'todo_add') {
    $d = request_json();
    $widget_id = (int)($d['widget_id'] ?? 0);
    if (!owns_widget($uid, $widget_id)) json_error('Accès refusé.', 403);
    $title = trim($d['title'] ?? '');
    if (!$title) json_error('Titre requis.');
    $maxPos = db_fetch('SELECT MAX(position) as m FROM todos WHERE widget_id=?', [$widget_id])['m'] ?? 0;
    $id = db_insert('INSERT INTO todos (widget_id, title, position) VALUES (?,?,?)', [$widget_id, $title, $maxPos+1]);
    json_response(['id' => $id]);
}

if ($method === 'POST' && $action === 'todo_toggle') {
    $id = (int)($_GET['id'] ?? 0);
    $todo = db_fetch('SELECT widget_id, done FROM todos WHERE id=?', [$id]);
    if (!$todo || !owns_widget($uid, $todo['widget_id'])) json_error('Accès refusé.', 403);
    db_query('UPDATE todos SET done=? WHERE id=?', [$todo['done'] ? 0 : 1, $id]);
    json_response(['ok' => true]);
}

if ($method === 'POST' && $action === 'todo_delete') {
    $id = (int)($_GET['id'] ?? 0);
    $todo = db_fetch('SELECT widget_id FROM todos WHERE id=?', [$id]);
    if (!$todo || !owns_widget($uid, $todo['widget_id'])) json_error('Accès refusé.', 403);
    db_query('DELETE FROM todos WHERE id=?', [$id]);
    json_response(['ok' => true]);
}

json_error('Action inconnue.', 404);
