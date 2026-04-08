<?php

function json_response(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function json_error(string $message, int $code = 400): never {
    json_response(['error' => $message], $code);
}

function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'à'=>'a','â'=>'a','ä'=>'a',
        'ù'=>'u','û'=>'u','ü'=>'u',
        'î'=>'i','ï'=>'i',
        'ô'=>'o','ö'=>'o',
        'ç'=>'c','œ'=>'oe','æ'=>'ae',
    ]);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function get_favicon(string $url): string {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return '';

    $dir      = UPLOAD_DIR . 'favicons/';
    $filename = md5($host) . '.png';
    $filepath = $dir . $filename;
    $fileurl  = UPLOAD_URL . 'favicons/' . $filename;

    // Cache valide (< 30 jours)
    if (file_exists($filepath) && (time() - filemtime($filepath)) < 30 * 86400) {
        return $fileurl;
    }

    // Télécharger depuis Google Favicon API
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $google_url = 'https://www.google.com/s2/favicons?sz=64&domain=' . urlencode($host);
    $ctx  = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'StartMe/1.0']]);
    $data = @file_get_contents($google_url, false, $ctx);

    if ($data && strlen($data) > 200) {
        file_put_contents($filepath, $data);
        return $fileurl;
    }

    // Fallback : URL Google directe
    return $google_url;
}

function sanitize_url(string $url): string {
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return filter_var($url, FILTER_SANITIZE_URL) ?: '';
}

function request_json(): array {
    $body = file_get_contents('php://input');
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR) ?? [];
}

function get_user_pages(int $user_id): array {
    return db_fetchAll(
        'SELECT * FROM pages WHERE user_id = ? ORDER BY position ASC, id ASC',
        [$user_id]
    );
}

function get_page_by_slug(int $user_id, string $slug): ?array {
    return db_fetch(
        'SELECT * FROM pages WHERE user_id = ? AND slug = ?',
        [$user_id, $slug]
    );
}

function get_page_widgets(int $page_id): array {
    return db_fetchAll(
        'SELECT * FROM widgets WHERE page_id = ? ORDER BY grid_y ASC, grid_x ASC',
        [$page_id]
    );
}
