<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$uid    = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$VALID_TYPES = ['bookmarks','rss','notes','todo','search','weather','clock','embed','calendar'];

// GET  /api/widgets.php?page_id=X          → widgets d'une page
// POST /api/widgets.php?action=create
// POST /api/widgets.php?action=update&id=X
// POST /api/widgets.php?action=delete&id=X
// POST /api/widgets.php?action=move        → mise à jour grille

function user_owns_widget(int $uid, int $widget_id): bool {
    $row = db_fetch(
        'SELECT w.id FROM widgets w
         JOIN pages p ON p.id = w.page_id
         WHERE w.id=? AND p.user_id=?',
        [$widget_id, $uid]
    );
    return $row !== null;
}

function user_owns_page(int $uid, int $page_id): bool {
    return db_fetch('SELECT id FROM pages WHERE id=? AND user_id=?', [$page_id, $uid]) !== null;
}

if ($method === 'GET') {
    $page_id = (int)($_GET['page_id'] ?? 0);
    if (!user_owns_page($uid, $page_id)) json_error('Accès refusé.', 403);
    $widgets = get_page_widgets($page_id);
    // Charger les données spécifiques à chaque type
    foreach ($widgets as &$w) {
        $w['data'] = load_widget_data($w);
    }
    json_response($widgets);
}

function load_widget_data(array $w): array {
    switch ($w['type']) {
        case 'bookmarks':
            return db_fetchAll(
                'SELECT * FROM bookmarks WHERE widget_id=? ORDER BY position ASC',
                [$w['id']]
            );
        case 'notes':
            return db_fetch('SELECT content FROM notes WHERE widget_id=?', [$w['id']]) ?? ['content'=>''];
        case 'todo':
            return db_fetchAll(
                'SELECT * FROM todos WHERE widget_id=? ORDER BY position ASC',
                [$w['id']]
            );
        case 'rss':
            $cache = db_fetch('SELECT content_json, fetched_at FROM rss_cache WHERE widget_id=?', [$w['id']]);
            if ($cache && strtotime($cache['fetched_at']) > time() - (RSS_CACHE_MINUTES * 60)) {
                return json_decode($cache['content_json'], true) ?? [];
            }
            return [];
        default:
            return [];
    }
}

if ($method === 'POST' && $action === 'create') {
    $d       = request_json();
    $page_id = (int)($d['page_id'] ?? 0);
    $type    = $d['type'] ?? '';

    if (!user_owns_page($uid, $page_id)) json_error('Accès refusé.', 403);
    if (!in_array($type, $VALID_TYPES)) json_error('Type invalide.');

    $config = $d['config'] ?? [];
    $id = db_insert(
        'INSERT INTO widgets (page_id, type, title, config_json, grid_x, grid_y, grid_w, grid_h)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $page_id,
            $type,
            $d['title'] ?? ucfirst($type),
            json_encode($config),
            $d['grid_x'] ?? 0,
            $d['grid_y'] ?? 0,
            $d['grid_w'] ?? 4,
            $d['grid_h'] ?? 30,
        ]
    );

    // Initialiser les données selon le type
    if ($type === 'notes') {
        db_insert('INSERT INTO notes (widget_id, content) VALUES (?, ?)', [$id, '']);
    }

    json_response(['id' => $id]);
}

if ($method === 'POST' && $action === 'update') {
    $id = (int)($_GET['id'] ?? 0);
    if (!user_owns_widget($uid, $id)) json_error('Accès refusé.', 403);

    $d = request_json();
    $fields = [];
    $params = [];

    if (isset($d['title'])) { $fields[] = 'title=?'; $params[] = $d['title']; }
    if (isset($d['config'])) { $fields[] = 'config_json=?'; $params[] = json_encode($d['config']); }

    if ($fields) {
        $params[] = $id;
        db_query('UPDATE widgets SET ' . implode(',', $fields) . ' WHERE id=?', $params);
    }

    json_response(['ok' => true]);
}

if ($method === 'POST' && $action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if (!user_owns_widget($uid, $id)) json_error('Accès refusé.', 403);
    db_query('DELETE FROM widgets WHERE id=?', [$id]);
    json_response(['ok' => true]);
}

if ($method === 'POST' && $action === 'move') {
    // Mettre à jour les positions de la grille
    // { items: [{id, x, y, w, h}, ...] }
    $d = request_json();
    foreach (($d['items'] ?? []) as $item) {
        $id = (int)($item['id'] ?? 0);
        if (!user_owns_widget($uid, $id)) continue;
        db_query(
            'UPDATE widgets SET grid_x=?, grid_y=?, grid_w=?, grid_h=? WHERE id=?',
            [$item['x'], $item['y'], $item['w'], $item['h'], $id]
        );
    }
    json_response(['ok' => true]);
}

json_error('Action inconnue.', 404);
