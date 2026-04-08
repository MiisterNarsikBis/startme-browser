<?php
/* GET /api/v1/weather?city=X
   GET /api/v1/weather?lat=X&lon=X&name=Y */

if ($method !== 'GET') json_error('Méthode non autorisée.', 405);

// Déléguer à l'API existante
$qs = http_build_query(array_merge(['widget_id' => 0], $_GET));
header('Location: ' . BASE_URL . '/api/weather.php?' . $qs, true, 307);
exit;
