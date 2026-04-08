<?php
/* GET /api/v1/rss?url=X&widget_id=X */

if ($method !== 'GET') json_error('Méthode non autorisée.', 405);

$qs = http_build_query($_GET);
header('Location: ' . BASE_URL . '/api/rss.php?' . $qs, true, 307);
exit;
