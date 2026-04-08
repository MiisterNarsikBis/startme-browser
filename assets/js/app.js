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
}

// ----------------------------------------------------------------
// Sauvegarde layout grille
// ----------------------------------------------------------------
async function saveGridLayout() {
  const items = grid.engine.nodes.map(n => ({
    id: n.el.getAttribute('gs-id'),
    x: n.x, y: n.y, w: n.w, h: n.h,
  }));

  await apiFetch('/api/widgets.php?action=move', { items });
}

// ----------------------------------------------------------------
// RSS — chargement et onglets
// ----------------------------------------------------------------
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
  container.innerHTML = `<div class="flex items-center gap-2 text-white/30 text-sm py-4">
    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
    </svg> Chargement…</div>`;

  try {
    const items = await apiFetch(
      `/api/rss.php?widget_id=${widgetId}&url=${encodeURIComponent(url)}`,
      null, 'GET'
    );

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

  } catch (e) {
    container.innerHTML = `<p class="text-red-400/60 text-sm py-2 text-center">Erreur : ${escHtml(e.message)}</p>`;
  }
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
async function loadWeather(widgetId, city) {
  const container = document.querySelector(`.weather-container[data-widget-id="${widgetId}"]`);
  if (!container) return;

  try {
    const d = await apiFetch(`/api/weather.php?widget_id=${widgetId}&city=${encodeURIComponent(city)}`, null, 'GET');

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
  await apiFetch('/api/bookmarks.php?action=save_note', { widget_id: widgetId, content });
}

// ----------------------------------------------------------------
// Todos
// ----------------------------------------------------------------
async function addTodo(e, widgetId) {
  e.preventDefault();
  const input = e.target.querySelector('input');
  const title = input.value.trim();
  if (!title) return;

  const res = await apiFetch('/api/bookmarks.php?action=todo_add', { widget_id: widgetId, title });
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
  await apiFetch(`/api/bookmarks.php?action=todo_toggle&id=${id}`, {});
  if (checkbox.checked) {
    span.classList.add('line-through', 'text-white/30');
    span.classList.remove('text-white/80');
  } else {
    span.classList.remove('line-through', 'text-white/30');
    span.classList.add('text-white/80');
  }
}

async function deleteTodo(id, label) {
  await apiFetch(`/api/bookmarks.php?action=todo_delete&id=${id}`, {});
  label.remove();
}

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------
async function logout() {
  await apiFetch('/api/auth.php?action=logout', {});
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
