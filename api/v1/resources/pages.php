<?php
/* GET    /api/v1/pages
   POST   /api/v1/pages             { name, icon? }
   PUT    /api/v1/pages/{id}        { name?, icon?, bg_type?, bg_value? }
   DELETE /api/v1/pages/{id}
   POST   /api/v1/pages/reorder     { order: [id,...] } */

// GET
if ($method === 'GET') {
    json_response(get_user_pages($uid));
}

// POST — création
if ($method === 'POST' && $id === null && $sub === null) {
    $d    = request_json();
    $name = trim($d['name'] ?? '');
    if (!$name) json_error('Nom requis.');
    $slug = slugify($name); $base = $slug; $i = 1;
    while (db_fetch('SELECT id FROM pages WHERE user_id=? AND slug=?', [$uid, $slug])) {
        $slug = $base . '-' . $i++;
    }
    $maxPos = db_fetch('SELECT MAX(position) as m FROM pages WHERE user_id=?', [$uid])['m'] ?? 0;
    $newId  = db_insert(
        'INSERT INTO pages (user_id,name,slug,icon,bg_type,bg_value,position) VALUES (?,?,?,?,?,?,?)',
        [$uid, $name, $slug, $d['icon'] ?? '📄', 'color', '#0f172a', $maxPos + 1]
    );
    json_response(['id' => $newId, 'slug' => $slug], 201);
}

// POST — reorder
if ($method === 'POST' && $sub === 'reorder') {
    $d = request_json();
    foreach (($d['order'] ?? []) as $pos => $pageId) {
        db_query('UPDATE pages SET position=? WHERE id=? AND user_id=?', [$pos, $pageId, $uid]);
    }
    json_response(['ok' => true]);
}

// PUT — mise à jour
if ($method === 'PUT' && $id !== null) {
    $page = db_fetch('SELECT id FROM pages WHERE id=? AND user_id=?', [$id, $uid]);
    if (!$page) json_error('Page introuvable.', 404);
    $d = request_json(); $fields = []; $params = [];
    if (isset($d['name']))    { $fields[] = 'name=?';  $params[] = trim($d['name']); }
    if (isset($d['icon']))    { $fields[] = 'icon=?';  $params[] = $d['icon']; }
    if (isset($d['bg_type']) && in_array($d['bg_type'], ['color','gradient','image'])) {
        $fields[] = 'bg_type=?'; $params[] = $d['bg_type'];
        $fields[] = 'bg_value=?'; $params[] = $d['bg_value'] ?? '#0f172a';
    }
    if ($fields) { $params[] = $id; db_query('UPDATE pages SET '.implode(',',$fields).' WHERE id=?', $params); }
    json_response(['ok' => true]);
}

// DELETE
if ($method === 'DELETE' && $id !== null) {
    $page  = db_fetch('SELECT id FROM pages WHERE id=? AND user_id=?', [$id, $uid]);
    if (!$page) json_error('Page introuvable.', 404);
    $count = db_fetch('SELECT COUNT(*) as c FROM pages WHERE user_id=?', [$uid])['c'];
    if ($count <= 1) json_error('Impossible de supprimer la dernière page.');
    db_query('DELETE FROM pages WHERE id=?', [$id]);
    json_response(['ok' => true]);
}

json_error('Route inconnue.', 404);
