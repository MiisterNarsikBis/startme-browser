<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$uid = require_auth();

// GET /api/rss.php?widget_id=X&url=https://...
$widget_id = (int)($_GET['widget_id'] ?? 0);
$feed_url  = trim($_GET['url'] ?? '');

// Vérifier propriété du widget
$widget = db_fetch(
    'SELECT w.id, w.config_json FROM widgets w
     JOIN pages p ON p.id = w.page_id
     WHERE w.id=? AND p.user_id=? AND w.type="rss"',
    [$widget_id, $uid]
);
if (!$widget) json_error('Widget introuvable.', 404);

$config    = json_decode($widget['config_json'], true) ?? [];
$max_items = (int)($config['max_items'] ?? 10);

// Valider que l'URL demandée fait partie de la config du widget
$feeds = get_widget_feeds($config);
$allowed_urls = array_column($feeds, 'url');

if (!$feed_url || !in_array($feed_url, $allowed_urls)) {
    json_error('URL de flux non autorisée.');
}

// Vérifier le cache
$cache = db_fetch(
    'SELECT content_json, fetched_at FROM rss_cache WHERE widget_id=? AND url=?',
    [$widget_id, $feed_url]
);

if ($cache && strtotime($cache['fetched_at']) > time() - (RSS_CACHE_MINUTES * 60)) {
    $items = json_decode($cache['content_json'], true) ?? [];
    json_response(['items' => array_slice($items, 0, $max_items), 'cached_at' => $cache['fetched_at']]);
}

// Fetch du flux
$ctx = stream_context_create(['http' => [
    'timeout'       => 10,
    'user_agent'    => 'StartMe RSS Reader/1.0',
    'ignore_errors' => true,
]]);

$xml_str = @file_get_contents($feed_url, false, $ctx);
if (!$xml_str) json_error('Impossible de récupérer le flux : ' . htmlspecialchars($feed_url));

libxml_use_internal_errors(true);
$xml = simplexml_load_string($xml_str, 'SimpleXMLElement', LIBXML_NOCDATA);
if (!$xml) json_error('Flux RSS invalide.');

$items = [];

if (isset($xml->channel->item)) {
    foreach ($xml->channel->item as $item) {
        $items[] = parse_rss_item($item);
        if (count($items) >= 50) break;
    }
} elseif ($xml->entry) {
    foreach ($xml->entry as $entry) {
        $items[] = parse_atom_entry($entry);
        if (count($items) >= 50) break;
    }
}

function parse_rss_item(SimpleXMLElement $item): array {
    $ns_content = $item->children('content', true);
    $ns_media   = $item->children('media', true);
    $image = '';
    if ($ns_media->thumbnail) {
        $image = (string)$ns_media->thumbnail->attributes()->url;
    }
    if (!$image) {
        $desc = (string)($ns_content->encoded ?? $item->description ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)/i', $desc, $m)) {
            $image = $m[1];
        }
    }
    return [
        'title' => html_entity_decode(strip_tags((string)$item->title)),
        'link'  => (string)$item->link,
        'date'  => (string)$item->pubDate,
        'desc'  => html_entity_decode(strip_tags((string)($ns_content->encoded ?? $item->description ?? ''))),
        'image' => $image,
    ];
}

function parse_atom_entry(SimpleXMLElement $entry): array {
    $link = '';
    foreach (($entry->link ?? []) as $l) {
        if ((string)$l->attributes()->rel === 'alternate') { $link = (string)$l->attributes()->href; break; }
    }
    if (!$link && isset($entry->link)) $link = (string)$entry->link->attributes()->href;
    return [
        'title' => html_entity_decode(strip_tags((string)$entry->title)),
        'link'  => $link,
        'date'  => (string)($entry->updated ?? $entry->published ?? ''),
        'desc'  => html_entity_decode(strip_tags((string)($entry->summary ?? $entry->content ?? ''))),
        'image' => '',
    ];
}

// Sauvegarder le cache (unique par widget_id + url)
$json = json_encode($items, JSON_UNESCAPED_UNICODE);
db_query(
    'INSERT INTO rss_cache (widget_id, url, content_json, fetched_at) VALUES (?,?,?,NOW())
     ON DUPLICATE KEY UPDATE content_json=?, fetched_at=NOW()',
    [$widget_id, $feed_url, $json, $json]
);

json_response(['items' => array_slice($items, 0, $max_items), 'cached_at' => date('Y-m-d H:i:s')]);

// --- Helper ---
function get_widget_feeds(array $config): array {
    // Nouveau format : { feeds: [{name, url}, ...] }
    if (!empty($config['feeds']) && is_array($config['feeds'])) {
        return $config['feeds'];
    }
    // Ancien format : { url: '...' }
    if (!empty($config['url'])) {
        return [['name' => 'Flux', 'url' => $config['url']]];
    }
    return [];
}
