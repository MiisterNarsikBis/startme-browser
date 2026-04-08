<?php
/* GET    /api/v1/bookmarks?widget_id=X
   POST   /api/v1/bookmarks          { widget_id, title, url, favicon? }
   PUT    /api/v1/bookmarks/{id}     { title?, url? }
   DELETE /api/v1/bookmarks/{id}
   POST   /api/v1/bookmarks/reorder  { widget_id, order: [id,...] } */

function owns_widget_v1(int $uid, int $widget_id): bool {
    return db_fetch(
        'SELECT w.id FROM widgets w JOIN pages p ON p.id=w.page_id WHERE w.id=? AND p.user_id=?',
        [$widget_id, $uid]
    ) !== null;
}

// GET — liste
if ($method === 'GET' && $id === null) {
    $widget_id = (int)($_GET['widget_id'] ?? 0);
    if (!owns_widget_v1($uid, $widget_id)) json_error('Accès refusé.', 403);
    json_response(db_fetchAll('SELECT * FROM bookmarks WHERE widget_id=? ORDER BY position', [$widget_id]));
}

// POST — création
if ($method === 'POST' && $id === null && $sub === null) {
    $d         = request_json();
    $widget_id = (int)($d['widget_id'] ?? 0);
    if (!owns_widget_v1($uid, $widget_id)) json_error('Accès refusé.', 403);
    $url     = sanitize_url(trim($d['url'] ?? ''));
    $title   = trim($d['title'] ?? '') ?: parse_url($url, PHP_URL_HOST);
    $favicon = $d['favicon'] ?? get_favicon($url);
    $maxPos  = db_fetch('SELECT MAX(position) as m FROM bookmarks WHERE widget_id=?', [$widget_id])['m'] ?? 0;
    $newId   = db_insert('INSERT INTO bookmarks (widget_id,title,url,favicon,position) VALUES (?,?,?,?,?)',
        [$widget_id, $title, $url, $favicon, $maxPos + 1]);
    json_response(['id' => $newId, 'title' => $title, 'url' => $url, 'favicon' => $favicon], 201);
}

// POST — reorder
if ($method === 'POST' && $sub === 'reorder') {
    $d         = request_json();
    $widget_id = (int)($d['widget_id'] ?? 0);
    if (!owns_widget_v1($uid, $widget_id)) json_error('Accès refusé.', 403);
    foreach (($d['order'] ?? []) as $pos => $bmId) {
        db_query('UPDATE bookmarks SET position=? WHERE id=? AND widget_id=?', [$pos, $bmId, $widget_id]);
    }
    json_response(['ok' => true]);
}

// PUT — mise à jour
if ($method === 'PUT' && $id !== null) {
    $bm = db_fetch('SELECT widget_id FROM bookmarks WHERE id=?', [$id]);
    if (!$bm || !owns_widget_v1($uid, $bm['widget_id'])) json_error('Accès refusé.', 403);
    $d      = request_json();
    $fields = []; $params = [];
    if (isset($d['title'])) { $fields[] = 'title=?'; $params[] = $d['title']; }
    if (isset($d['url']))   { $fields[] = 'url=?';   $params[] = sanitize_url($d['url']); }
    if ($fields) { $params[] = $id; db_query('UPDATE bookmarks SET ' . implode(',', $fields) . ' WHERE id=?', $params); }
    json_response(['ok' => true]);
}

// DELETE
if ($method === 'DELETE' && $id !== null) {
    $bm = db_fetch('SELECT widget_id FROM bookmarks WHERE id=?', [$id]);
    if (!$bm || !owns_widget_v1($uid, $bm['widget_id'])) json_error('Accès refusé.', 403);
    db_query('DELETE FROM bookmarks WHERE id=?', [$id]);
    json_response(['ok' => true]);
}

json_error('Route inconnue.', 404);
