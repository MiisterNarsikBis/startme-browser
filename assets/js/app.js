/* ============================================================
   app.js — Logique commune (vue + admin)
   ============================================================ */

'use strict';

let grid;

// ----------------------------------------------------------------
// GridStack — initialisation
// ----------------------------------------------------------------
function initGrid(editMode) {
  grid = GridStack.init({
    column: 12,
    cellHeight: 10,      // grille de 10px → positionnement quasi libre
    cellHeightUnit: 'px',
    margin: 4,
    float: true,         // les widgets ne se poussent plus entre eux
    animate: true,
    draggable: {
      handle: '.widget-header',
    },
    resizable: {
      handles: 'se,s,e', // resize bas, droite et coin
    },
    staticGrid: !editMode,
  });

  if (editMode) {
    grid.on('change', debounce(saveGridLayout, 800));
  }

  // Charger les données dynamiques
  document.querySelectorAll('[data-widget-type="rss"]').forEach(el => {
    loadRss(el.dataset.widgetId);
  });

  document.querySelectorAll('[data-widget-type="weather"]').forEach(el => {
    const city = el.querySelector('[data-city]')?.dataset.city;
    if (city) loadWeather(el.dataset.widgetId, city);
  });

  // Horloges
  document.querySelectorAll('.clock-time').forEach(el => {
    updateClock(el);
    setInterval(() => updateClock(el), 1000);
  });

  // Drag & drop bookmarks
  initBookmarksSortable();
}

// ----------------------------------------------------------------
// Sauvegarde layout grille
// ----------------------------------------------------------------
async function saveGridLayout() {
  const items = grid.engine.nodes.map(n => ({
    id: n.el.getAttribute('gs-id'),
    x: n.x, y: n.y, w: n.w, h: n.h,
  }));

  await apiFetch('/api/v1/widgets/reorder', { items });
}

// ----------------------------------------------------------------
// RSS — chargement et onglets
// ----------------------------------------------------------------
const rssFetchVersion = {}; // anti-race : seul le dernier fetch par widget s'affiche
const rssPageCache   = {}; // cache JS en mémoire : pas de re-fetch lors des switch d'onglets

async function loadRss(widgetId) {
  const container = document.querySelector(`.rss-feed-container[data-widget-id="${widgetId}"]`);
  if (!container) return;

  const url = container.dataset.currentUrl;
  if (!url) {
    container.innerHTML = '<p class="text-white/30 text-sm py-4 text-center">Aucun flux configuré.</p>';
    return;
  }

  await loadRssFeed(container, widgetId, url);
}

async function loadRssFeed(container, widgetId, url) {
  rssFetchVersion[widgetId] = (rssFetchVersion[widgetId] || 0) + 1;
  const myVersion = rssFetchVersion[widgetId];

  // Cache JS : affichage instantané sans appel réseau
  const cacheKey = `${widgetId}__${url}`;
  if (rssPageCache[cacheKey]) {
    renderRssFeedData(container, widgetId, rssPageCache[cacheKey]);
    return;
  }

  container.innerHTML = `<div class="flex items-center gap-2 text-white/30 text-sm py-4">
    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
    </svg> Chargement…</div>`;

  try {
    const data = await apiFetch(
      `/api/v1/rss?widget_id=${widgetId}&url=${encodeURIComponent(url)}`,
      null, 'GET'
    );

    // Ignorer si un fetch plus récent a démarré entre-temps
    if (rssFetchVersion[widgetId] !== myVersion) return;

    rssPageCache[cacheKey] = data; // mémoriser pour les prochains switch
    renderRssFeedData(container, widgetId, data);

  } catch (e) {
    container.innerHTML = `<p class="text-red-400/60 text-sm py-2 text-center">Erreur : ${escHtml(e.message)}</p>`;
  }
}

function renderRssFeedData(container, widgetId, data) {
  const items    = data.items ?? data;
  const cachedAt = data.cached_at ?? null;

  const cacheLabel = container.closest('.relative')?.querySelector(`.rss-cached-at[data-widget-id="${widgetId}"]`);
  if (cacheLabel && cachedAt) {
    const d = new Date(cachedAt.replace(' ', 'T'));
    const diffMin = Math.round((Date.now() - d) / 60000);
    let label;
    if (diffMin < 1)         label = 'à l\'instant';
    else if (diffMin < 60)   label = `il y a ${diffMin} min`;
    else if (diffMin < 1440) label = `il y a ${Math.round(diffMin / 60)}h`;
    else                     label = d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    cacheLabel.textContent = label;
    cacheLabel.title = cachedAt;
  }

  if (!items.length) {
    container.innerHTML = '<p class="text-white/30 text-sm py-4 text-center">Aucun article disponible.</p>';
    return;
  }

  container.innerHTML = items.map(item => {
    const date = item.date
      ? new Date(item.date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
      : '';
    return `<a href="${escHtml(item.link)}" target="_blank" rel="noopener" class="rss-item block">
      <h4>${escHtml(item.title)}</h4>
      ${date ? `<div class="rss-date">${escHtml(date)}</div>` : ''}
      ${item.desc ? `<div class="rss-desc">${escHtml(item.desc)}</div>` : ''}
    </a>`;
  }).join('<hr class="border-white/5 my-0.5">');
}

function switchRssTab(btn, widgetId) {
  const url = btn.dataset.feedUrl;
  const container = document.querySelector(`.rss-feed-container[data-widget-id="${widgetId}"]`);
  if (!container) return;

  // Mettre à jour les onglets visuellement
  document.querySelectorAll(`.rss-tab[data-widget-id="${widgetId}"]`).forEach(t => {
    t.className = 'rss-tab px-2.5 py-1 rounded-lg text-xs border transition bg-white/5 text-white/50 border-white/10 hover:bg-white/10 hover:text-white';
  });
  btn.className = 'rss-tab px-2.5 py-1 rounded-lg text-xs border transition bg-brand/30 text-brand border-brand/50';

  container.dataset.currentUrl = url;
  loadRssFeed(container, widgetId, url);
}

// ----------------------------------------------------------------
// Météo
// ----------------------------------------------------------------
function toggleWeatherSearch(widgetId) {
  const form = document.getElementById(`weather-form-${widgetId}`);
  form.classList.toggle('hidden');
  if (!form.classList.contains('hidden')) {
    const input = document.getElementById(`weather-search-${widgetId}`);
    input.focus();
    setupWeatherAutocomplete(widgetId);
  } else {
    closeWeatherDropdown(widgetId);
  }
}

function setupWeatherAutocomplete(widgetId) {
  const input = document.getElementById(`weather-search-${widgetId}`);
  if (input.dataset.acReady) return;
  input.dataset.acReady = '1';

  let timer;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) { closeWeatherDropdown(widgetId); return; }
    timer = setTimeout(() => fetchWeatherSuggestions(widgetId, q), 300);
  });

  input.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeWeatherDropdown(widgetId);
      document.getElementById(`weather-form-${widgetId}`).classList.add('hidden');
    }
  });

  document.addEventListener('click', e => {
    const form = document.getElementById(`weather-form-${widgetId}`);
    if (form && !form.contains(e.target)) closeWeatherDropdown(widgetId);
  }, { passive: true });
}

async function fetchWeatherSuggestions(widgetId, query) {
  try {
    const url = `https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(query)}&count=6&language=fr&format=json`;
    const res  = await fetch(url);
    const data = await res.json();
    renderWeatherDropdown(widgetId, data.results || []);
  } catch {
    closeWeatherDropdown(widgetId);
  }
}

function renderWeatherDropdown(widgetId, results) {
  closeWeatherDropdown(widgetId);
  if (!results.length) return;

  const form = document.getElementById(`weather-form-${widgetId}`);
  form.style.position = 'relative';

  const dropdown = document.createElement('div');
  dropdown.id = `weather-ac-${widgetId}`;
  dropdown.style.cssText = 'position:absolute;top:100%;left:0;right:0;margin-top:4px;z-index:50;border-radius:12px;overflow:hidden;background:rgba(15,23,42,0.97);border:1px solid rgba(255,255,255,0.12);backdrop-filter:blur(20px)';

  results.forEach(r => {
    const sub = [r.admin1, r.country].filter(Boolean).join(', ');
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.style.cssText = 'display:flex;align-items:center;gap:8px;width:100%;text-align:left;padding:8px 12px;background:transparent;border:none;cursor:pointer;transition:background .15s';
    btn.innerHTML = `<span style="color:#fff;font-size:.875rem;font-weight:500;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(r.name)}</span>`
                  + `<span style="color:rgba(255,255,255,.35);font-size:.75rem;white-space:nowrap">${escHtml(sub)}</span>`;
    btn.addEventListener('mouseenter', () => btn.style.background = 'rgba(255,255,255,.08)');
    btn.addEventListener('mouseleave', () => btn.style.background = 'transparent');
    btn.addEventListener('click', () => selectWeatherLocation(widgetId, r.latitude, r.longitude, r.name));
    dropdown.appendChild(btn);
  });

  form.appendChild(dropdown);
}

function closeWeatherDropdown(widgetId) {
  document.getElementById(`weather-ac-${widgetId}`)?.remove();
}

function selectWeatherLocation(widgetId, lat, lon, name) {
  closeWeatherDropdown(widgetId);
  const input = document.getElementById(`weather-search-${widgetId}`);
  input.value = '';
  document.getElementById(`weather-form-${widgetId}`).classList.add('hidden');
  loadWeather(widgetId, null, lat, lon, name);
}

// searchWeather (fallback soumission formulaire sans sélection dropdown)
function searchWeather(e, widgetId) {
  e.preventDefault();
  const input = document.getElementById(`weather-search-${widgetId}`);
  const city  = input.value.trim();
  if (!city) return;
  closeWeatherDropdown(widgetId);
  document.getElementById(`weather-form-${widgetId}`).classList.add('hidden');
  input.value = '';
  loadWeather(widgetId, city);
}

async function loadWeather(widgetId, city = null, lat = null, lon = null, name = null) {
  const container = document.querySelector(`.weather-container[data-widget-id="${widgetId}"]`);
  if (!container) return;
  container.innerHTML = '<div class="text-white/30 text-sm py-4 text-center">⏳ Chargement météo…</div>';

  try {
    let url;
    if (lat !== null && lon !== null) {
      url = `/api/v1/weather?widget_id=${widgetId}&lat=${lat}&lon=${lon}&name=${encodeURIComponent(name || '')}`;
    } else {
      url = `/api/v1/weather?widget_id=${widgetId}&city=${encodeURIComponent(city)}`;
    }
    const d = await apiFetch(url, null, 'GET');

    const forecastHtml = d.forecast.map((day, i) => {
      const label = i === 0 ? 'Auj.' : new Date(day.date + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'short' });
      return `<div class="forecast-day">
        <div>${escHtml(day.emoji)}</div>
        <div class="font-medium text-white/80">${day.max}°</div>
        <div>${day.min}°</div>
        <div class="text-white/30 text-[10px]">${escHtml(label)}</div>
      </div>`;
    }).join('');

    container.innerHTML = `
      <div class="flex flex-col gap-2 h-full">
        <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold text-white">
              ${escHtml(d.city)}${d.postal ? ` <span class="text-white/40 text-xs">(${escHtml(d.postal)})</span>` : ''}
            </div>
            <div class="text-xs text-white/40">
              ${d.admin ? escHtml(d.admin) + ' · ' : ''}${escHtml(d.country)}
            </div>
            <div class="text-xs text-white/50 mt-0.5">${escHtml(d.current.desc)}</div>
          </div>
          <div class="text-right">
            <div class="weather-temp">${d.current.temp}°</div>
            <div class="text-xs text-white/40">Ressenti ${d.current.feels_like}°</div>
          </div>
          <div class="weather-emoji">${escHtml(d.current.emoji)}</div>
        </div>
        <div class="text-xs text-white/40 flex gap-3">
          <span>💧 ${d.current.humidity}%</span>
          <span>💨 ${d.current.wind} km/h</span>
        </div>
        <div class="flex gap-1 mt-auto pt-2 border-t border-white/10">
          ${forecastHtml}
        </div>
      </div>`;
  } catch (e) {
    container.innerHTML = `<p class="text-red-400/60 text-sm py-2 text-center">Ville introuvable</p>`;
  }
}

// ----------------------------------------------------------------
// Horloge
// ----------------------------------------------------------------
function updateClock(el) {
  const now = new Date();
  el.textContent = now.toLocaleTimeString('fr-FR');
  const dateEl = el.parentElement?.querySelector('.clock-date');
  if (dateEl) {
    dateEl.textContent = now.toLocaleDateString('fr-FR', {
      weekday: 'long', day: 'numeric', month: 'long'
    });
  }
}

// ----------------------------------------------------------------
// Notes
// ----------------------------------------------------------------
let noteSaveTimers = {};

function debounceSaveNote(widgetId, content) {
  clearTimeout(noteSaveTimers[widgetId]);
  noteSaveTimers[widgetId] = setTimeout(() => saveNote(widgetId, content), 1000);
}

async function saveNote(widgetId, content) {
  await apiFetch('/api/v1/notes', { widget_id: widgetId, content });
}

// ----------------------------------------------------------------
// Todos
// ----------------------------------------------------------------
async function addTodo(e, widgetId) {
  e.preventDefault();
  const input = e.target.querySelector('input');
  const title = input.value.trim();
  if (!title) return;

  const res = await apiFetch('/api/v1/todos', { widget_id: widgetId, title });
  if (res.id) {
    const list = document.querySelector(`.todo-list[data-widget-id="${widgetId}"]`);
    if (list) {
      const label = document.createElement('label');
      label.className = 'flex items-center gap-2.5 py-1 px-1 rounded-lg hover:bg-white/5 cursor-pointer group';
      label.innerHTML = `
        <input type="checkbox" class="accent-indigo-500 w-4 h-4 flex-shrink-0"
          onchange="toggleTodo(${res.id}, this)">
        <span class="text-sm flex-1 text-white/80">${escHtml(title)}</span>
        <button onclick="deleteTodo(${res.id}, this.closest('label'))"
          class="opacity-0 group-hover:opacity-100 text-white/30 hover:text-red-400 transition text-xs">✕</button>`;
      list.appendChild(label);
    }
    input.value = '';
  }
}

async function toggleTodo(id, checkbox) {
  const span = checkbox.parentElement.querySelector('span');
  await apiFetch(`/api/v1/todos/${id}`, {}, 'PUT');
  if (checkbox.checked) {
    span.classList.add('line-through', 'text-white/30');
    span.classList.remove('text-white/80');
  } else {
    span.classList.remove('line-through', 'text-white/30');
    span.classList.add('text-white/80');
  }
}

async function deleteTodo(id, label) {
  await apiFetch(`/api/v1/todos/${id}`, null, 'DELETE');
  label.remove();
}

async function deleteBookmark(id, el) {
  await apiFetch(`/api/v1/bookmarks/${id}`, null, 'DELETE');
  el.remove();
}

// ----------------------------------------------------------------
// Drag & drop bookmarks (SortableJS)
// ----------------------------------------------------------------
function initBookmarksSortable() {
  document.querySelectorAll('.bookmarks-list').forEach(list => {
    Sortable.create(list, {
      animation:   150,
      handle:      '.bm-handle',     // poignée visible au survol (mode liste)
      fallbackOnBody: true,
      ghostClass:  'opacity-20',
      chosenClass: 'opacity-60',
      onEnd: async () => {
        const widgetId = parseInt(list.dataset.widgetId);
        const order    = [...list.children].map(el => parseInt(el.dataset.id)).filter(Boolean);
        await apiFetch('/api/v1/bookmarks/reorder', { widget_id: widgetId, order });
      },
    });
  });
}

// ----------------------------------------------------------------
// Thème clair / sombre
// ----------------------------------------------------------------
function applyTheme(theme) {
  const html   = document.documentElement;
  const btn    = document.getElementById('theme-toggle');
  if (theme === 'light') {
    html.classList.add('light');
    html.classList.remove('dark');
    if (btn) btn.textContent = '🌙';
  } else {
    html.classList.remove('light');
    html.classList.add('dark');
    if (btn) btn.textContent = '☀️';
  }
}

function toggleTheme() {
  const current = localStorage.getItem('theme') || 'dark';
  const next    = current === 'dark' ? 'light' : 'dark';
  localStorage.setItem('theme', next);
  applyTheme(next);
}

// Appliquer immédiatement au chargement (avant initGrid)
applyTheme(localStorage.getItem('theme') || 'dark');

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------
async function logout() {
  await apiFetch('/api/v1/auth/logout', {});
  window.location.href = BASE_URL + '/auth.php';
}

// ----------------------------------------------------------------
// Utilitaires
// ----------------------------------------------------------------
async function apiFetch(path, body = null, method = null) {
  const opts = {
    method: method || (body !== null ? 'POST' : 'GET'),
    headers: {},
  };
  if (body !== null) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(BASE_URL + path, opts);
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return data;
}

function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function debounce(fn, delay) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}
