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
    $due    = isset($d['due_date']) && $d['due_date'] ? date('Y-m-d', strtotime($d['due_date'])) : null;
    $maxPos = db_fetch('SELECT MAX(position) as m FROM todos WHERE widget_id=?', [$widget_id])['m'] ?? 0;
    $newId  = db_insert('INSERT INTO todos (widget_id, title, due_date, position) VALUES (?,?,?,?)', [$widget_id, $title, $due, $maxPos + 1]);
    json_response(['id' => $newId, 'due_date' => $due]);
}

// PUT — toggle done OU mise à jour de la date d'échéance
if ($method === 'PUT' && $id !== null) {
    $todo = db_fetch('SELECT widget_id, done FROM todos WHERE id=?', [$id]);
    if (!$todo || !owns_widget_todos($uid, $todo['widget_id'])) json_error('Accès refusé.', 403);
    $d = request_json();
    if (array_key_exists('due_date', $d)) {
        $due = $d['due_date'] ? date('Y-m-d', strtotime($d['due_date'])) : null;
        db_query('UPDATE todos SET due_date=? WHERE id=?', [$due, $id]);
    } else {
        db_query('UPDATE todos SET done=? WHERE id=?', [$todo['done'] ? 0 : 1, $id]);
    }
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
