<?php
// ============================================================
//  config.php — Copier ce fichier en config.php et adapter
// ============================================================

// DEBUG — désactiver en production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

// Chemin absolu du dossier racine (avec slash final)
define('ROOT_PATH', __DIR__ . '/');

// URL publique de base (sans slash final)
define('BASE_URL', 'https://yourdomain.com');

// Dossier d'upload des fonds d'écran
define('UPLOAD_DIR', ROOT_PATH . 'assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');

// Durée du cache RSS en minutes
define('RSS_CACHE_MINUTES', 60);

// Durée du cache météo en minutes
define('WEATHER_CACHE_MINUTES', 30);

// Dossier cache fichiers (météo, etc.)
define('CACHE_DIR', ROOT_PATH . 'cache/');

// Durée de session en secondes (30 jours)
define('SESSION_LIFETIME', 60 * 60 * 24 * 30);
