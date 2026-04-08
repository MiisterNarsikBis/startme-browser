<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$uid = require_auth();

// POST /api/upload.php?page_id=X
// multipart/form-data  champ : "bg"

$page_id = (int)($_GET['page_id'] ?? 0);
$page    = db_fetch('SELECT id FROM pages WHERE id=? AND user_id=?', [$page_id, $uid]);
if (!$page) json_error('Page introuvable.', 404);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Méthode invalide.', 405);
if (empty($_FILES['bg'])) json_error('Fichier manquant.');

$file  = $_FILES['bg'];
if ($file['error'] !== UPLOAD_ERR_OK) json_error('Erreur upload : ' . $file['error']);

// Validation type MIME
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file($file['tmp_name']);
$allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
if (!in_array($mime, $allowed)) json_error('Type de fichier non autorisé.');

// Taille max 10 Mo
if ($file['size'] > 10 * 1024 * 1024) json_error('Fichier trop lourd (max 10 Mo).');

// Nom sécurisé
$ext      = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    'image/avif' => 'avif',
    default      => 'jpg',
};
$filename = 'bg_' . $uid . '_' . $page_id . '_' . time() . '.' . $ext;
$dest     = UPLOAD_DIR . $filename;

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!move_uploaded_file($file['tmp_name'], $dest)) json_error('Impossible de sauvegarder le fichier.');

// Mettre à jour la page
$url = UPLOAD_URL . $filename;
db_query(
    'UPDATE pages SET bg_type=?, bg_value=? WHERE id=?',
    ['image', $url, $page_id]
);

json_response(['ok' => true, 'url' => $url]);
