<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

$uid   = require_auth();
$slug  = $_GET['page'] ?? null;
$pages = get_user_pages($uid);

if (!$pages) {
    header('Location: ' . BASE_URL . '/auth.php');
    exit;
}

if (!$slug) {
    header('Location: ' . BASE_URL . '/admin.php?page=' . $pages[0]['slug']);
    exit;
}

$page = get_page_by_slug($uid, $slug);
if (!$page) {
    header('Location: ' . BASE_URL . '/admin.php?page=' . $pages[0]['slug']);
    exit;
}

$widgets = get_page_widgets($page['id']);
?><!DOCTYPE html>
<html lang="fr" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>✏️ Admin — <?= htmlspecialchars($page['name']) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
<link rel="manifest" href="<?= BASE_URL ?>/manifest.php">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: 'rgb(var(--brand) / <alpha-value>)',
          dark:    'rgb(var(--brand-dark) / <alpha-value>)',
        }
      },
    }
  }
}
</script>
<?php
$accent   = $page['accent_color'] ?? '#6366f1';
$accent   = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#6366f1';
$r        = hexdec(substr($accent, 1, 2));
$g        = hexdec(substr($accent, 3, 2));
$b        = hexdec(substr($accent, 5, 2));
$rDark    = (int)($r * 0.8); $gDark = (int)($g * 0.8); $bDark = (int)($b * 0.8);
?>
<style>:root { --brand: <?= "$r $g $b" ?>; --brand-dark: <?= "$rDark $gDark $bDark" ?>; }</style>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10/dist/gridstack.min.css">
<script src="https://cdn.jsdelivr.net/npm/gridstack@10/dist/gridstack-all.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1/Sortable.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css?v=<?= filemtime(__DIR__.'/assets/css/app.css') ?>">
</head>
<body class="min-h-screen text-white overflow-x-hidden"
  style="<?= bg_style($page) ?>; font-family: 'Inter', sans-serif;">

<div class="fixed inset-0 bg-black/40 pointer-events-none z-0"></div>

<!-- ADMIN HEADER -->
<header class="fixed top-0 left-0 right-0 z-50 flex items-center gap-3 px-4 py-2.5"
  style="background:rgba(79,70,229,0.25);backdrop-filter:blur(16px);border-bottom:1px solid rgba(99,102,241,0.3);">

  <a href="<?= BASE_URL ?>/p/<?= htmlspecialchars($slug) ?>" class="text-xl">🚀</a>

  <!-- Page switcher -->
  <div class="relative" x-data="{open:false}">
    <button @click="open=!open" @click.outside="open=false"
      class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium hover:bg-white/10 transition">
      <?= htmlspecialchars($page['icon'] . ' ' . $page['name']) ?>
      <svg class="w-3.5 h-3.5 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>
    <div x-show="open" x-transition class="absolute top-full left-0 mt-1 w-52 rounded-xl shadow-2xl overflow-hidden"
      style="background:rgba(15,23,42,0.97);border:1px solid rgba(255,255,255,0.12);">
      <?php foreach ($pages as $p): ?>
      <a href="<?= BASE_URL ?>/admin.php?page=<?= htmlspecialchars($p['slug']) ?>"
        class="flex items-center gap-2.5 px-3 py-2.5 text-sm hover:bg-white/10 transition
               <?= $p['id'] === $page['id'] ? 'text-brand font-medium' : 'text-white/80' ?>">
        <?= htmlspecialchars($p['icon'] . ' ' . $p['name']) ?>
      </a>
      <?php endforeach; ?>
      <div class="border-t border-white/10 px-3 py-2">
        <button onclick="adminApp.showModal('new-page')"
          class="text-sm text-white/50 hover:text-white transition w-full text-left">+ Nouvelle page</button>
      </div>
    </div>
  </div>

  <span class="text-xs bg-brand/30 text-brand border border-brand/40 px-2.5 py-1 rounded-full font-medium">
    ✏️ Mode édition
  </span>

  <div class="flex-1"></div>

  <!-- Actions rapides -->
  <button onclick="adminApp.showModal('add-widget')"
    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-brand hover:bg-brand-dark transition">
    + Widget
  </button>

  <button onclick="adminApp.showModal('page-settings')"
    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-white/10 hover:bg-white/20 transition">
    🎨 Page
  </button>

  <button onclick="adminApp.showModal('themes')"
    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm text-white/50 hover:text-white hover:bg-white/10 transition"
    title="Thèmes">
    🎨 Thèmes
  </button>

  <button onclick="adminApp.showModal('backup')"
    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm text-white/50 hover:text-white hover:bg-white/10 transition"
    title="Sauvegarde & Restauration">
    📦
  </button>

  <a href="<?= BASE_URL ?>/p/<?= htmlspecialchars($slug) ?>"
    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm text-white/50 hover:text-white hover:bg-white/10 transition">
    👁️ Voir
  </a>
</header>

<!-- GRILLE EN MODE ÉDITION -->
<main class="relative z-10 pt-16 p-4 min-h-screen">
  <div id="grid" class="grid-stack">
    <?php foreach ($widgets as $w): ?>
    <div class="grid-stack-item"
      gs-id="<?= $w['id'] ?>"
      gs-x="<?= $w['grid_x'] ?>" gs-y="<?= $w['grid_y'] ?>"
      gs-w="<?= $w['grid_w'] ?>" gs-h="<?= $w['grid_h'] ?>">
      <div class="grid-stack-item-content">
        <div class="widget h-full flex flex-col rounded-2xl overflow-hidden relative group"
             style="background:rgba(15,23,42,0.80);backdrop-filter:blur(16px);border:2px solid rgba(99,102,241,0.3);"
             data-widget-id="<?= $w['id'] ?>" data-widget-type="<?= $w['type'] ?>">

          <!-- Barre de contrôle widget -->
          <div class="widget-header flex items-center gap-2 px-3 py-2 border-b border-white/10 cursor-move"
               style="background:rgba(99,102,241,0.15);">
            <span class="text-xs text-white/40">⠿</span>
            <span class="font-semibold text-sm text-white/90 flex-1"><?= htmlspecialchars($w['title'] ?? '') ?></span>
            <span class="text-xs text-white/40 bg-white/10 px-2 py-0.5 rounded-full"><?= $w['type'] ?></span>
            <button onclick="adminApp.editWidgetFromEl(this)"
              data-widget-id="<?= $w['id'] ?>"
              data-widget-type="<?= htmlspecialchars($w['type'], ENT_QUOTES) ?>"
              data-widget-config="<?= htmlspecialchars($w['config_json'] ?? '{}', ENT_QUOTES) ?>"
              data-widget-title="<?= htmlspecialchars($w['title'] ?? '', ENT_QUOTES) ?>"
              class="p-1.5 rounded-lg hover:bg-white/20 text-white/50 hover:text-white transition text-xs">⚙️</button>
            <button onclick="adminApp.deleteWidget(<?= $w['id'] ?>)"
              class="p-1.5 rounded-lg hover:bg-red-500/30 text-white/30 hover:text-red-400 transition text-xs">✕</button>
          </div>

          <div class="widget-body flex-1 overflow-auto p-3 pointer-events-none opacity-70">
            <?php
            // Affichage simplifié en mode admin
            $type = $w['type'];
            $config = json_decode($w['config_json'] ?? '{}', true) ?? [];
            echo '<div class="flex items-center justify-center h-full text-white/30 flex-col gap-2">';
            $icons = ['bookmarks'=>'🔖','rss'=>'📰','notes'=>'📝','todo'=>'✅','search'=>'🔍','weather'=>'🌤️','clock'=>'🕐','embed'=>'🖼️','calendar'=>'📅','image'=>'🌄'];
            echo '<span class="text-4xl">' . ($icons[$type] ?? '📦') . '</span>';
            echo '<span class="text-sm">' . ucfirst($type) . '</span>';
            if ($type === 'weather' && !empty($config['city'])) echo '<span class="text-xs opacity-60">' . htmlspecialchars($config['city']) . '</span>';
            if ($type === 'rss' && !empty($config['url'])) echo '<span class="text-xs opacity-60 truncate max-w-full px-2">' . htmlspecialchars($config['url']) . '</span>';
            echo '</div>';
            ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>

<!-- ============================================================
     MODALS
     ============================================================ -->

<!-- Overlay -->
<div id="modal-overlay" class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4"
  onclick="adminApp.closeModal(event)">

  <!-- Modal Ajouter Widget -->
  <div id="modal-add-widget" class="modal-panel hidden w-full max-w-lg">
    <div class="glass-dark rounded-2xl p-6">
      <h2 class="text-lg font-bold mb-4">Ajouter un widget</h2>
      <div class="grid grid-cols-3 gap-3">
        <?php
        $widgetTypes = [
          'bookmarks' => ['🔖', 'Favoris'],
          'rss'       => ['📰', 'RSS'],
          'notes'     => ['📝', 'Notes'],
          'todo'      => ['✅', 'Tâches'],
          'search'    => ['🔍', 'Recherche'],
          'weather'   => ['🌤️', 'Météo'],
          'clock'     => ['🕐', 'Horloge'],
          'embed'     => ['🖼️', 'Embed'],
          'calendar'  => ['📅', 'Calendrier'],
          'image'     => ['🌄', 'Image'],
          'pomodoro'  => ['🍅', 'Pomodoro'],
          'github'    => ['🐙', 'GitHub/Lab'],
          'countdown' => ['⏳', 'Countdown'],
          'crypto'    => ['📈', 'Crypto'],
          'lofi'      => ['🎵', 'Lofi Radio'],
        ];
        foreach ($widgetTypes as $type => [$icon, $label]):
        ?>
        <button onclick="adminApp.addWidget('<?= $type ?>')"
          class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/5 hover:bg-brand/20
                 border border-white/10 hover:border-brand/50 transition">
          <span class="text-3xl"><?= $icon ?></span>
          <span class="text-sm font-medium"><?= $label ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Modal Édition Widget -->
  <div id="modal-edit-widget" class="modal-panel hidden w-full max-w-md">
    <div class="glass-dark rounded-2xl p-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-bold" id="edit-widget-title">Paramètres</h2>
        <button onclick="adminApp.closeModal()" class="text-white/40 hover:text-white">✕</button>
      </div>
      <div id="edit-widget-fields" class="space-y-4"></div>
      <button onclick="adminApp.saveWidgetConfig()"
        class="w-full mt-5 bg-brand hover:bg-brand-dark py-2.5 rounded-xl font-medium transition">
        Enregistrer
      </button>
    </div>
  </div>

  <!-- Modal Paramètres Page -->
  <div id="modal-page-settings" class="modal-panel hidden w-full max-w-lg">
    <div class="glass-dark rounded-2xl p-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-bold">Paramètres de la page</h2>
        <button onclick="adminApp.closeModal()" class="text-white/40 hover:text-white">✕</button>
      </div>

      <div class="space-y-4">
        <div>
          <label class="text-sm text-white/60 block mb-1">Nom</label>
          <input id="pg-name" type="text" value="<?= htmlspecialchars($page['name']) ?>"
            class="form-input w-full">
        </div>
        <div>
          <label class="text-sm text-white/60 block mb-1">Icône (emoji)</label>
          <input id="pg-icon" type="text" value="<?= htmlspecialchars($page['icon']) ?>"
            class="form-input w-24">
        </div>

        <div>
          <label class="text-sm text-white/60 block mb-2">Fond d'écran</label>
          <div class="flex gap-2 mb-3">
            <button onclick="adminApp.setBgTab('color')" id="bg-tab-color"
              class="px-3 py-1.5 rounded-lg text-sm bg-brand/30 text-brand border border-brand/50">Couleur</button>
            <button onclick="adminApp.setBgTab('gradient')" id="bg-tab-gradient"
              class="px-3 py-1.5 rounded-lg text-sm bg-white/10 hover:bg-white/20 transition">Dégradé</button>
            <button onclick="adminApp.setBgTab('image')" id="bg-tab-image"
              class="px-3 py-1.5 rounded-lg text-sm bg-white/10 hover:bg-white/20 transition">Image</button>
          </div>

          <div id="bg-panel-color">
            <input id="pg-bg-color" type="color" value="#0f172a"
              class="w-full h-12 rounded-xl cursor-pointer bg-transparent border border-white/20 p-1">
          </div>

          <div id="bg-panel-gradient" class="hidden">
            <select id="pg-bg-gradient" class="form-input w-full">
              <option value="linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%)">Nuit indigo</option>
              <option value="linear-gradient(135deg,#0c0a09 0%,#1c1917 50%,#292524 100%)">Obsidienne</option>
              <option value="linear-gradient(135deg,#042f2e 0%,#134e4a 100%)">Forêt profonde</option>
              <option value="linear-gradient(135deg,#1e1b4b 0%,#4c1d95 50%,#5b21b6 100%)">Violet cosmos</option>
              <option value="linear-gradient(135deg,#172554 0%,#1e3a5f 50%,#0c4a6e 100%)">Océan nuit</option>
              <option value="linear-gradient(135deg,#450a0a 0%,#7f1d1d 100%)">Rouge sombre</option>
              <option value="linear-gradient(160deg,#0f172a 0%,#0c1445 50%,#0f172a 100%)">Aurora</option>
            </select>
            <div id="gradient-preview" class="h-16 mt-2 rounded-xl transition-all"
                 style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%)"></div>
          </div>

          <div id="bg-panel-image" class="hidden">
            <div class="mb-2">
              <label class="text-xs text-white/50 block mb-1">URL d'une image</label>
              <input id="pg-bg-url" type="url" placeholder="https://…"
                class="form-input w-full text-sm" oninput="adminApp.previewBgUrl(this.value)">
            </div>
            <div class="text-xs text-white/30 text-center my-2">ou</div>
            <label class="block w-full py-3 text-center rounded-xl border-2 border-dashed border-white/20
                          hover:border-brand/50 cursor-pointer transition text-sm text-white/50 hover:text-white">
              📁 Choisir un fichier (max 10 Mo)
              <input type="file" accept="image/*" class="hidden" onchange="adminApp.uploadBg(this)">
            </label>
            <div id="bg-preview" class="h-24 mt-2 rounded-xl bg-cover bg-center bg-white/5 transition-all"></div>
            <!-- Galerie des images déjà uploadées -->
            <div id="bg-gallery" class="hidden mt-3">
              <p class="text-xs text-white/40 mb-2">Mes images :</p>
              <div id="bg-gallery-grid" class="grid grid-cols-3 gap-2 max-h-36 overflow-y-auto"></div>
            </div>
          </div>
        </div>

        <!-- Couleur d'accent -->
        <div>
          <label class="text-sm text-white/60 block mb-1">Couleur d'accent
            <span class="text-white/30 text-xs ml-1">(boutons, bordures…)</span>
          </label>
          <div class="flex items-center gap-3">
            <input id="pg-accent" type="color" value="<?= htmlspecialchars($accent) ?>"
              class="w-12 h-10 rounded-xl cursor-pointer bg-transparent border border-white/20 p-1"
              oninput="adminApp.previewAccent(this.value)">
            <div class="flex flex-wrap gap-1.5">
              <?php foreach(['#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#eab308','#22c55e','#06b6d4','#3b82f6','#64748b'] as $c): ?>
              <button type="button" onclick="adminApp.pickAccent('<?= $c ?>')"
                class="w-6 h-6 rounded-full border-2 border-white/20 hover:border-white/60 transition"
                style="background:<?= $c ?>" title="<?= $c ?>"></button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Danger zone -->
        <details class="mt-4">
          <summary class="text-sm text-red-400/70 cursor-pointer hover:text-red-400">Danger zone</summary>
          <div class="mt-3">
            <button onclick="adminApp.deletePage(<?= $page['id'] ?>)"
              class="w-full py-2 rounded-xl border border-red-500/40 text-red-400 text-sm hover:bg-red-500/20 transition">
              🗑️ Supprimer cette page
            </button>
          </div>
        </details>
      </div>

      <div class="flex gap-3 mt-5">
        <button onclick="adminApp.closeModal()" class="flex-1 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 transition text-sm">Annuler</button>
        <button onclick="adminApp.savePageSettings(<?= $page['id'] ?>)" class="flex-1 py-2.5 rounded-xl bg-brand hover:bg-brand-dark transition text-sm font-medium">Enregistrer</button>
      </div>
    </div>
  </div>

  <!-- Modal Nouvelle Page -->
  <div id="modal-new-page" class="modal-panel hidden w-full max-w-sm">
    <div class="glass-dark rounded-2xl p-6">
      <h2 class="text-lg font-bold mb-4">Nouvelle page</h2>
      <div class="flex gap-2 mb-4">
        <input id="new-page-icon" type="text" value="📄" maxlength="4"
          class="form-input w-16 text-center text-xl">
        <input id="new-page-name" type="text" placeholder="Nom de la page"
          class="form-input flex-1" onkeydown="if(event.key==='Enter')adminApp.createPage()">
      </div>
      <button onclick="adminApp.createPage()"
        class="w-full bg-brand hover:bg-brand-dark py-2.5 rounded-xl font-medium transition">
        Créer →
      </button>
    </div>
  </div>

  <!-- Modal Thèmes -->
  <div id="modal-themes" class="modal-panel hidden w-full max-w-lg">
    <div class="glass-dark rounded-2xl p-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-bold">🎨 Thèmes</h2>
        <button onclick="adminApp.closeModal()" class="text-white/40 hover:text-white">✕</button>
      </div>
      <p class="text-sm text-white/40 mb-4">Cliquez sur un thème pour l'appliquer immédiatement à cette page.</p>
      <div id="themes-grid" class="grid grid-cols-3 gap-3"></div>
    </div>
  </div>

  <!-- Modal Sauvegarde -->
  <div id="modal-backup" class="modal-panel hidden w-full max-w-md">
    <div class="glass-dark rounded-2xl p-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-bold">📦 Sauvegarde</h2>
        <button onclick="adminApp.closeModal()" class="text-white/40 hover:text-white">✕</button>
      </div>

      <!-- Export -->
      <div class="mb-6">
        <h3 class="text-sm font-semibold text-white/80 mb-1">📤 Exporter</h3>
        <p class="text-xs text-white/40 mb-3">Télécharge toutes vos pages, widgets et favoris dans un fichier JSON.</p>
        <button onclick="adminApp.exportBackup()"
          class="w-full py-2.5 rounded-xl bg-brand/30 hover:bg-brand/50 border border-brand/40 text-brand text-sm font-medium transition">
          Télécharger le backup JSON
        </button>
      </div>

      <div class="border-t border-white/10 mb-6"></div>

      <!-- Import -->
      <div>
        <h3 class="text-sm font-semibold text-white/80 mb-1">📥 Importer</h3>
        <p class="text-xs text-white/40 mb-3">
          Choisissez un fichier de sauvegarde exporté depuis Startme.<br>
          <span class="text-red-400/80">⚠️ Toutes vos pages actuelles seront remplacées.</span>
        </p>
        <label class="block w-full py-3 text-center rounded-xl border-2 border-dashed border-white/20
                      hover:border-brand/50 cursor-pointer transition text-sm text-white/50 hover:text-white mb-3">
          📁 Choisir un fichier .json
          <input type="file" accept=".json,application/json" class="hidden"
                 onchange="adminApp.importBackup(this)">
        </label>
        <div id="import-status" class="hidden text-sm text-center py-2 rounded-xl"></div>
      </div>
    </div>
  </div>

</div><!-- /overlay -->

<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= filemtime(__DIR__.'/assets/js/app.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/admin.js?v=<?= filemtime(__DIR__.'/assets/js/admin.js') ?>"></script>
<script>
const BASE_URL  = '<?= BASE_URL ?>';
const PAGE_ID   = <?= $page['id'] ?>;
const PAGE_SLUG = '<?= htmlspecialchars($slug) ?>';
const PAGE_BG   = <?= json_encode(['type' => $page['bg_type'], 'value' => $page['bg_value']]) ?>;
const PAGE_ACCENT = '<?= $accent ?>';
const PAGES_NAV = <?= json_encode(array_map(fn($p) => [
    'name' => $p['name'], 'icon' => $p['icon'], 'slug' => $p['slug']
], $pages), JSON_UNESCAPED_UNICODE) ?>;
initGrid(true);
</script>
</body>
</html>

<?php
function bg_style(array $page): string {
    return match($page['bg_type']) {
        'image'    => 'background:url(' . htmlspecialchars($page['bg_value']) . ') center/cover no-repeat fixed',
        'gradient' => 'background:' . htmlspecialchars($page['bg_value']),
        default    => 'background:' . htmlspecialchars($page['bg_value']),
    };
}
?>
