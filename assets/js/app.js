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

  // Countdown
  document.querySelectorAll('[data-widget-type="countdown"]').forEach(el => {
    const cfg = JSON.parse(el.querySelector('[data-countdown-config]')?.dataset.countdownConfig || '{}');
    initCountdown(el.dataset.widgetId, cfg);
  });

  // Crypto
  document.querySelectorAll('[data-widget-type="crypto"]').forEach(el => {
    const cfg = JSON.parse(el.querySelector('.crypto-container')?.dataset.cryptoConfig || '{}');
    initCryptoWidget(el.dataset.widgetId, cfg);
  });

  // Pomodoro
  document.querySelectorAll('[data-widget-type="pomodoro"]').forEach(el => {
    const wid = el.dataset.widgetId;
    const cfg = JSON.parse(el.querySelector('[data-pomodoro-config]')?.dataset.pomodoroConfig || '{}');
    initPomodoro(wid, cfg);
  });

  // GitHub / GitLab
  document.querySelectorAll('[data-widget-type="github"]').forEach(el => {
    const wid = el.dataset.widgetId;
    const cfg = JSON.parse(el.querySelector('[data-github-config]')?.dataset.githubConfig || '{}');
    initGithubWidget(wid, cfg);
  });

  // Auto-refresh iframes embed
  document.querySelectorAll('[data-widget-type="embed"] iframe[data-refresh]').forEach(iframe => {
    const seconds = parseInt(iframe.dataset.refresh, 10);
    if (seconds > 0) {
      setInterval(() => { iframe.src = iframe.src; }, seconds * 1000);
    }
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
  showToast('Disposition sauvegardée');
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

  const ac = new AbortController();
  // Stocker l'AbortController pour pouvoir nettoyer les listeners plus tard
  input._acController = ac;

  let timer;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) { closeWeatherDropdown(widgetId); return; }
    timer = setTimeout(() => fetchWeatherSuggestions(widgetId, q), 300);
  }, { signal: ac.signal });

  input.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeWeatherDropdown(widgetId);
      document.getElementById(`weather-form-${widgetId}`).classList.add('hidden');
    }
  }, { signal: ac.signal });

  document.addEventListener('click', e => {
    const form = document.getElementById(`weather-form-${widgetId}`);
    if (form && !form.contains(e.target)) closeWeatherDropdown(widgetId);
  }, { passive: true, signal: ac.signal });
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
      const label = i === 0 ? 'Auj.' : new Date(day.date + 'T12:00:00Z').toLocaleDateString('fr-FR', { weekday: 'short' });
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
  showToast('Note sauvegardée');
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
// Toast
// ----------------------------------------------------------------
function showToast(message, type = 'success') {
  const colors = {
    success: 'bg-green-500/90 text-white',
    error:   'bg-red-500/90 text-white',
    info:    'bg-white/20 backdrop-blur-sm text-white',
  };
  const toast = document.createElement('div');
  toast.className = `fixed bottom-5 right-5 z-[300] px-4 py-2.5 rounded-xl text-sm font-medium
    shadow-xl pointer-events-none transition-all duration-300 ${colors[type] || colors.success}`;
  toast.style.cssText = 'transform:translateY(8px);opacity:0';
  toast.textContent = message;
  document.body.appendChild(toast);

  requestAnimationFrame(() => {
    toast.style.transform = 'translateY(0)';
    toast.style.opacity   = '1';
  });
  setTimeout(() => {
    toast.style.transform = 'translateY(8px)';
    toast.style.opacity   = '0';
    setTimeout(() => toast.remove(), 300);
  }, 2200);
}

// ----------------------------------------------------------------
// Pomodoro
// ----------------------------------------------------------------
function initPomodoro(widgetId, cfg) {
  const workMin        = (cfg.work_minutes       || 25) * 60;
  const shortBreakMin  = (cfg.break_minutes      || 5)  * 60;
  const longBreakMin   = (cfg.long_break_minutes || 15) * 60;
  const longBreakEvery = cfg.long_break_every    || 4;
  const STORAGE_KEY    = `pomo_${widgetId}`;

  const root      = document.querySelector(`[data-widget-id="${widgetId}"]`);
  const display   = root?.querySelector('.pomo-display');
  const modeEl    = root?.querySelector('.pomo-mode');
  const sessEl    = root?.querySelector('.pomo-sessions');
  const btnToggle = root?.querySelector('.pomo-toggle');
  if (!display || !btnToggle) return;

  let state     = 'work';
  let sessions  = 0;
  let remaining = workMin;
  let running   = false;
  let interval  = null;

  const durations  = () => ({ work: workMin, 'short-break': shortBreakMin, 'long-break': longBreakMin });
  const modeLabels = () => ({ work: '🍅 Travail', 'short-break': '☕ Pause courte', 'long-break': '🛋️ Pause longue' });

  // --- Persistance localStorage ---
  function saveState() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      state, sessions, remaining, running,
      savedAt: running ? Date.now() : null,  // timestamp si en cours, null si en pause
    }));
  }

  function loadState() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      const s = JSON.parse(raw);

      state    = s.state    || 'work';
      sessions = s.sessions || 0;

      if (s.running && s.savedAt) {
        // Timer tournait → recalculer le temps écoulé
        const elapsed = Math.floor((Date.now() - s.savedAt) / 1000);
        remaining = Math.max(0, (s.remaining || workMin) - elapsed);
        if (remaining <= 0) {
          // Phase terminée pendant l'absence → passer à la suivante (en pause)
          if (state === 'work') {
            sessions++;
            state = sessions % longBreakEvery === 0 ? 'long-break' : 'short-break';
          } else {
            state = 'work';
          }
          remaining = durations()[state];
          running = false;
          showToast(modeLabels()[state] + ' (terminé pendant ton absence)', 'info');
        } else {
          // Phase encore en cours → reprendre automatiquement
          running = true;
          interval = setInterval(() => {
            if (remaining <= 0) { nextPhase(); return; }
            remaining--;
            render();
            saveState();
          }, 1000);
        }
      } else {
        remaining = s.remaining ?? durations()[state];
        running   = false;
      }
    } catch {}
  }

  // --- Rendu ---
  function render() {
    const m = Math.floor(remaining / 60).toString().padStart(2, '0');
    const s = (remaining % 60).toString().padStart(2, '0');
    display.textContent = `${m}:${s}`;
    modeEl.textContent  = modeLabels()[state];

    const dots = Array.from({ length: longBreakEvery }, (_, i) =>
      i < (sessions % longBreakEvery)
        ? '<span class="inline-block w-2.5 h-2.5 rounded-full bg-brand"></span>'
        : '<span class="inline-block w-2.5 h-2.5 rounded-full bg-white/20"></span>'
    ).join('');
    sessEl.innerHTML = dots;

    btnToggle.textContent = running ? '⏸' : '▶';
    display.style.color = state === 'work' ? '#f87171' :
                          state === 'short-break' ? '#34d399' : '#60a5fa';
  }

  function nextPhase() {
    clearInterval(interval);
    running = false;
    if (state === 'work') {
      sessions++;
      state = sessions % longBreakEvery === 0 ? 'long-break' : 'short-break';
    } else {
      state = 'work';
    }
    remaining = durations()[state];
    render();
    saveState();
    showToast(modeLabels()[state] + ' !', 'info');
  }

  btnToggle.addEventListener('click', () => {
    if (running) {
      clearInterval(interval);
      running = false;
    } else {
      running = true;
      interval = setInterval(() => {
        if (remaining <= 0) { nextPhase(); return; }
        remaining--;
        render();
        saveState();
      }, 1000);
    }
    render();
    saveState();
  });

  root.querySelector('.pomo-skip')?.addEventListener('click', nextPhase);
  root.querySelector('.pomo-reset')?.addEventListener('click', () => {
    clearInterval(interval);
    running = false; state = 'work'; sessions = 0; remaining = workMin;
    render();
    saveState();
  });

  // Sauvegarder avant de quitter la page
  window.addEventListener('beforeunload', saveState);

  loadState();
  render();
}

// ----------------------------------------------------------------
// Countdown
// ----------------------------------------------------------------
function initCountdown(widgetId, cfg) {
  const target = new Date(cfg.target_date);
  if (isNaN(target)) return;
  const showSecs = cfg.show_seconds !== false;
  const root = document.querySelector(`[data-widget-id="${widgetId}"]`);
  if (!root) return;

  const daysEl  = root.querySelector('.countdown-days');
  const hoursEl = root.querySelector('.countdown-hours');
  const minsEl  = root.querySelector('.countdown-mins');
  const secsEl  = root.querySelector('.countdown-secs');
  const doneEl  = root.querySelector('.countdown-done');
  const dispEl  = root.querySelector('.countdown-display');

  function update() {
    const diff = target - Date.now();
    if (diff <= 0) {
      dispEl?.classList.add('hidden');
      doneEl?.classList.remove('hidden');
      clearInterval(timer);
      return;
    }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    if (daysEl)          daysEl.textContent  = String(d).padStart(2, '0');
    if (hoursEl)         hoursEl.textContent = String(h).padStart(2, '0');
    if (minsEl)          minsEl.textContent  = String(m).padStart(2, '0');
    if (secsEl && showSecs) secsEl.textContent = String(s).padStart(2, '0');
  }

  update();
  const timer = setInterval(update, 1000);
}

// ----------------------------------------------------------------
// Crypto
// ----------------------------------------------------------------
const COIN_META = {
  bitcoin:       { symbol: 'BTC', color: '#f7931a', char: '₿' },
  ethereum:      { symbol: 'ETH', color: '#627eea', char: 'Ξ' },
  solana:        { symbol: 'SOL', color: '#9945ff', char: '◎' },
  cardano:       { symbol: 'ADA', color: '#0033ad', char: '₳' },
  dogecoin:      { symbol: 'DOGE',color: '#c2a633', char: 'Ð' },
  ripple:        { symbol: 'XRP', color: '#346aa9', char: '✕' },
  polkadot:      { symbol: 'DOT', color: '#e6007a', char: '●' },
  chainlink:     { symbol: 'LINK',color: '#2a5ada', char: '⬡' },
  avalanche2:    { symbol: 'AVAX',color: '#e84142', char: '▲' },
  'matic-network':{ symbol: 'MATIC',color: '#8247e5', char: '⬡' },
};

async function initCryptoWidget(widgetId, cfg) {
  const root      = document.querySelector(`[data-widget-id="${widgetId}"][data-widget-type="crypto"]`);
  const container = root?.querySelector('.crypto-container');
  if (!container) return;

  const coins    = (cfg.coins || ['bitcoin', 'ethereum']).join(',');
  const currency = cfg.currency || 'eur';
  const sym      = { eur: '€', usd: '$', gbp: '£', btc: '₿' }[currency] || currency.toUpperCase();

  try {
    const d    = await apiFetch(`/api/v1/crypto?coins=${encodeURIComponent(coins)}&currency=${currency}`, null, 'GET');
    const data = d.data || {};

    container.innerHTML = Object.entries(data).map(([id, prices]) => {
      const meta   = COIN_META[id] || { symbol: id.toUpperCase().slice(0, 5), color: '#6366f1', char: '●' };
      const price  = prices[currency];
      const change = prices[`${currency}_24h_change`];
      const pos    = change >= 0;
      const priceStr = price >= 1000
        ? price.toLocaleString('fr-FR', { maximumFractionDigits: 0 })
        : price.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 4 });

      return `<div class="flex items-center gap-3 px-2 py-2.5 rounded-xl hover:bg-white/5 transition">
        <div class="w-9 h-9 rounded-full flex items-center justify-center text-base font-bold flex-shrink-0"
             style="background:${meta.color}22;color:${meta.color}">${meta.char}</div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold text-white/90">${meta.symbol}</div>
          <div class="text-xs text-white/30 capitalize">${id}</div>
        </div>
        <div class="text-right">
          <div class="text-sm font-mono font-semibold text-white/90">${priceStr} ${sym}</div>
          <div class="text-xs font-mono ${pos ? 'text-green-400' : 'text-red-400'}">
            ${pos ? '▲' : '▼'} ${Math.abs(change ?? 0).toFixed(2)}%
          </div>
        </div>
      </div>`;
    }).join('');
  } catch (e) {
    container.innerHTML = `<p class="text-red-400/70 text-xs text-center py-4">Erreur : ${escHtml(e.message)}</p>`;
  }
}

// ----------------------------------------------------------------
// Lofi Radio
// ----------------------------------------------------------------
function toggleLofi(widgetId) {
  const root   = document.querySelector(`[data-widget-id="${widgetId}"][data-widget-type="lofi"]`);
  const cover  = root?.querySelector('.lofi-cover');
  const player = root?.querySelector('.lofi-player');
  const btn    = root?.querySelector('.lofi-play-btn');
  const ui     = root?.querySelector('.lofi-ui');
  if (!player) return;

  const playing = !player.classList.contains('hidden');
  if (playing) {
    player.classList.add('hidden');
    cover?.classList.remove('opacity-0');
    if (btn) btn.textContent = '▶';
    if (ui)  ui.style.opacity = '1';
  } else {
    // Charger l'iframe seulement au premier clic (lazy)
    const iframe = player.querySelector('iframe');
    if (iframe && !iframe.src && iframe.dataset.src) {
      iframe.src = iframe.dataset.src;
    }
    player.classList.remove('hidden');
    cover?.classList.add('opacity-0');
    if (btn) btn.textContent = '⏸';
    if (ui)  ui.style.opacity = '0';
  }
}

// ----------------------------------------------------------------
// Ctrl+K — Palette de commande
// ----------------------------------------------------------------
let paletteSelected = 0;

function buildPaletteItems(query) {
  const q = query.toLowerCase().trim();
  const items = [];

  // Pages
  if (typeof PAGES_NAV !== 'undefined') {
    PAGES_NAV.forEach(p => {
      if (!q || p.name.toLowerCase().includes(q)) {
        items.push({ icon: p.icon, label: p.name, sub: 'Page', action: () => { window.location.href = BASE_URL + '/p/' + p.slug; } });
      }
    });
  }

  // Bookmarks depuis le DOM
  document.querySelectorAll('.bookmarks-list a[href], a[href][class*="bookmark"]').forEach(a => {
    const title = a.querySelector('span')?.textContent?.trim() || a.href;
    if (!q || title.toLowerCase().includes(q) || a.href.toLowerCase().includes(q)) {
      const href = a.href;
      items.push({ icon: '🔖', label: title, sub: a.hostname || href, action: () => window.open(href, '_blank') });
    }
  });

  // Actions fixes
  const actions = [
    { icon: '✏️', label: 'Administrer', sub: 'Admin', action: () => { window.location.href = BASE_URL + '/admin.php'; } },
    { icon: '🔒', label: 'Déconnexion', sub: '', action: logout },
  ];
  actions.forEach(a => { if (!q || a.label.toLowerCase().includes(q)) items.push(a); });

  return items.slice(0, 8);
}

function renderPaletteItems(items) {
  const el = document.getElementById('palette-results');
  if (!el) return;
  paletteSelected = 0;

  if (!items.length) {
    el.innerHTML = '<p class="text-white/30 text-sm text-center py-4">Aucun résultat</p>';
    return;
  }

  el.innerHTML = items.map((item, i) => `
    <div class="palette-item flex items-center gap-3 px-4 py-2.5 cursor-pointer transition
                ${i === 0 ? 'bg-white/10' : 'hover:bg-white/5'}"
         data-index="${i}" onclick="paletteActivate(${i})">
      <span class="text-lg w-6 text-center flex-shrink-0">${escHtml(item.icon)}</span>
      <div class="flex-1 min-w-0">
        <div class="text-sm text-white/90 truncate">${escHtml(item.label)}</div>
        ${item.sub ? `<div class="text-xs text-white/30 truncate">${escHtml(item.sub)}</div>` : ''}
      </div>
    </div>`).join('');
}

let _paletteItems = [];
function filterPalette(query) {
  _paletteItems = buildPaletteItems(query);
  renderPaletteItems(_paletteItems);
}

function paletteActivate(index) {
  _paletteItems[index]?.action?.();
  closePalette();
}

function palettekeydown(e) {
  const items = document.querySelectorAll('.palette-item');
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    paletteSelected = Math.min(paletteSelected + 1, items.length - 1);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    paletteSelected = Math.max(paletteSelected - 1, 0);
  } else if (e.key === 'Enter') {
    paletteActivate(paletteSelected);
    return;
  } else if (e.key === 'Escape') {
    closePalette();
    return;
  }
  items.forEach((el, i) => el.classList.toggle('bg-white/10', i === paletteSelected));
  items[paletteSelected]?.scrollIntoView({ block: 'nearest' });
}

function openPalette() {
  const overlay = document.getElementById('palette-overlay');
  if (!overlay) return;
  overlay.classList.remove('hidden');
  overlay.classList.add('flex');
  const input = document.getElementById('palette-input');
  if (input) { input.value = ''; input.focus(); }
  _paletteItems = buildPaletteItems('');
  renderPaletteItems(_paletteItems);
}

function closePalette() {
  const overlay = document.getElementById('palette-overlay');
  if (!overlay) return;
  overlay.classList.add('hidden');
  overlay.classList.remove('flex');
}

// Raccourcis globaux
document.addEventListener('keydown', e => {
  // Ctrl+K ou Cmd+K → palette
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    const overlay = document.getElementById('palette-overlay');
    if (overlay?.classList.contains('hidden')) openPalette();
    else closePalette();
    return;
  }

  // Touches 1-9 → navigation pages (hors inputs)
  if (e.target.matches('input, textarea, select, [contenteditable]')) return;
  if (!e.ctrlKey && !e.metaKey && !e.altKey && e.key >= '1' && e.key <= '9') {
    const idx = parseInt(e.key) - 1;
    if (typeof PAGES_NAV !== 'undefined' && PAGES_NAV[idx]) {
      window.location.href = BASE_URL + '/p/' + PAGES_NAV[idx].slug;
    }
  }
});

// ----------------------------------------------------------------
// Search bangs (!yt, !gh, !w…)
// ----------------------------------------------------------------
function handleSearch(e, form) {
  const input = form.querySelector('input[name="q"]');
  const val   = (input?.value || '').trim();
  const bang  = val.match(/^!(\S+)\s*(.*)/s);

  if (bang) {
    e.preventDefault();
    const key   = bang[1].toLowerCase();
    const query = bang[2].trim();
    const bangs = {
      g:    'https://www.google.com/search?q=',
      yt:   'https://www.youtube.com/results?search_query=',
      gh:   'https://github.com/search?q=',
      gl:   'https://gitlab.com/search?search=',
      w:    'https://fr.wikipedia.org/wiki/Special:Search?search=',
      r:    'https://www.reddit.com/search/?q=',
      npm:  'https://www.npmjs.com/search?q=',
      dd:   'https://duckduckgo.com/?q=',
      x:    'https://x.com/search?q=',
      maps: 'https://maps.google.com/?q=',
      img:  'https://www.google.com/search?tbm=isch&q=',
    };
    const url = bangs[key];
    window.open((url || 'https://www.google.com/search?q=' + encodeURIComponent(val))
                + encodeURIComponent(query), '_blank');
    if (input) input.value = '';
  }
  // Sinon, submit normal du form
}

// ----------------------------------------------------------------
// GitHub / GitLab
// ----------------------------------------------------------------
async function initGithubWidget(widgetId, cfg) {
  const username = (cfg.username || '').trim();
  const platform = cfg.platform || 'github';
  const root     = document.querySelector(`[data-widget-id="${widgetId}"][data-widget-type="github"]`);
  const container = root?.querySelector('.github-container');
  if (!container || !username) {
    if (container) container.innerHTML = '<p class="text-white/30 text-sm text-center py-6">Configurez un nom d\'utilisateur.</p>';
    return;
  }

  try {
    if (platform === 'gitlab') {
      await loadGitlabActivity(container, username);
    } else {
      await loadGithubActivity(container, username);
    }
  } catch (e) {
    container.innerHTML = `<p class="text-red-400/70 text-xs text-center py-4">Erreur : ${escHtml(e.message)}</p>`;
  }
}

async function loadGithubActivity(container, username) {
  // Heatmap via proxy PHP (inclut les contributions privées si activées sur le profil GitHub)
  const [contribRes, profileRes, eventsRes] = await Promise.all([
    apiFetch(`/api/v1/github?username=${encodeURIComponent(username)}`, null, 'GET'),
    fetch(`https://api.github.com/users/${encodeURIComponent(username)}`).then(r => r.ok ? r.json() : {}),
    fetch(`https://api.github.com/users/${encodeURIComponent(username)}/events?per_page=30`).then(r => r.ok ? r.json() : []),
  ]);

  const activityMap = contribRes.contributions || {};
  const profile     = profileRes || {};
  const events      = Array.isArray(eventsRes) ? eventsRes : [];
  const recent      = parseGithubEvents(events.slice(0, 6));
  const total       = contribRes.total ?? 0;
  const hasPrivate  = profile.private_gists !== undefined; // indicateur qu'on a des données complètes

  container.innerHTML = `
    <div class="flex items-center gap-2 mb-3">
      ${profile.avatar_url ? `<img src="${escHtml(profile.avatar_url)}" class="w-7 h-7 rounded-full">` : '<span class="text-lg">⚫</span>'}
      <a href="https://github.com/${encodeURIComponent(username)}" target="_blank"
         class="text-sm font-semibold text-white/90 hover:text-brand transition">${escHtml(username)}</a>
      <span class="text-xs text-white/30 ml-auto" title="Contributions sur 1 an">${total > 0 ? total + ' contrib.' : ''}</span>
    </div>
    ${renderHeatmap(activityMap, '#6366f1')}
    ${recent.length ? `<div class="mt-3 space-y-1">${recent.map(e => `
      <div class="flex items-start gap-1.5 text-xs text-white/60">
        <span class="flex-shrink-0">${e.icon}</span>
        <span class="truncate">${escHtml(e.text)}</span>
        <span class="flex-shrink-0 text-white/25 ml-auto">${e.when}</span>
      </div>`).join('')}
    </div>` : ''}`;
}

async function loadGitlabActivity(container, username) {
  // Proxy PHP : cache 1h, évite le CORS et les requêtes répétées à GitLab
  const d = await apiFetch(`/api/v1/github?username=${encodeURIComponent(username)}&platform=gitlab`, null, 'GET');

  container.innerHTML = `
    <div class="flex items-center gap-2 mb-3">
      ${d.avatar ? `<img src="${escHtml(d.avatar)}" class="w-7 h-7 rounded-full">` : '<span class="text-lg">🦊</span>'}
      <a href="https://gitlab.com/${encodeURIComponent(username)}" target="_blank"
         class="text-sm font-semibold text-white/90 hover:text-orange-400 transition">${escHtml(username)}</a>
      <span class="text-xs text-white/30 ml-auto" title="Contributions sur 1 an">${d.total > 0 ? d.total + ' contrib.' : 'GitLab'}</span>
    </div>
    ${renderHeatmap(d.contributions || {}, '#fc6d26')}`;
}

function buildActivityMap(dates) {
  const map = {};
  dates.forEach(d => { if (d) map[d] = (map[d] || 0) + 1; });
  return map;
}

function renderHeatmap(activityMap, color) {
  const today  = new Date();
  const weeks  = 10;
  const days   = weeks * 7;
  const cells  = [];

  for (let i = days - 1; i >= 0; i--) {
    const d    = new Date(today);
    d.setDate(d.getDate() - i);
    const key  = d.toISOString().slice(0, 10);
    const cnt  = activityMap[key] || 0;
    const op   = cnt === 0 ? 0.07 : cnt < 3 ? 0.4 : cnt < 6 ? 0.7 : 1;
    cells.push(`<div title="${key}: ${cnt}" style="width:9px;height:9px;border-radius:2px;background:${color};opacity:${op};"></div>`);
  }

  return `<div style="display:grid;grid-template-rows:repeat(7,9px);grid-auto-flow:column;gap:2px;">${cells.join('')}</div>`;
}

function parseGithubEvents(events) {
  const icons = {
    PushEvent:       '📤', PullRequestEvent: '🔀', IssuesEvent: '🐛',
    WatchEvent:      '⭐', ForkEvent:        '🍴', CreateEvent: '🌿',
    DeleteEvent:     '🗑️', ReleaseEvent:     '🚀', CommitCommentEvent: '💬',
  };
  return events.map(e => {
    const icon = icons[e.type] || '📝';
    const repo = e.repo?.name?.split('/')[1] || e.repo?.name || '';
    const when = timeAgo(e.created_at);
    let text = '';
    if (e.type === 'PushEvent')        text = `Push → ${repo} (${e.payload?.commits?.length || 1} commit${(e.payload?.commits?.length || 1) > 1 ? 's' : ''})`;
    else if (e.type === 'PullRequestEvent') text = `PR #${e.payload?.pull_request?.number} ${e.payload?.action} → ${repo}`;
    else if (e.type === 'IssuesEvent') text = `Issue #${e.payload?.issue?.number} ${e.payload?.action} → ${repo}`;
    else if (e.type === 'WatchEvent')  text = `Starred ${repo}`;
    else if (e.type === 'ForkEvent')   text = `Forked ${repo}`;
    else if (e.type === 'CreateEvent') text = `Created ${e.payload?.ref_type} ${e.payload?.ref || ''} in ${repo}`;
    else                               text = `${e.type.replace('Event','')} in ${repo}`;
    return { icon, text, when };
  });
}

function timeAgo(iso) {
  const diff = Math.floor((Date.now() - new Date(iso)) / 1000);
  if (diff < 3600)  return Math.floor(diff / 60) + 'min';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h';
  return Math.floor(diff / 86400) + 'j';
}

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------
async function logout() {
  const ok = confirm('Vous allez vous déconnecter.\nVous devrez ressaisir votre phrase secrète (12 mots) pour vous reconnecter.\n\nContinuer ?');
  if (!ok) return;
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
