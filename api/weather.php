<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$uid = require_auth();

// GET /api/weather.php?widget_id=X&city=Paris
// GET /api/weather.php?widget_id=X&lat=48.85&lon=2.35&name=Paris  (coordonnées directes, skip géocodage)
$widget_id = (int)($_GET['widget_id'] ?? 0);
$city      = trim($_GET['city'] ?? '');
$lat_in    = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lon_in    = isset($_GET['lon']) ? (float)$_GET['lon'] : null;
$name_in   = trim($_GET['name'] ?? '');

if (!$city && $lat_in === null) json_error('Ville ou coordonnées requises.');

// --- Cache fichier ---
if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
$cache_key  = $lat_in !== null ? round($lat_in, 3) . '_' . round($lon_in, 3) : md5($city);
$cache_file = CACHE_DIR . 'weather_' . $uid . '_' . ($widget_id ?: $cache_key) . '.json';

if (file_exists($cache_file)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    if ($cached && isset($cached['_cached_at'])
        && (time() - $cached['_cached_at']) < (WEATHER_CACHE_MINUTES * 60)) {
        unset($cached['_cached_at']);
        json_response($cached);
    }
}

$ctx = stream_context_create(['http' => [
    'timeout'    => 8,
    'user_agent' => 'StartMe/1.0 (homepage perso)',
]]);

$lat = $lon = $name = $country = $postal = $admin = null;

// --- Coordonnées fournies directement (depuis l'autocomplétion) → skip géocodage ---
if ($lat_in !== null && $lon_in !== null) {
    $lat     = $lat_in;
    $lon     = $lon_in;
    $name    = $name_in ?: 'Localisation';
    $country = '';
    $admin   = '';
} else {
    // 1. Géocodage — Open-Meteo puis Nominatim (OSM) en fallback
    $is_postal = preg_match('/^\d{4,10}$/', trim($city))
              || preg_match('/^[A-Z]{2}[\s\-]?\d{4,10}$/i', trim($city));

    if (!$is_postal) {
        $geo_url  = 'https://geocoding-api.open-meteo.com/v1/search?name=' . urlencode($city) . '&count=1&language=fr&format=json';
        $geo_json = @file_get_contents($geo_url, false, $ctx);
        if ($geo_json) {
            $geo = json_decode($geo_json, true);
            if (!empty($geo['results'][0])) {
                $loc     = $geo['results'][0];
                $lat     = $loc['latitude'];
                $lon     = $loc['longitude'];
                $name    = $loc['name'];
                $country = $loc['country'] ?? '';
                $admin   = $loc['admin1'] ?? '';
            }
        }
    }

    // Fallback Nominatim (OpenStreetMap) — gère codes postaux ET noms
    if ($lat === null) {
        $nom_url  = 'https://nominatim.openstreetmap.org/search?q=' . urlencode($city)
                  . '&format=json&limit=1&accept-language=fr&addressdetails=1';
        $nom_json = @file_get_contents($nom_url, false, $ctx);
        if (!$nom_json) json_error('Service de géocodage indisponible.');

        $nom = json_decode($nom_json, true);
        if (empty($nom[0])) json_error('Ville ou code postal introuvable : « ' . htmlspecialchars($city) . ' »');

        $loc     = $nom[0];
        $lat     = (float)$loc['lat'];
        $lon     = (float)$loc['lon'];
        $addr    = $loc['address'] ?? [];
        $name    = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $loc['display_name'];
        $country = $addr['country'] ?? '';
        $postal  = $addr['postcode'] ?? '';
        $admin   = $addr['state'] ?? $addr['county'] ?? '';
    }

    if ($lat === null) json_error('Localisation introuvable.');
}

// 2. Météo
$weather_url = sprintf(
    'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s'
    . '&current=temperature_2m,apparent_temperature,weathercode,windspeed_10m,relativehumidity_2m'
    . '&daily=temperature_2m_max,temperature_2m_min,weathercode,precipitation_probability_max'
    . '&timezone=auto&forecast_days=5',
    $lat, $lon
);

$weather_json = @file_get_contents($weather_url, false, $ctx);
if (!$weather_json) json_error('Données météo indisponibles.');
$weather = json_decode($weather_json, true);

// Codes WMO → description + emoji
$wmo = [
    0  => ['Ciel dégagé',       '☀️'],
    1  => ['Peu nuageux',        '🌤️'],
    2  => ['Partiellement nuageux','⛅'],
    3  => ['Couvert',            '☁️'],
    45 => ['Brouillard',         '🌫️'],
    48 => ['Brouillard givrant', '🌫️'],
    51 => ['Bruine légère',      '🌦️'],
    53 => ['Bruine',             '🌦️'],
    55 => ['Bruine forte',       '🌧️'],
    61 => ['Pluie légère',       '🌧️'],
    63 => ['Pluie',              '🌧️'],
    65 => ['Forte pluie',        '🌧️'],
    71 => ['Neige légère',       '🌨️'],
    73 => ['Neige',              '❄️'],
    75 => ['Forte neige',        '❄️'],
    77 => ['Granules de neige',  '❄️'],
    80 => ['Averses légères',    '🌦️'],
    81 => ['Averses',            '🌧️'],
    82 => ['Fortes averses',     '⛈️'],
    85 => ['Averses de neige',   '🌨️'],
    86 => ['Fortes averses neige','🌨️'],
    95 => ['Orage',              '⛈️'],
    96 => ['Orage + grêle',      '⛈️'],
    99 => ['Orage fort + grêle', '⛈️'],
];

function wmo_info(int $code, array $wmo): array {
    return $wmo[$code] ?? ['Inconnu', '🌡️'];
}

$cur = $weather['current'];
$daily = $weather['daily'];
$curInfo = wmo_info((int)$cur['weathercode'], $wmo);

$days = [];
for ($i = 0; $i < min(5, count($daily['time'])); $i++) {
    $info = wmo_info((int)$daily['weathercode'][$i], $wmo);
    $days[] = [
        'date'   => $daily['time'][$i],
        'max'    => round($daily['temperature_2m_max'][$i]),
        'min'    => round($daily['temperature_2m_min'][$i]),
        'code'   => $daily['weathercode'][$i],
        'desc'   => $info[0],
        'emoji'  => $info[1],
        'precip' => $daily['precipitation_probability_max'][$i] ?? 0,
    ];
}

$response = [
    'city'     => $name,
    'postal'   => $postal ?? '',
    'admin'    => $admin ?? '',
    'country'  => $country,
    'current'  => [
        'temp'       => round($cur['temperature_2m']),
        'feels_like' => round($cur['apparent_temperature']),
        'humidity'   => $cur['relativehumidity_2m'],
        'wind'       => round($cur['windspeed_10m']),
        'code'       => $cur['weathercode'],
        'desc'       => $curInfo[0],
        'emoji'      => $curInfo[1],
    ],
    'forecast' => $days,
];

// Sauvegarder le cache
file_put_contents($cache_file, json_encode($response + ['_cached_at' => time()]));

json_response($response);
