<?php
/* ============================================================
   api/v1/router.php — Routeur REST Startme
   Toutes les requêtes /api/v1/* arrivent ici via .htaccess

   Routes :
     POST   /api/v1/auth/login
     POST   /api/v1/auth/logout
     POST   /api/v1/auth/generate

     GET    /api/v1/pages
     POST   /api/v1/pages
     PUT    /api/v1/pages/{id}
     DELETE /api/v1/pages/{id}

     GET    /api/v1/widgets?page_id=X
     POST   /api/v1/widgets
     PUT    /api/v1/widgets/{id}
     DELETE /api/v1/widgets/{id}
     POST   /api/v1/widgets/reorder

     GET    /api/v1/bookmarks?widget_id=X
     POST   /api/v1/bookmarks
     PUT    /api/v1/bookmarks/{id}
     DELETE /api/v1/bookmarks/{id}
     POST   /api/v1/bookmarks/reorder

     GET    /api/v1/weather?city=X
     GET    /api/v1/weather?lat=X&lon=X&name=Y
     GET    /api/v1/rss?url=X&widget_id=X
============================================================ */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth_check.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Extraire le chemin après /api/v1/
$base  = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?: '', '/');
$path  = preg_replace('#^' . preg_quote($base . '/api/v1', '#') . '#', '', $uri);
$path  = trim($path, '/');
$parts = explode('/', $path);           // ['widgets', '42'] ou ['auth', 'login']
$resource = $parts[0] ?? '';
$id       = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;
$sub      = isset($parts[1]) && !is_numeric($parts[1]) ? $parts[1] : null; // ex: 'reorder'

// Routes publiques (sans auth)
if ($resource === 'auth') {
    require __DIR__ . '/resources/auth.php';
    exit;
}

// Toutes les autres routes requièrent une auth
$uid = get_current_user_id();
if (!$uid) json_error('Non authentifié.', 401);

match ($resource) {
    'pages'     => require __DIR__ . '/resources/pages.php',
    'widgets'   => require __DIR__ . '/resources/widgets.php',
    'bookmarks' => require __DIR__ . '/resources/bookmarks.php',
    'notes'     => require __DIR__ . '/resources/notes.php',
    'todos'     => require __DIR__ . '/resources/todos.php',
    'weather'   => require __DIR__ . '/resources/weather.php',
    'rss'       => require __DIR__ . '/resources/rss.php',
    default     => json_error('Ressource inconnue.', 404),
};
