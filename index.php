<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

$uid   = require_auth();
$slug  = $_GET['page'] ?? null;
$pages = get_user_pages($uid);

if (!$pages) {
    // Ne devrait pas arriver, mais sécurité
    header('Location: ' . BASE_URL . '/auth.php');
    exit;
}

// Si pas de slug → rediriger vers la première page
if (!$slug) {
    header('Location: ' . BASE_URL . '/p/' . $pages[0]['slug']);
    exit;
}

$page = get_page_by_slug($uid, $slug);
if (!$page) {
    header('Location: ' . BASE_URL . '/p/' . $pages[0]['slug']);
    exit;
}

$widgets = get_page_widgets($page['id']);
?><!DOCTYPE html>
<html lang="fr" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page['icon'] . ' ' . $page['name']) ?> — Startme</title>
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">

<!-- Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: { brand: { DEFAULT: '#6366f1', dark: '#4f46e5' } },
      fontFamily: { mono: ['JetBrains Mono','monospace'] }
    }
  }
}
</script>

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- GridStack -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10/dist/gridstack.min.css">
<script src="https://cdn.jsdelivr.net/npm/gridstack@10/dist/gridstack-all.js"></script>

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1/Sortable.min.js"></script>

<!-- Alpine.js -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
<?php if ($page['bg_type'] === 'image' && !empty($page['bg_value'])): ?>
<link rel="preload" as="image" href="<?= htmlspecialchars($page['bg_value']) ?>">
<?php endif; ?>
</head>
<body class="min-h-screen text-white overflow-x-hidden"
  style="<?= bg_style($page) ?>; font-family: 'Inter', sans-serif;">

<!-- Fond overlay -->
<div id="body-overlay" class="fixed inset-0 pointer-events-none z-0"></div>

<!-- HEADER -->
<header id="app-header" class="fixed top-0 left-0 right-0 z-50 flex items-center gap-3 px-4 py-2.5"
  style="backdrop-filter: blur(16px);">

  <!-- Switcher de pages -->
  <div class="relative" x-data="{ open: false }">
    <button @click="open = !open" @click.outside="open = false"
      class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium hover:bg-white/10 transition">
      <span><?= htmlspecialchars($page['icon']) ?></span>
      <span><?= htmlspecialchars($page['name']) ?></span>
      <svg class="w-3.5 h-3.5 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>

    <div x-show="open" x-transition
      class="absolute top-full left-0 mt-1 w-52 rounded-xl shadow-2xl overflow-hidden"
      style="background: rgba(15,23,42,0.95); border: 1px solid rgba(255,255,255,0.12);">
      <?php foreach ($pages as $p): ?>
      <a href="<?= BASE_URL ?>/p/<?= htmlspecialchars($p['slug']) ?>"
        class="flex items-center gap-2.5 px-3 py-2.5 text-sm hover:bg-white/10 transition
               <?= $p['id'] === $page['id'] ? 'bg-white/10 text-brand font-medium' : 'text-white/80' ?>">
        <span class="text-base"><?= htmlspecialchars($p['icon']) ?></span>
        <span><?= htmlspecialchars($p['name']) ?></span>
        <?php if ($p['id'] === $page['id']): ?>
        <span class="ml-auto w-1.5 h-1.5 bg-brand rounded-full"></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
      <div class="border-t border-white/10 px-3 py-2">
        <a href="<?= BASE_URL ?>/admin.php?page=<?= htmlspecialchars($slug) ?>&create_page=1"
          class="flex items-center gap-2 text-sm text-white/50 hover:text-white transition">
          <span>+</span> Nouvelle page
        </a>
      </div>
    </div>
  </div>

  <div class="flex-1"></div>

  <!-- Bouton Admin -->
  <a href="<?= BASE_URL ?>/admin.php?page=<?= htmlspecialchars($slug) ?>"
    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-brand/20 hover:bg-brand/40 text-brand transition">
    ✏️ Personnaliser
  </a>

  <!-- Toggle thème -->
  <button onclick="toggleTheme()" id="theme-toggle"
    class="flex items-center px-3 py-1.5 rounded-lg text-sm text-white/40 hover:text-white hover:bg-white/10 transition"
    title="Changer de thème">☀️</button>

  <!-- Déconnexion -->
  <button onclick="logout()"
    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm text-white/40 hover:text-white hover:bg-white/10 transition">
    🔒
  </button>
</header>

<!-- GRILLE DE WIDGETS -->
<main class="relative z-10 pt-16 p-4 min-h-screen">
  <div id="grid" class="grid-stack">
    <?php foreach ($widgets as $w): ?>
    <div class="grid-stack-item"
      gs-id="<?= $w['id'] ?>"
      gs-x="<?= $w['grid_x'] ?>" gs-y="<?= $w['grid_y'] ?>"
      gs-w="<?= $w['grid_w'] ?>" gs-h="<?= $w['grid_h'] ?>">
      <div class="grid-stack-item-content">
        <?php renderWidget($w); ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
const PAGE_SLUG = '<?= htmlspecialchars($slug) ?>';
initGrid(false); // false = mode lecture seule
</script>
</body>
</html>

<?php
function bg_style(array $page): string {
    return match($page['bg_type']) {
        'image'    => 'background: url(' . htmlspecialchars($page['bg_value']) . ') center/cover no-repeat fixed',
        'gradient' => 'background: ' . htmlspecialchars($page['bg_value']),
        default    => 'background: ' . htmlspecialchars($page['bg_value']),
    };
}

function renderWidget(array $w): void {
    $config = json_decode($w['config_json'] ?? '{}', true) ?? [];
    $id     = (int)$w['id'];
    $type   = $w['type'];
    $title  = htmlspecialchars($w['title'] ?? '');
    echo '<div class="widget h-full flex flex-col rounded-2xl overflow-hidden"
               style="backdrop-filter:blur(16px);border:1px solid var(--widget-border);"
               data-widget-id="' . $id . '" data-widget-type="' . $type . '">';
    echo '<div class="widget-header flex items-center gap-2 px-4 py-2.5 border-b border-white/10">';
    echo '<span class="font-semibold text-sm text-white/80 flex-1">' . $title . '</span>';
    echo '</div>';
    echo '<div class="widget-body flex-1 overflow-auto p-3">';

    match($type) {
        'bookmarks' => renderBookmarks($id, $config),
        'rss'       => renderRss($id, $config),
        'notes'     => renderNotes($id),
        'todo'      => renderTodo($id),
        'search'    => renderSearch($config),
        'weather'   => renderWeather($id, $config),
        'clock'     => renderClock(),
        'embed'     => renderEmbed($config),
        'calendar'  => renderCalendar($config),
        'image'     => renderImage($config),
        default     => null,
    };

    echo '</div></div>';
}

function renderBookmarks(int $id, array $config): void {
    global $uid;
    $bookmarks = db_fetchAll('SELECT * FROM bookmarks WHERE widget_id=? ORDER BY position', [$id]);
    $display   = $config['display'] ?? 'grid'; // grid | list
    $showTitle = $config['show_title'] ?? true;

    echo '<div class="bookmarks-list ' . ($display === 'list' ? 'flex flex-col gap-1' : 'grid grid-cols-3 gap-2') . '"
              data-widget-id="' . $id . '">';
    foreach ($bookmarks as $bm) {
        $bmId    = (int)$bm['id'];
        // Si le favicon n'est pas encore en cache local, le télécharger maintenant
        $rawFavicon = $bm['favicon'] ?: '';
        if (!$rawFavicon || str_contains($rawFavicon, 'google.com/s2/favicons')) {
            $rawFavicon = get_favicon($bm['url']);
            if ($bm['favicon'] !== $rawFavicon) {
                db_query('UPDATE bookmarks SET favicon=? WHERE id=?', [$rawFavicon, $bm['id']]);
            }
        }
        $favicon = htmlspecialchars($rawFavicon);
        $title   = htmlspecialchars($bm['title']);
        $url     = htmlspecialchars($bm['url']);
        if ($display === 'list') {
            echo '<div class="relative group/bm flex items-center" data-id="' . $bmId . '">
                   <span class="bm-handle px-1 opacity-0 group-hover/bm:opacity-30 hover:!opacity-70 cursor-grab text-white text-xs select-none">⠿</span>
                   <a href="' . $url . '" target="_blank"
                      class="flex-1 flex items-center gap-2.5 px-2 py-1.5 pr-7 rounded-lg hover:bg-white/10 transition">
                     <img src="' . $favicon . '" class="w-4 h-4 rounded flex-shrink-0" onerror="this.src=\'data:image/svg+xml,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; viewBox=&quot;0 0 24 24&quot;><circle cx=&quot;12&quot; cy=&quot;12&quot; r=&quot;10&quot; fill=&quot;%236366f1&quot;/></svg>\'">
                     <span class="text-sm text-white/80 truncate">' . $title . '</span>
                   </a>
                   <button onclick="deleteBookmark(' . $bmId . ', this.closest(\'div\'))"
                     class="absolute right-1.5 opacity-0 group-hover/bm:opacity-100 text-white/30 hover:text-red-400 transition-opacity text-xs leading-none p-0.5">✕</button>
                 </div>';
        } else {
            echo '<div class="relative group/bm" data-id="' . $bmId . '">
                   <a href="' . $url . '" target="_blank"
                      class="flex flex-col items-center gap-1.5 p-2 rounded-xl hover:bg-white/10 transition group text-center w-full">
                     <img src="' . $favicon . '" class="w-8 h-8 rounded-lg" onerror="this.src=\'data:image/svg+xml,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; viewBox=&quot;0 0 24 24&quot;><circle cx=&quot;12&quot; cy=&quot;12&quot; r=&quot;10&quot; fill=&quot;%236366f1&quot;/></svg>\'">
                     ' . ($showTitle ? '<span class="text-xs text-white/60 group-hover:text-white truncate max-w-full">' . $title . '</span>' : '') . '
                   </a>
                   <button onclick="deleteBookmark(' . $bmId . ', this.closest(\'div\'))"
                     class="absolute top-0.5 right-0.5 opacity-0 group-hover/bm:opacity-100 text-white/30 hover:text-red-400 transition-opacity text-[10px] leading-none bg-black/40 rounded p-0.5">✕</button>
                 </div>';
        }
    }
    echo '</div>';
}

function renderRss(int $id, array $config): void {
    $feeds = [];
    if (!empty($config['feeds']) && is_array($config['feeds'])) {
        $feeds = $config['feeds'];
    } elseif (!empty($config['url'])) {
        $feeds = [['name' => 'Flux', 'url' => $config['url']]];
    }

    if (empty($feeds)) {
        echo '<p class="text-white/30 text-sm text-center py-6">Aucun flux configuré.<br>
              <span class="text-xs">Ajoutez un flux dans les paramètres du widget.</span></p>';
        return;
    }

    $feedsJson = htmlspecialchars(json_encode($feeds, JSON_UNESCAPED_UNICODE), ENT_QUOTES);

    // Onglets (visibles seulement si > 1 flux)
    $tabs = '';
    if (count($feeds) > 1) {
        $tabs = '<div class="flex gap-1 mb-2 flex-wrap rss-tabs" data-widget-id="' . $id . '">';
        foreach ($feeds as $i => $feed) {
            $active = $i === 0 ? 'bg-brand/30 text-brand border-brand/50' : 'bg-white/5 text-white/50 border-white/10 hover:bg-white/10 hover:text-white';
            $tabs .= '<button class="rss-tab px-2.5 py-1 rounded-lg text-xs border transition ' . $active . '"
                               data-feed-url="' . htmlspecialchars($feed['url'], ENT_QUOTES) . '"
                               data-widget-id="' . $id . '"
                               onclick="switchRssTab(this,' . $id . ')">'
                   . htmlspecialchars($feed['name']) . '</button>';
        }
        $tabs .= '</div>';
    }

    $firstUrl = htmlspecialchars($feeds[0]['url'], ENT_QUOTES);

    echo $tabs;
    echo '<div class="rss-feed-container overflow-auto" data-widget-id="' . $id . '" data-feeds="' . $feedsJson . '" data-current-url="' . $firstUrl . '">
            <div class="flex items-center gap-2 text-white/30 text-sm py-4">
              <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              Chargement…
            </div>
          </div>';
}

function renderNotes(int $id): void {
    $note = db_fetch('SELECT content FROM notes WHERE widget_id=?', [$id]);
    $content = htmlspecialchars($note['content'] ?? '');
    echo '<textarea class="w-full h-full bg-transparent text-sm text-white/80 resize-none
                           focus:outline-none placeholder-white/30"
                   placeholder="Prendre des notes…"
                   data-widget-id="' . $id . '"
                   onchange="saveNote(' . $id . ', this.value)"
                   oninput="debounceSaveNote(' . $id . ', this.value)">' . $content . '</textarea>';
}

function renderTodo(int $id): void {
    $todos = db_fetchAll('SELECT * FROM todos WHERE widget_id=? ORDER BY position', [$id]);
    echo '<div class="flex flex-col gap-1 todo-list" data-widget-id="' . $id . '">';
    foreach ($todos as $t) {
        $done  = $t['done'] ? 'line-through text-white/30' : 'text-white/80';
        $check = $t['done'] ? 'checked' : '';
        echo '<label class="flex items-center gap-2.5 py-1 px-1 rounded-lg hover:bg-white/5 cursor-pointer group">
                <input type="checkbox" ' . $check . ' class="accent-indigo-500 w-4 h-4 flex-shrink-0"
                  onchange="toggleTodo(' . (int)$t['id'] . ', this)">
                <span class="text-sm flex-1 ' . $done . '">' . htmlspecialchars($t['title']) . '</span>
                <button onclick="deleteTodo(' . (int)$t['id'] . ', this.closest(\'label\'))"
                  class="opacity-0 group-hover:opacity-100 text-white/30 hover:text-red-400 transition text-xs">✕</button>
              </label>';
    }
    echo '</div>
          <form onsubmit="addTodo(event, ' . $id . ')" class="mt-2 flex gap-2">
            <input type="text" placeholder="Nouvelle tâche…"
              class="flex-1 bg-white/10 border border-white/10 rounded-lg px-3 py-1.5 text-sm
                     text-white placeholder-white/30 focus:outline-none focus:border-brand">
            <button type="submit" class="bg-brand/30 hover:bg-brand/60 px-3 py-1.5 rounded-lg text-sm transition">+</button>
          </form>';
}

function renderSearch(array $config): void {
    $engine = $config['engine'] ?? 'google';
    $engines = [
        'google'     => ['https://www.google.com/search?q=', 'Rechercher sur Google…'],
        'duckduckgo' => ['https://duckduckgo.com/?q=',       'Rechercher sur DuckDuckGo…'],
        'brave'      => ['https://search.brave.com/search?q=','Rechercher sur Brave…'],
        'bing'       => ['https://www.bing.com/search?q=',   'Rechercher sur Bing…'],
    ];
    [$action, $placeholder] = $engines[$engine] ?? $engines['google'];
    echo '<form action="' . $action . '" target="_blank" class="flex gap-2 items-center h-full">
            <input type="text" name="q" placeholder="' . $placeholder . '" autofocus
              class="flex-1 bg-white/10 border border-white/15 rounded-xl px-4 py-2.5 text-sm
                     text-white placeholder-white/40 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
            <button type="submit"
              class="bg-brand hover:bg-brand-dark px-4 py-2.5 rounded-xl text-sm font-medium transition">→</button>
          </form>';
}

function renderWeather(int $id, array $config): void {
    $city = htmlspecialchars($config['city'] ?? '');
    if (!$city) {
        echo '<p class="text-white/40 text-sm text-center py-4">Configurez une ville dans les paramètres du widget.</p>';
        return;
    }
    echo '<div class="weather-container h-full" data-widget-id="' . $id . '" data-city="' . $city . '">
              <div class="text-white/30 text-sm py-4 text-center">⏳ Chargement météo…</div>
            </div>';
}

function renderClock(): void {
    echo '<div class="flex flex-col items-center justify-center h-full gap-1">
            <div id="clock-time-' . rand(1000,9999) . '" class="clock-time text-4xl font-mono font-bold text-white">00:00:00</div>
            <div class="clock-date text-sm text-white/50"></div>
          </div>';
}

function renderEmbed(array $config): void {
    $url = htmlspecialchars($config['url'] ?? '');
    if (!$url) {
        echo '<p class="text-white/40 text-sm text-center py-4">URL non configurée.</p>';
        return;
    }
    echo '<iframe src="' . $url . '" class="w-full h-full rounded-lg border-0" loading="lazy"
                  sandbox="allow-scripts allow-same-origin allow-forms allow-popups"></iframe>';
}

function renderCalendar(array $config): void {
    $url = htmlspecialchars($config['ical_url'] ?? '');
    echo '<div class="text-center py-4">
            <p class="text-5xl mb-2">' . date('d') . '</p>
            <p class="text-white/60 text-sm">' . strftime_fr() . '</p>
          </div>';
}

function renderImage(array $config): void {
    $url     = htmlspecialchars($config['url'] ?? '');
    $fit     = $config['fit'] ?? 'cover'; // cover | contain | fill
    $caption = htmlspecialchars($config['caption'] ?? '');
    if (!$url) {
        echo '<p class="text-white/40 text-sm text-center py-4">Configurez une image dans les paramètres du widget.</p>';
        return;
    }
    echo '<div class="relative w-full h-full overflow-hidden rounded-lg">
            <img src="' . $url . '" alt="' . $caption . '"
                 class="w-full h-full"
                 style="object-fit:' . htmlspecialchars($fit) . '"
                 loading="lazy">
            ' . ($caption ? '<div class="absolute bottom-0 left-0 right-0 px-3 py-2 text-xs text-white/80"
                 style="background:linear-gradient(transparent,rgba(0,0,0,0.6))">' . $caption . '</div>' : '') . '
          </div>';
}

function strftime_fr(): string {
    $days   = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $months = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    return $days[date('w')] . ' ' . date('j') . ' ' . $months[date('n')-1] . ' ' . date('Y');
}
?>
