<?php
/* GET /api/v1/crypto?coins=bitcoin,ethereum&currency=eur
   Proxy CoinGecko avec cache 5 min */

if ($method !== 'GET') json_error('Méthode non autorisée.', 405);

$coinsRaw = $_GET['coins'] ?? 'bitcoin,ethereum';
$currency = preg_replace('/[^a-z]/', '', strtolower($_GET['currency'] ?? 'eur'));

$coins = array_filter(
    array_slice(array_map('trim', explode(',', $coinsRaw)), 0, 10),
    fn($c) => preg_match('/^[a-z0-9\-]{1,50}$/', $c)
);
if (empty($coins)) json_error('Aucun coin valide.');

$cache_dir  = dirname(__DIR__, 3) . '/cache/crypto/';
$cache_file = $cache_dir . md5(implode(',', $coins) . $currency) . '.json';
if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 300) {
    header('X-Cache: HIT');
    echo file_get_contents($cache_file);
    exit;
}

$url = 'https://api.coingecko.com/api/v3/simple/price'
     . '?ids='           . urlencode(implode(',', $coins))
     . '&vs_currencies=' . urlencode($currency)
     . '&include_24hr_change=true';

$ctx = stream_context_create(['http' => ['timeout' => 8, 'user_agent' => 'StartMe/1.0']]);
$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) json_error('Impossible de contacter CoinGecko.');

$data = json_decode($raw, true);
if (!$data) json_error('Réponse invalide de CoinGecko.');

$result = json_encode(['data' => $data, 'currency' => $currency, 'cached_at' => date('c')], JSON_UNESCAPED_UNICODE);
file_put_contents($cache_file, $result);

header('Content-Type: application/json; charset=utf-8');
header('X-Cache: MISS');
echo $result;
exit;
