<?php
/* GET    /api/v1/widgets?page_id=X
   POST   /api/v1/widgets             { page_id, type, title, config, grid_x, grid_y, grid_w, grid_h }
   PUT    /api/v1/widgets/{id}        { title?, config? }
   DELETE /api/v1/widgets/{id}
   POST   /api/v1/widgets/reorder     { items: [{id,x,y,w,h}] } */

$VALID_TYPES = ['bookmarks','rss','notes','todo','search','weather','clock','embed','calendar','image','pomodoro','github','countdown','crypto','lofi'];

function owns_page_v1(int $uid, int $page_id): bool {
    return db_fetch('SELECT id FROM pages WHERE id=? AND user_id=?', [$page_id, $uid]) !== null;
}
function owns_widget_v1b(int $uid, int $widget_id): bool {
    return db_fetch(
        'SELECT w.id FROM widgets w JOIN pages p ON p.id=w.page_id WHERE w.id=? AND p.user_id=?',
        [$widget_id, $uid]
    ) !== null;
}

// GET
if ($method === 'GET') {
    $page_id = (int)($_GET['page_id'] ?? 0);
    if (!owns_page_v1($uid, $page_id)) json_error('Accès refusé.', 403);
    json_response(get_page_widgets($page_id));
}

// POST — création
if ($method === 'POST' && $id === null && $sub === null) {
    $d       = request_json();
    $page_id = (int)($d['page_id'] ?? 0);
    $type    = $d['type'] ?? '';
    if (!owns_page_v1($uid, $page_id)) json_error('Accès refusé.', 403);
    if (!in_array($type, $VALID_TYPES)) json_error('Type invalide.');
    $newId = db_insert(
        'INSERT INTO widgets (page_id,type,title,config_json,grid_x,grid_y,grid_w,grid_h) VALUES (?,?,?,?,?,?,?,?)',
        [$page_id, $type, $d['title'] ?? ucfirst($type), json_encode($d['config'] ?? []),
         $d['grid_x'] ?? 0, $d['grid_y'] ?? 0, $d['grid_w'] ?? 4, $d['grid_h'] ?? 30]
    );
    if ($type === 'notes') db_insert('INSERT INTO notes (widget_id,content) VALUES (?,?)', [$newId, '']);
    json_response(['id' => $newId], 201);
}

// POST — reorder (move grille)
if ($method === 'POST' && $sub === 'reorder') {
    $d = request_json();
    foreach (($d['items'] ?? []) as $item) {
        $wid = (int)($item['id'] ?? 0);
        if (!owns_widget_v1b($uid, $wid)) continue;
        db_query('UPDATE widgets SET grid_x=?,grid_y=?,grid_w=?,grid_h=? WHERE id=?',
            [$item['x'], $item['y'], $item['w'], $item['h'], $wid]);
    }
    json_response(['ok' => true]);
}

// PUT — mise à jour
if ($method === 'PUT' && $id !== null) {
    if (!owns_widget_v1b($uid, $id)) json_error('Accès refusé.', 403);
    $d = request_json(); $fields = []; $params = [];
    if (isset($d['title']))  { $fields[] = 'title=?';       $params[] = $d['title']; }
    if (isset($d['config'])) { $fields[] = 'config_json=?'; $params[] = json_encode($d['config']); }
    if ($fields) { $params[] = $id; db_query('UPDATE widgets SET '.implode(',',$fields).' WHERE id=?', $params); }
    json_response(['ok' => true]);
}

// DELETE
if ($method === 'DELETE' && $id !== null) {
    if (!owns_widget_v1b($uid, $id)) json_error('Accès refusé.', 403);
    db_query('DELETE FROM widgets WHERE id=?', [$id]);
    json_response(['ok' => true]);
}

json_error('Route inconnue.', 404);
