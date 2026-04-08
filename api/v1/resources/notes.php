<?php
/* POST /api/v1/notes { widget_id, content } */

if ($method !== 'POST') json_error('Méthode non autorisée.', 405);

$d         = request_json();
$widget_id = (int)($d['widget_id'] ?? 0);

$ok = db_fetch(
    'SELECT w.id FROM widgets w JOIN pages p ON p.id=w.page_id WHERE w.id=? AND p.user_id=?',
    [$widget_id, $uid]
);
if (!$ok) json_error('Widget introuvable.', 404);

$content = $d['content'] ?? '';
db_query(
    'INSERT INTO notes (widget_id, content) VALUES (?,?)
     ON DUPLICATE KEY UPDATE content=?, updated_at=NOW()',
    [$widget_id, $content, $content]
);
json_response(['ok' => true]);
