<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

echo json_encode([
    'name'             => 'Startme',
    'short_name'       => 'Startme',
    'description'      => 'Ma page de démarrage personnalisée',
    'start_url'        => BASE_URL . '/',
    'scope'            => BASE_URL . '/',
    'display'          => 'standalone',
    'background_color' => '#0f172a',
    'theme_color'      => '#6366f1',
    'icons'            => [
        ['src' => BASE_URL . '/assets/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
