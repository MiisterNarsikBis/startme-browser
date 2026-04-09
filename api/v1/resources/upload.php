<?php
/* GET  /api/v1/upload          → liste les images uploadées par l'utilisateur
   POST /api/v1/upload?page_id=X  multipart/form-data, champ : "bg" */

// GET — liste des images uploadées
if ($method === 'GET') {
    $files = [];
    if (is_dir(UPLOAD_DIR)) {
        foreach (glob(UPLOAD_DIR . 'bg_' . $uid . '_*.{jpg,jpeg,png,webp,gif,avif}', GLOB_BRACE) as $f) {
            $files[] = [
                'url'  => UPLOAD_URL . basename($f),
                'name' => basename($f),
                'size' => filesize($f),
                'time' => filemtime($f),
            ];
        }
        usort($files, fn($a, $b) => $b['time'] - $a['time']);
    }
    json_response($files);
}

if ($method !== 'POST') json_error('Méthode invalide.', 405);

$page_id = (int)($_GET['page_id'] ?? 0);
$page    = db_fetch('SELECT id FROM pages WHERE id=? AND user_id=?', [$page_id, $uid]);
if (!$page) json_error('Page introuvable.', 404);

if (empty($_FILES['bg'])) json_error('Fichier manquant.');

$file = $_FILES['bg'];
if ($file['error'] !== UPLOAD_ERR_OK) json_error('Erreur upload : ' . $file['error']);

$finfo   = new finfo(FILEINFO_MIME_TYPE);
$mime    = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
if (!in_array($mime, $allowed)) json_error('Type de fichier non autorisé.');

if ($file['size'] > 10 * 1024 * 1024) json_error('Fichier trop lourd (max 10 Mo).');

$ext = match($mime) {
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

$url = UPLOAD_URL . $filename;
db_query('UPDATE pages SET bg_type=?, bg_value=? WHERE id=?', ['image', $url, $page_id]);

json_response(['ok' => true, 'url' => $url]);
