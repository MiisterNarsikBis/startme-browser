/* ============================================================
   admin.js — Logique de la page d'administration
   ============================================================ */

'use strict';

const adminApp = {
  currentWidgetId: null,
  currentWidgetType: null,
  currentWidgetConfig: {},
  bgTab: 'color',

  // ----------------------------------------------------------
  // MODALS
  // ----------------------------------------------------------
  showModal(name) {
    document.getElementById('modal-overlay').classList.remove('hidden');
    document.querySelectorAll('.modal-panel').forEach(el => el.classList.add('hidden'));
    const panel = document.getElementById('modal-' + name);
    if (panel) panel.classList.remove('hidden');
    if (name === 'themes') this.renderThemes();
    if (name === 'page-settings') this.initPageSettingsForm();
  },

  closeModal(e) {
    if (e && e.target !== document.getElementById('modal-overlay')) return;
    document.getElementById('modal-overlay').classList.add('hidden');
    document.querySelectorAll('.modal-panel').forEach(el => el.classList.add('hidden'));
  },

  // ----------------------------------------------------------
  // WIDGETS — Ajout
  // ----------------------------------------------------------
  async addWidget(type) {
    // h en unités de 10px (cellHeight=10) : h:30 = 300px, h:40 = 400px
    const defaults = {
      bookmarks: { title: 'Favoris',    config: { display: 'grid', show_title: true }, w: 3, h: 30 },
      rss:       { title: 'Actualités', config: { feeds: [], max_items: 10 },          w: 3, h: 40 },
      notes:     { title: 'Notes',      config: {},                                    w: 3, h: 30 },
      todo:      { title: 'Tâches',     config: {},                                    w: 3, h: 30 },
      search:    { title: 'Recherche',  config: { engine: 'google' },                  w: 4, h: 10 },
      weather:   { title: 'Météo',      config: { city: '' },                          w: 3, h: 28 },
      clock:     { title: 'Horloge',   config: {},                                    w: 2, h: 16 },
      embed:     { title: 'Contenu',   config: { url: '' },                           w: 4, h: 40 },
      calendar:  { title: 'Calendrier',config: { ical_url: '' },                       w: 3, h: 24 },
      image:     { title: 'Image',     config: { url: '', fit: 'cover', caption: '' },                          w: 3, h: 30 },
      pomodoro:  { title: 'Pomodoro',  config: { work_minutes: 25, break_minutes: 5, long_break_minutes: 15, long_break_every: 4 }, w: 2, h: 28 },
      github:    { title: 'GitHub',    config: { username: '', platform: 'github' },                            w: 3, h: 36 },
      countdown: { title: 'Countdown', config: { target_date: '', label: 'Événement', show_seconds: true },     w: 3, h: 18 },
      crypto:    { title: 'Crypto',    config: { coins: ['bitcoin', 'ethereum'], currency: 'eur' },             w: 3, h: 24 },
      lofi:      { title: 'Lofi Radio',config: { video_id: 'jfKfPfyJRdk', label: 'Lofi Girl ☕' },             w: 2, h: 30 },
    };

    const def = defaults[type] || { title: type, config: {}, w: 3, h: 4 };

    // Trouver la position Y la plus basse pour éviter de décaler les widgets existants
    const bottomY = grid.engine.nodes.reduce((max, n) => Math.max(max, n.y + n.h), 0);

    try {
      const res = await apiFetch('/api/v1/widgets', {
        page_id: PAGE_ID,
        type,
        title:   def.title,
        config:  def.config,
        grid_x:  0,
        grid_y:  bottomY,
        grid_w:  def.w,
        grid_h:  def.h,
      });

      // Ajouter visuellement dans la grille sans reload
      const icons = { bookmarks:'🔖',rss:'📰',notes:'📝',todo:'✅',search:'🔍',weather:'🌤️',clock:'🕐',embed:'🖼️',calendar:'📅',image:'🖼️' };

      const content = `
        <div class="widget h-full flex flex-col rounded-2xl overflow-hidden relative"
             style="background:rgba(15,23,42,0.80);backdrop-filter:blur(16px);border:2px solid rgba(99,102,241,0.3);"
             data-widget-id="${res.id}" data-widget-type="${type}">
          <div class="widget-header flex items-center gap-2 px-3 py-2 border-b border-white/10 cursor-move"
               style="background:rgba(99,102,241,0.15);">
            <span class="text-xs text-white/40">⠿</span>
            <span class="font-semibold text-sm text-white/90 flex-1">${escHtml(def.title)}</span>
            <span class="text-xs text-white/40 bg-white/10 px-2 py-0.5 rounded-full">${type}</span>
            <button onclick="adminApp.editWidgetFromEl(this)"
              data-widget-id="${res.id}"
              data-widget-type="${escHtml(type)}"
              data-widget-config="${escHtml(JSON.stringify(def.config))}"
              data-widget-title="${escHtml(def.title)}"
              class="p-1.5 rounded-lg hover:bg-white/20 text-white/50 hover:text-white transition text-xs">⚙️</button>
            <button onclick="adminApp.deleteWidget(${res.id})"
              class="p-1.5 rounded-lg hover:bg-red-500/30 text-white/30 hover:text-red-400 transition text-xs">✕</button>
          </div>
          <div class="widget-body flex-1 flex items-center justify-center pointer-events-none opacity-60">
            <div class="text-center">
              <div class="text-4xl">${icons[type]||'📦'}</div>
              <div class="text-sm text-white/40 mt-1">${def.title}</div>
            </div>
          </div>
        </div>`;

      grid.addWidget({
        w: def.w, h: def.h, x: 0, y: bottomY,
        id: String(res.id),
        content,
      });

      this.closeModal({target: document.getElementById('modal-overlay')});
    } catch(e) {
      alert('Erreur : ' + e.message);
    }
  },

  // ----------------------------------------------------------
  // WIDGETS — Édition paramètres
  // ----------------------------------------------------------
  editWidgetFromEl(btn) {
    const id     = parseInt(btn.dataset.widgetId, 10);
    const type   = btn.dataset.widgetType;
    const config = JSON.parse(btn.dataset.widgetConfig || '{}');
    const title  = btn.dataset.widgetTitle || '';
    this.editWidget(id, type, config, title);
  },

  editWidget(id, type, config, title) {
    this.currentWidgetId   = id;
    this.currentWidgetType = type;
    this.currentWidgetConfig = typeof config === 'string' ? JSON.parse(config) : config;

    const titleEl = document.getElementById('edit-widget-title');
    titleEl.textContent = '⚙️ ' + title;

    const fields = document.getElementById('edit-widget-fields');
    fields.innerHTML = this.buildFields(type, this.currentWidgetConfig, title);

    this.showModal('edit-widget');
  },

  buildFields(type, config, title) {
    let html = `
      <div>
        <label class="text-sm text-white/60 block mb-1">Titre du widget</label>
        <input id="wf-title" type="text" value="${escHtml(title)}" class="form-input w-full">
      </div>`;

    switch (type) {
      case 'bookmarks':
        html += `
          <div>
            <label class="text-sm text-white/60 block mb-1">Affichage</label>
            <select id="wf-display" class="form-input w-full">
              <option value="grid" ${config.display==='grid'?'selected':''}>Grille (icônes)</option>
              <option value="list" ${config.display==='list'?'selected':''}>Liste</option>
            </select>
          </div>
          <div class="flex items-center gap-3">
            <input type="checkbox" id="wf-show-title" ${config.show_title!==false?'checked':''} class="accent-indigo-500 w-4 h-4">
            <label for="wf-show-title" class="text-sm text-white/70">Afficher les titres</label>
          </div>
          <div id="bookmark-manager" class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-sm text-white/60">Favoris</span>
              <button onclick="adminApp.showAddBookmark()" class="text-xs bg-brand/30 text-brand px-2 py-1 rounded-lg hover:bg-brand/50 transition">+ Ajouter</button>
            </div>
            <div id="bookmark-list" class="space-y-1 max-h-40 overflow-y-auto"></div>
            <div id="add-bookmark-form" class="hidden space-y-2 p-3 bg-white/5 rounded-xl">
              <input id="bm-title" type="text" placeholder="Titre" class="form-input w-full text-sm">
              <input id="bm-url" type="url" placeholder="https://…" class="form-input w-full text-sm">
              <button onclick="adminApp.addBookmark()" class="w-full bg-brand/40 hover:bg-brand/60 py-1.5 rounded-lg text-sm transition">Ajouter</button>
            </div>
          </div>`;
        // Charger les bookmarks existants
        setTimeout(() => this.loadBookmarkList(), 50);
        break;

      case 'rss': {
        // Normaliser ancien format { url } → nouveau { feeds: [] }
        let feeds = config.feeds || [];
        if (!feeds.length && config.url) feeds = [{ name: 'Flux', url: config.url }];
        const feedsJson = JSON.stringify(feeds);

        html += `
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-sm text-white/60">Flux RSS</label>
              <button type="button" onclick="adminApp.showAddFeed()"
                class="text-xs bg-brand/30 text-brand px-2 py-1 rounded-lg hover:bg-brand/50 transition">
                + Ajouter un flux
              </button>
            </div>

            <div id="rss-feeds-list" class="space-y-2 mb-3"></div>

            <div id="add-feed-form" class="hidden space-y-2 p-3 bg-white/5 rounded-xl mb-3">
              <input id="feed-name" type="text" placeholder="Nom de l'onglet (ex: Tech, Actu…)"
                class="form-input w-full text-sm">
              <input id="feed-url" type="url" placeholder="https://exemple.com/feed.xml"
                class="form-input w-full text-sm">
              <button type="button" onclick="adminApp.addFeed()"
                class="w-full bg-brand/40 hover:bg-brand/60 py-1.5 rounded-lg text-sm transition">
                Ajouter
              </button>
            </div>
          </div>
          <div>
            <label class="text-sm text-white/60 block mb-1">Articles max par flux</label>
            <input id="wf-rss-max" type="number" value="${config.max_items || 10}" min="1" max="50"
              class="form-input w-24">
          </div>`;

        // Initialiser la liste après le rendu
        setTimeout(() => adminApp.initFeedList(feeds), 30);
        break;
      }

      case 'search':
        html += `
          <div>
            <label class="text-sm text-white/60 block mb-1">Moteur de recherche</label>
            <select id="wf-engine" class="form-input w-full">
              <option value="google"     ${config.engine==='google'?'selected':''}>🔍 Google</option>
              <option value="duckduckgo" ${config.engine==='duckduckgo'?'selected':''}>🦆 DuckDuckGo</option>
              <option value="brave"      ${config.engine==='brave'?'selected':''}>🦁 Brave Search</option>
              <option value="bing"       ${config.engine==='bing'?'selected':''}>🌐 Bing</option>
            </select>
          </div>`;
        break;

      case 'weather':
        html += `
          <div>
            <label class="text-sm text-white/60 block mb-1">Ville</label>
            <input id="wf-city" type="text" value="${escHtml(config.city||'')}"
              class="form-input w-full" placeholder="Paris, Lyon, Toulouse…">
          </div>`;
        break;

      case 'embed':
        html += `
          <div>
            <label class="text-sm text-white/60 block mb-1">URL à intégrer</label>
            <input id="wf-embed-url" type="url" value="${escHtml(config.url||'')}"
              class="form-input w-full" placeholder="https://…">
            <p class="text-xs text-white/30 mt-1">Note : certains sites bloquent l'intégration en iframe.</p>
          </div>`;
        break;

      case 'calendar':
        html += `
          <div>
            <label class="text-sm text-white/60 block mb-1">URL iCal (.ics)</label>
            <input id="wf-ical" type="url" value="${escHtml(config.ical_url||'')}"
              class="form-input w-full" placeholder="https://…/calendar.ics">
          </div>`;
        break;

      case 'image':
        html += `
          <div class="flex flex-col gap-3">
            <div>
              <label class="text-sm text-white/60 block mb-1">URL de l'image</label>
              <input id="wf-img-url" type="url" value="${escHtml(config.url||'')}"
                class="form-input w-full" placeholder="https://…/photo.jpg">
            </div>
            <div>
              <label class="text-sm text-white/60 block mb-1">Ajustement</label>
              <select id="wf-img-fit" class="form-input w-full">
                <option value="cover"   ${config.fit==='cover'   ?'selected':''}>Cover (recadré, remplit)</option>
                <option value="contain" ${config.fit==='contain' ?'selected':''}>Contain (entier, avec marges)</option>
                <option value="fill"    ${config.fit==='fill'    ?'selected':''}>Fill (étiré)</option>
              </select>
            </div>
            <div>
              <label class="text-sm text-white/60 block mb-1">Légende (optionnelle)</label>
              <input id="wf-img-caption" type="text" value="${escHtml(config.caption||'')}"
                class="form-input w-full" placeholder="Description de l'image…">
            </div>
          </div>`;
        break;

      case 'pomodoro':
        html += `
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm text-white/60 block mb-1">Travail (min)</label>
              <input id="wf-pomo-work" type="number" value="${config.work_minutes||25}" min="1" max="120"
                class="form-input w-full">
            </div>
            <div>
              <label class="text-sm text-white/60 block mb-1">Pause courte (min)</label>
              <input id="wf-pomo-short" type="number" value="${config.break_minutes||5}" min="1" max="60"
                class="form-input w-full">
            </div>
            <div>
              <label class="text-sm text-white/60 block mb-1">Pause longue (min)</label>
              <input id="wf-pomo-long" type="number" value="${config.long_break_minutes||15}" min="1" max="120"
                class="form-input w-full">
            </div>
            <div>
              <label class="text-sm text-white/60 block mb-1">Sessions avant longue pause</label>
              <input id="wf-pomo-every" type="number" value="${config.long_break_every||4}" min="1" max="10"
                class="form-input w-full">
            </div>
          </div>`;
        break;

      case 'github':
        html += `
          <div class="space-y-3">
            <div>
              <label class="text-sm text-white/60 block mb-1">Plateforme</label>
              <select id="wf-gh-platform" class="form-input w-full">
                <option value="github"  ${(config.platform||'github')==='github' ?'selected':''}>🐙 GitHub</option>
                <option value="gitlab"  ${config.platform==='gitlab' ?'selected':''}>🦊 GitLab</option>
              </select>
            </div>
            <div>
              <label class="text-sm text-white/60 block mb-1">Nom d'utilisateur</label>
              <input id="wf-gh-username" type="text" value="${escHtml(config.username||'')}"
                class="form-input w-full" placeholder="octocat">
              <p class="text-xs text-white/30 mt-1">Profil public uniquement. Pas de token requis.</p>
            </div>
          </div>`;
        break;

      case 'countdown':
        html += `
          <div class="space-y-3">
            <div>
              <label class="text-sm text-white/60 block mb-1">Label</label>
              <input id="wf-cd-label" type="text" value="${escHtml(config.label||'')}"
                class="form-input w-full" placeholder="Vacances, Deadline…">
            </div>
            <div>
              <label class="text-sm text-white/60 block mb-1">Date & heure cible</label>
              <input id="wf-cd-date" type="datetime-local"
                value="${escHtml((config.target_date||'').slice(0,16))}"
                class="form-input w-full">
            </div>
            <div class="flex items-center gap-3">
              <input type="checkbox" id="wf-cd-secs" ${config.show_seconds!==false?'checked':''} class="accent-indigo-500 w-4 h-4">
              <label for="wf-cd-secs" class="text-sm text-white/70">Afficher les secondes</label>
            </div>
          </div>`;
        break;

      case 'crypto':
        html += `
          <div class="space-y-3">
            <div>
              <label class="text-sm text-white/60 block mb-1">Devises (séparées par virgule)</label>
              <input id="wf-crypto-coins" type="text" value="${escHtml((config.coins||['bitcoin','ethereum']).join(', '))}"
                class="form-input w-full" placeholder="bitcoin, ethereum, solana…">
              <p class="text-xs text-white/30 mt-1">IDs CoinGecko : bitcoin, ethereum, solana, cardano, dogecoin…</p>
            </div>
            <div>
              <label class="text-sm text-white/60 block mb-1">Devise d'affichage</label>
              <select id="wf-crypto-currency" class="form-input w-full">
                <option value="eur" ${(config.currency||'eur')==='eur'?'selected':''}>€ EUR</option>
                <option value="usd" ${config.currency==='usd'?'selected':''}>$ USD</option>
                <option value="gbp" ${config.currency==='gbp'?'selected':''}>£ GBP</option>
                <option value="btc" ${config.currency==='btc'?'selected':''}>₿ BTC</option>
              </select>
            </div>
          </div>`;
        break;

      case 'lofi':
        html += `
          <div class="space-y-3">
            <div>
              <label class="text-sm text-white/60 block mb-1">Label</label>
              <input id="wf-lofi-label" type="text" value="${escHtml(config.label||'')}"
                class="form-input w-full" placeholder="Lofi Girl ☕">
            </div>
            <div>
              <label class="text-sm text-white/60 block mb-1">URL ou ID YouTube</label>
              <input id="wf-lofi-video" type="text" value="${escHtml(config.video_id||'')}"
                class="form-input w-full" placeholder="jfKfPfyJRdk ou https://youtube.com/watch?v=…">
              <p class="text-xs text-white/30 mt-1">Lofi Girl par défaut : jfKfPfyJRdk</p>
            </div>
          </div>`;
        break;
    }

    return html;
  },

  // ----------------------------------------------------------
  // RSS — Gestion des flux multiples
  // ----------------------------------------------------------
  _rssFeeds: [],

  initFeedList(feeds) {
    this._rssFeeds = feeds ? [...feeds] : [];
    this.renderFeedList();
  },

  renderFeedList() {
    const list = document.getElementById('rss-feeds-list');
    if (!list) return;
    if (!this._rssFeeds.length) {
      list.innerHTML = '<p class="text-xs text-white/30 py-1">Aucun flux ajouté.</p>';
      return;
    }
    list.innerHTML = this._rssFeeds.map((f, i) => `
      <div class="flex items-center gap-2 bg-white/5 rounded-lg px-3 py-2">
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium text-white/80 truncate">${escHtml(f.name)}</div>
          <div class="text-xs text-white/30 truncate">${escHtml(f.url)}</div>
        </div>
        <button type="button" onclick="adminApp.removeFeed(${i})"
          class="text-white/30 hover:text-red-400 transition text-xs flex-shrink-0">✕</button>
      </div>`).join('');
  },

  showAddFeed() {
    document.getElementById('add-feed-form')?.classList.toggle('hidden');
    document.getElementById('feed-name')?.focus();
  },

  addFeed() {
    const name = document.getElementById('feed-name')?.value.trim();
    const url  = document.getElementById('feed-url')?.value.trim();
    if (!url) { alert('URL requise.'); return; }
    this._rssFeeds.push({ name: name || 'Flux ' + (this._rssFeeds.length + 1), url });
    this.renderFeedList();
    document.getElementById('feed-name').value = '';
    document.getElementById('feed-url').value  = '';
    document.getElementById('add-feed-form')?.classList.add('hidden');
  },

  removeFeed(index) {
    this._rssFeeds.splice(index, 1);
    this.renderFeedList();
  },

  async loadBookmarkList() {
    const list = document.getElementById('bookmark-list');
    if (!list) return;

    try {
      const bookmarks = await apiFetch(`/api/v1/widgets?page_id=-1`, null, 'GET').catch(() => []);
      // Charger via widgets API n'est pas le bon endpoint ici — on fait autrement
      // Pour l'instant on affiche un message
      list.innerHTML = '<p class="text-xs text-white/30 py-1">Rechargez après avoir sauvegardé.</p>';
    } catch (e) {}
  },

  showAddBookmark() {
    document.getElementById('add-bookmark-form')?.classList.toggle('hidden');
  },

  async addBookmark() {
    const title = document.getElementById('bm-title')?.value.trim();
    const url   = document.getElementById('bm-url')?.value.trim();
    if (!url) { alert('URL requise.'); return; }

    await apiFetch('/api/v1/bookmarks', {
      widget_id: this.currentWidgetId,
      title: title || url,
      url,
    });

    document.getElementById('bm-title').value = '';
    document.getElementById('bm-url').value   = '';
    alert('Favori ajouté ! Il sera visible après rechargement.');
  },

  async saveWidgetConfig() {
    const type   = this.currentWidgetType;
    const id     = this.currentWidgetId;
    const title  = document.getElementById('wf-title')?.value || '';

    let config = { ...this.currentWidgetConfig };

    switch (type) {
      case 'bookmarks':
        config.display    = document.getElementById('wf-display')?.value;
        config.show_title = document.getElementById('wf-show-title')?.checked;
        break;
      case 'rss':
        config.feeds     = this._rssFeeds;
        config.max_items = parseInt(document.getElementById('wf-rss-max')?.value || 10);
        delete config.url; // supprimer l'ancien format
        break;
      case 'search':
        config.engine = document.getElementById('wf-engine')?.value;
        break;
      case 'weather':
        config.city = document.getElementById('wf-city')?.value;
        break;
      case 'embed':
        config.url = document.getElementById('wf-embed-url')?.value;
        break;
      case 'calendar':
        config.ical_url = document.getElementById('wf-ical')?.value;
        break;
      case 'image':
        config.url     = document.getElementById('wf-img-url')?.value;
        config.fit     = document.getElementById('wf-img-fit')?.value;
        config.caption = document.getElementById('wf-img-caption')?.value;
        break;
      case 'pomodoro':
        config.work_minutes       = parseInt(document.getElementById('wf-pomo-work')?.value  || 25);
        config.break_minutes      = parseInt(document.getElementById('wf-pomo-short')?.value || 5);
        config.long_break_minutes = parseInt(document.getElementById('wf-pomo-long')?.value  || 15);
        config.long_break_every   = parseInt(document.getElementById('wf-pomo-every')?.value || 4);
        break;
      case 'github':
        config.username = document.getElementById('wf-gh-username')?.value.trim();
        config.platform = document.getElementById('wf-gh-platform')?.value;
        break;
      case 'countdown':
        config.label        = document.getElementById('wf-cd-label')?.value.trim();
        config.target_date  = document.getElementById('wf-cd-date')?.value;
        config.show_seconds = document.getElementById('wf-cd-secs')?.checked;
        break;
      case 'crypto':
        config.coins    = document.getElementById('wf-crypto-coins')?.value.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
        config.currency = document.getElementById('wf-crypto-currency')?.value;
        break;
      case 'lofi': {
        const raw = document.getElementById('wf-lofi-video')?.value.trim() || 'jfKfPfyJRdk';
        const match = raw.match(/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
        config.video_id = match ? match[1] : raw;
        config.label    = document.getElementById('wf-lofi-label')?.value.trim();
        break;
      }
    }

    await apiFetch(`/api/v1/widgets/${id}`, { title, config }, 'PUT');
    showToast('Widget mis à jour');

    // Mettre à jour le DOM : titre + data-widget-config (évite la perte au 2e edit sans rechargement)
    const header = document.querySelector(`[data-widget-id="${id}"] .widget-header .font-semibold`);
    if (header) header.textContent = title;

    const editBtn = document.querySelector(`button[data-widget-id="${id}"]`);
    if (editBtn) {
      editBtn.dataset.widgetConfig = JSON.stringify(config);
      editBtn.dataset.widgetTitle  = title;
      this.currentWidgetConfig     = config;
    }

    this.closeModal({target: document.getElementById('modal-overlay')});
  },

  async deleteWidget(id) {
    if (!confirm('Supprimer ce widget ?')) return;
    await apiFetch(`/api/v1/widgets/${id}`, null, 'DELETE');

    // Retirer de la grille
    const el = document.querySelector(`.grid-stack-item[gs-id="${id}"]`);
    if (el) grid.removeWidget(el);
  },

  // ----------------------------------------------------------
  // PAGE — Paramètres
  // ----------------------------------------------------------

  // Initialise le formulaire avec les valeurs actuelles de la page
  initPageSettingsForm() {
    const type  = typeof PAGE_BG !== 'undefined' ? PAGE_BG.type  : 'color';
    const value = typeof PAGE_BG !== 'undefined' ? PAGE_BG.value : '#0f172a';
    const accent = typeof PAGE_ACCENT !== 'undefined' ? PAGE_ACCENT : '#6366f1';

    // Onglet bg
    this.setBgTab(type);

    // Remplir la valeur actuelle
    if (type === 'color') {
      const el = document.getElementById('pg-bg-color');
      if (el) el.value = value;
    } else if (type === 'gradient') {
      const sel = document.getElementById('pg-bg-gradient');
      if (sel) {
        // Chercher l'option correspondante, sinon garder la première
        const opt = Array.from(sel.options).find(o => o.value === value);
        if (opt) sel.value = value;
        document.getElementById('gradient-preview').style.background = value;
      }
    } else if (type === 'image') {
      const urlEl = document.getElementById('pg-bg-url');
      if (urlEl && value) {
        urlEl.value = value;
        this.previewBgUrl(value);
      }
      // Charger la galerie
      this.loadBgGallery();
    }

    // Couleur d'accent
    const accentEl = document.getElementById('pg-accent');
    if (accentEl) accentEl.value = accent;
  },

  _bgGalleryLoaded: false,

  async loadBgGallery() {
    if (this._bgGalleryLoaded) return;
    const gallery = document.getElementById('bg-gallery');
    const grid    = document.getElementById('bg-gallery-grid');
    if (!gallery || !grid) return;

    try {
      const files = await apiFetch('/api/v1/upload', null, 'GET');
      if (!files || !files.length) return;

      gallery.classList.remove('hidden');
      grid.innerHTML = files.map(f => `
        <div class="relative h-16 group">
          <button type="button" onclick="adminApp.selectGalleryImg('${escHtml(f.url)}')"
            class="w-full h-full rounded-lg overflow-hidden border-2 border-white/10 hover:border-brand/60 transition bg-cover bg-center block"
            style="background-image:url('${escHtml(f.url)}')" title="${escHtml(f.name)}">
          </button>
          <button type="button" onclick="adminApp.deleteBgImage('${escHtml(f.name)}', this)"
            class="absolute top-0.5 right-0.5 w-5 h-5 rounded-full bg-black/70 text-white/60
                   hover:bg-red-500 hover:text-white text-[10px] leading-none opacity-0
                   group-hover:opacity-100 transition flex items-center justify-center"
            title="Supprimer">✕</button>
        </div>`).join('');
      this._bgGalleryLoaded = true;
    } catch {}
  },

  selectGalleryImg(url) {
    const urlEl = document.getElementById('pg-bg-url');
    if (urlEl) { urlEl.value = url; this.previewBgUrl(url); }
  },

  async deleteBgImage(filename, btn) {
    if (!confirm('Supprimer cette image ?')) return;
    try {
      await apiFetch(`/api/v1/upload?file=${encodeURIComponent(filename)}`, null, 'DELETE');
      // Retirer le bloc parent du DOM
      btn.closest('.relative')?.remove();
      // Si l'URL supprimée est l'URL courante dans le champ, vider le champ
      const urlEl = document.getElementById('pg-bg-url');
      if (urlEl && urlEl.value.endsWith(filename)) {
        urlEl.value = '';
        document.getElementById('bg-preview').style.backgroundImage = '';
      }
      showToast('Image supprimée');
    } catch {
      showToast('Erreur suppression', 'error');
    }
  },

  previewAccent(hex) {
    if (!/^#[0-9a-fA-F]{6}$/.test(hex)) return;
    const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
    document.documentElement.style.setProperty('--brand', `${r} ${g} ${b}`);
    document.documentElement.style.setProperty('--brand-dark', `${Math.floor(r*.8)} ${Math.floor(g*.8)} ${Math.floor(b*.8)}`);
  },

  pickAccent(hex) {
    const el = document.getElementById('pg-accent');
    if (el) el.value = hex;
    this.previewAccent(hex);
  },

  setBgTab(tab) {
    this.bgTab = tab;
    ['color','gradient','image'].forEach(t => {
      const panel = document.getElementById('bg-panel-' + t);
      const btn   = document.getElementById('bg-tab-' + t);
      if (!panel || !btn) return;
      panel.classList.toggle('hidden', t !== tab);
      if (t === tab) {
        btn.className = 'px-3 py-1.5 rounded-lg text-sm bg-brand/30 text-brand border border-brand/50';
      } else {
        btn.className = 'px-3 py-1.5 rounded-lg text-sm bg-white/10 hover:bg-white/20 transition';
      }
    });

    // Preview dégradé
    if (tab === 'gradient') {
      const sel = document.getElementById('pg-bg-gradient');
      sel?.addEventListener('change', () => {
        document.getElementById('gradient-preview').style.background = sel.value;
      });
    }

    // Charger galerie au premier passage sur image
    if (tab === 'image') this.loadBgGallery();
  },

  previewBgUrl(url) {
    const preview = document.getElementById('bg-preview');
    if (preview && url) {
      preview.style.backgroundImage = `url(${url})`;
    }
  },

  async uploadBg(input) {
    const file = input.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('bg', file);

    const res = await fetch(`${BASE_URL}/api/v1/upload?page_id=${PAGE_ID}`, {
      method: 'POST',
      body: formData,
    });
    const data = await res.json();

    if (data.url) {
      document.getElementById('pg-bg-url').value = data.url;
      this.previewBgUrl(data.url);
      // Recharger la galerie avec la nouvelle image
      this._bgGalleryLoaded = false;
      this.loadBgGallery();
    } else {
      alert('Erreur upload : ' + (data.error || 'inconnue'));
    }
  },

  async savePageSettings(pageId) {
    const name = document.getElementById('pg-name')?.value.trim();
    const icon = document.getElementById('pg-icon')?.value.trim();

    let bgType, bgValue;

    switch (this.bgTab) {
      case 'color':
        bgType  = 'color';
        bgValue = document.getElementById('pg-bg-color')?.value || '#0f172a';
        break;
      case 'gradient':
        bgType  = 'gradient';
        bgValue = document.getElementById('pg-bg-gradient')?.value || '#0f172a';
        break;
      case 'image':
        bgType  = 'image';
        bgValue = document.getElementById('pg-bg-url')?.value || '';
        break;
    }

    const accentColor = document.getElementById('pg-accent')?.value || '#6366f1';

    await apiFetch(`/api/v1/pages/${pageId}`, {
      name, icon, bg_type: bgType, bg_value: bgValue, accent_color: accentColor,
    }, 'PUT');

    // Mettre à jour PAGE_BG et PAGE_ACCENT en mémoire
    if (typeof PAGE_BG !== 'undefined') { PAGE_BG.type = bgType; PAGE_BG.value = bgValue; }

    // Appliquer le fond sans reload
    document.body.style.background = bgType === 'image'
      ? `url(${bgValue}) center/cover no-repeat fixed`
      : bgValue;

    // Appliquer accent sans reload
    this.previewAccent(accentColor);

    showToast('Page sauvegardée');
    this.closeModal({target: document.getElementById('modal-overlay')});
  },

  // ----------------------------------------------------------
  // THÈMES
  // ----------------------------------------------------------
  _themes: [
    { name: 'Nuit indigo',    bg_type: 'gradient', bg_value: 'linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%)' },
    { name: 'Obsidienne',     bg_type: 'gradient', bg_value: 'linear-gradient(135deg,#0c0a09 0%,#292524 100%)' },
    { name: 'Forêt',          bg_type: 'gradient', bg_value: 'linear-gradient(135deg,#042f2e 0%,#134e4a 100%)' },
    { name: 'Cosmos',         bg_type: 'gradient', bg_value: 'linear-gradient(135deg,#1e1b4b 0%,#4c1d95 100%)' },
    { name: 'Océan',          bg_type: 'gradient', bg_value: 'linear-gradient(135deg,#172554 0%,#0c4a6e 100%)' },
    { name: 'Braise',         bg_type: 'gradient', bg_value: 'linear-gradient(135deg,#431407 0%,#9a3412 100%)' },
    { name: 'Rose sombre',    bg_type: 'gradient', bg_value: 'linear-gradient(135deg,#1a0a1e 0%,#5b0f4e 100%)' },
    { name: 'Aurore boréale', bg_type: 'gradient', bg_value: 'linear-gradient(135deg,#042f2e 0%,#1e1b4b 50%,#0f172a 100%)' },
    { name: 'Cyberpunk',      bg_type: 'gradient', bg_value: 'linear-gradient(135deg,#050c18 0%,#0d2137 50%,#04101f 100%)' },
    { name: 'Café',           bg_type: 'gradient', bg_value: 'linear-gradient(135deg,#1c1008 0%,#3b2005 100%)' },
    { name: 'Ardoise',        bg_type: 'color',    bg_value: '#0f172a' },
    { name: 'Minuit',         bg_type: 'color',    bg_value: '#050507' },
  ],

  renderThemes() {
    const grid = document.getElementById('themes-grid');
    if (!grid) return;
    grid.innerHTML = this._themes.map((t, i) => `
      <button type="button" onclick="adminApp.applyTheme(${i})"
        class="group relative h-20 rounded-xl overflow-hidden border-2 border-white/10 hover:border-brand/60 transition-all hover:scale-105"
        style="background:${escHtml(t.bg_value)};"
        title="${escHtml(t.name)}">
        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all"></div>
        <span class="absolute bottom-1.5 left-0 right-0 text-center text-[11px] font-medium text-white/80
                     drop-shadow-[0_1px_2px_rgba(0,0,0,0.9)]">
          ${escHtml(t.name)}
        </span>
      </button>`).join('');
  },

  async applyTheme(index) {
    const t = this._themes[index];
    if (!t) return;
    await apiFetch(`/api/v1/pages/${PAGE_ID}`, {
      bg_type: t.bg_type, bg_value: t.bg_value,
    }, 'PUT');
    document.body.style.background = t.bg_type === 'image'
      ? `url(${t.bg_value}) center/cover no-repeat fixed`
      : t.bg_value;
    showToast('Thème "' + t.name + '" appliqué');
    this.closeModal({ target: document.getElementById('modal-overlay') });
  },

  // ----------------------------------------------------------
  // SAUVEGARDE / RESTAURATION
  // ----------------------------------------------------------
  async exportBackup() {
    const data = await apiFetch('/api/v1/backup', null, 'GET');
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    const date = new Date().toISOString().slice(0, 10);
    a.href     = url;
    a.download = `startme-backup-${date}.json`;
    a.click();
    URL.revokeObjectURL(url);
  },

  async importBackup(input) {
    const file = input.files[0];
    if (!file) return;

    const status = document.getElementById('import-status');
    status.className = 'text-sm text-center py-2 rounded-xl bg-white/5 text-white/60';
    status.textContent = '⏳ Importation en cours…';
    status.classList.remove('hidden');

    try {
      const text = await file.text();
      const data = JSON.parse(text);
      await apiFetch('/api/v1/backup', data, 'POST');
      status.className = 'text-sm text-center py-2 rounded-xl bg-green-500/20 text-green-400';
      status.textContent = '✅ Import réussi ! Redirection…';
      setTimeout(() => { window.location.href = BASE_URL + '/'; }, 1200);
    } catch (e) {
      status.className = 'text-sm text-center py-2 rounded-xl bg-red-500/20 text-red-400';
      status.textContent = '❌ ' + (e.message || 'Erreur lors de l\'import.');
      input.value = '';
    }
  },

  async deletePage(pageId) {
    if (!confirm('Supprimer cette page et tous ses widgets ? Cette action est irréversible.')) return;
    try {
      await apiFetch(`/api/v1/pages/${pageId}`, null, 'DELETE');
      window.location.href = BASE_URL + '/';
    } catch(e) {
      alert(e.message);
    }
  },

  // ----------------------------------------------------------
  // NOUVELLE PAGE
  // ----------------------------------------------------------
  async createPage() {
    const name = document.getElementById('new-page-name')?.value.trim();
    const icon = document.getElementById('new-page-icon')?.value.trim() || '📄';
    if (!name) { alert('Nom requis.'); return; }

    const res = await apiFetch('/api/v1/pages', { name, icon });
    window.location.href = `${BASE_URL}/admin.php?page=${res.slug}`;
  },
};
