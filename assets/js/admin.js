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
      image:     { title: 'Image',     config: { url: '', fit: 'cover', caption: '' }, w: 3, h: 30 },
    };

    const def = defaults[type] || { title: type, config: {}, w: 3, h: 4 };

    try {
      const res = await apiFetch('/api/v1/widgets', {
        page_id: PAGE_ID,
        type,
        title:   def.title,
        config:  def.config,
        grid_x:  0,
        grid_y:  0,
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
            <button onclick="adminApp.editWidget(${res.id},'${type}',${JSON.stringify(def.config).replace(/"/g,'&quot;')},'${escHtml(def.title)}')"
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
        w: def.w, h: def.h, x: 0, y: 0,
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
    }

    await apiFetch(`/api/v1/widgets/${id}`, { title, config }, 'PUT');

    // Mettre à jour le titre dans la grille
    const header = document.querySelector(`[data-widget-id="${id}"] .widget-header .font-semibold`);
    if (header) header.textContent = title;

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

    const res = await fetch(`${BASE_URL}/api/upload.php?page_id=${PAGE_ID}`, {
      method: 'POST',
      body: formData,
    });
    const data = await res.json();

    if (data.url) {
      document.getElementById('pg-bg-url').value = data.url;
      this.previewBgUrl(data.url);
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

    await apiFetch(`/api/v1/pages/${pageId}`, {
      name, icon, bg_type: bgType, bg_value: bgValue,
    }, 'PUT');

    // Appliquer le fond sans reload
    document.body.style.background = bgType === 'image'
      ? `url(${bgValue}) center/cover no-repeat fixed`
      : bgValue;

    this.closeModal({target: document.getElementById('modal-overlay')});
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
