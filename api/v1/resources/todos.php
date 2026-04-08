<?php
/* POST   /api/v1/todos           { widget_id, title }
   PUT    /api/v1/todos/{id}      (toggle done)
   DELETE /api/v1/todos/{id} */

function owns_widget_todos(int $uid, int $widget_id): bool {
    return db_fetch(
        'SELECT w.id FROM widgets w JOIN pages p ON p.id=w.page_id WHERE w.id=? AND p.user_id=?',
        [$widget_id, $uid]
    ) !== null;
}

// POST — ajouter une tâche
if ($method === 'POST' && $id === null) {
    $d         = request_json();
    $widget_id = (int)($d['widget_id'] ?? 0);
    if (!owns_widget_todos($uid, $widget_id)) json_error('Accès refusé.', 403);
    $title = trim($d['title'] ?? '');
    if (!$title) json_error('Titre requis.');
    $maxPos = db_fetch('SELECT MAX(position) as m FROM todos WHERE widget_id=?', [$widget_id])['m'] ?? 0;
    $newId  = db_insert('INSERT INTO todos (widget_id, title, position) VALUES (?,?,?)', [$widget_id, $title, $maxPos + 1]);
    json_response(['id' => $newId]);
}

// PUT — toggle done
if ($method === 'PUT' && $id !== null) {
    $todo = db_fetch('SELECT widget_id, done FROM todos WHERE id=?', [$id]);
    if (!$todo || !owns_widget_todos($uid, $todo['widget_id'])) json_error('Accès refusé.', 403);
    db_query('UPDATE todos SET done=? WHERE id=?', [$todo['done'] ? 0 : 1, $id]);
    json_response(['ok' => true]);
}

// DELETE
if ($method === 'DELETE' && $id !== null) {
    $todo = db_fetch('SELECT widget_id FROM todos WHERE id=?', [$id]);
    if (!$todo || !owns_widget_todos($uid, $todo['widget_id'])) json_error('Accès refusé.', 403);
    db_query('DELETE FROM todos WHERE id=?', [$id]);
    json_response(['ok' => true]);
}

json_error('Route inconnue.', 404);
