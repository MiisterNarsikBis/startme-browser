<?php
/* GET  /api/v1/backup → exporter toute la configuration en JSON
   POST /api/v1/backup → importer une configuration JSON (remplace les données existantes) */

// --- EXPORT ---
if ($method === 'GET') {
    $pages = db_fetchAll(
        'SELECT id, name, slug, icon, bg_type, bg_value, position FROM pages WHERE user_id=? ORDER BY position ASC, id ASC',
        [$uid]
    );

    foreach ($pages as &$page) {
        $pid     = $page['id'];
        $widgets = db_fetchAll(
            'SELECT id, type, title, config_json, grid_x, grid_y, grid_w, grid_h FROM widgets WHERE page_id=? ORDER BY grid_y, grid_x',
            [$pid]
        );

        foreach ($widgets as &$w) {
            $wid           = $w['id'];
            $w['config_json'] = json_decode($w['config_json'] ?? '{}', true) ?? [];

            if ($w['type'] === 'bookmarks') {
                $w['bookmarks'] = db_fetchAll(
                    'SELECT title, url, favicon, position FROM bookmarks WHERE widget_id=? ORDER BY position',
                    [$wid]
                );
            }
            if ($w['type'] === 'notes') {
                $note             = db_fetch('SELECT content FROM notes WHERE widget_id=?', [$wid]);
                $w['note_content'] = $note['content'] ?? '';
            }
            if ($w['type'] === 'todo') {
                $w['todos'] = db_fetchAll(
                    'SELECT title, done, position FROM todos WHERE widget_id=? ORDER BY position',
                    [$wid]
                );
            }
            unset($w['id']);
        }
        unset($w);

        $page['widgets'] = $widgets;
        unset($page['id']);
    }
    unset($page);

    json_response([
        'version'     => 1,
        'app'         => 'startme',
        'exported_at' => date('c'),
        'pages'       => $pages,
    ]);
}

// --- IMPORT ---
if ($method === 'POST') {
    $d = request_json();

    if (($d['app'] ?? '') !== 'startme' || ($d['version'] ?? 0) < 1) {
        json_error('Fichier de sauvegarde invalide ou incompatible.');
    }
    if (empty($d['pages']) || !is_array($d['pages'])) {
        json_error('Aucune page trouvée dans la sauvegarde.');
    }

    $allowed_types = ['bookmarks','rss','notes','todo','search','weather','clock','embed','calendar','image'];

    // Supprimer les données actuelles (CASCADE supprime widgets, bookmarks, notes, todos)
    db_query('DELETE FROM pages WHERE user_id=?', [$uid]);

    foreach ($d['pages'] as $pos => $pd) {
        $name     = mb_substr(trim($pd['name'] ?? 'Page'), 0, 100);
        $icon     = mb_substr(trim($pd['icon'] ?? '📄'), 0, 10);
        $bg_type  = in_array($pd['bg_type'] ?? '', ['color','gradient','image']) ? $pd['bg_type'] : 'color';
        $bg_value = mb_substr($pd['bg_value'] ?? '#0f172a', 0, 1000);
        $position = isset($pd['position']) ? (int)$pd['position'] : (int)$pos;

        // Générer un slug unique pour cet utilisateur
        $base_slug = slugify($name) ?: 'page';
        $slug = $base_slug;
        $n = 1;
        while (db_fetch('SELECT id FROM pages WHERE user_id=? AND slug=?', [$uid, $slug])) {
            $slug = $base_slug . '-' . $n++;
        }

        $page_id = db_insert(
            'INSERT INTO pages (user_id, name, slug, icon, bg_type, bg_value, position) VALUES (?,?,?,?,?,?,?)',
            [$uid, $name, $slug, $icon, $bg_type, $bg_value, $position]
        );

        foreach ($pd['widgets'] ?? [] as $wd) {
            $type = in_array($wd['type'] ?? '', $allowed_types) ? $wd['type'] : null;
            if (!$type) continue;

            $title  = mb_substr($wd['title'] ?? '', 0, 100);
            $config = json_encode($wd['config_json'] ?? []);
            $gx     = max(0, (int)($wd['grid_x'] ?? 0));
            $gy     = max(0, (int)($wd['grid_y'] ?? 0));
            $gw     = max(1, min(12, (int)($wd['grid_w'] ?? 4)));
            $gh     = max(1, (int)($wd['grid_h'] ?? 4));

            $wid = db_insert(
                'INSERT INTO widgets (page_id, type, title, config_json, grid_x, grid_y, grid_w, grid_h) VALUES (?,?,?,?,?,?,?,?)',
                [$page_id, $type, $title, $config, $gx, $gy, $gw, $gh]
            );

            if ($type === 'bookmarks') {
                foreach ($wd['bookmarks'] ?? [] as $bm) {
                    $bu = mb_substr($bm['url'] ?? '', 0, 2000);
                    if (!$bu) continue;
                    db_insert(
                        'INSERT INTO bookmarks (widget_id, title, url, favicon, position) VALUES (?,?,?,?,?)',
                        [$wid, mb_substr($bm['title'] ?? '', 0, 200), $bu, mb_substr($bm['favicon'] ?? '', 0, 500), (int)($bm['position'] ?? 0)]
                    );
                }
            }
            if ($type === 'notes' && isset($wd['note_content'])) {
                db_insert('INSERT INTO notes (widget_id, content) VALUES (?,?)', [$wid, $wd['note_content']]);
            }
            if ($type === 'todo') {
                foreach ($wd['todos'] ?? [] as $td) {
                    $tt = mb_substr($td['title'] ?? '', 0, 500);
                    if (!$tt) continue;
                    db_insert(
                        'INSERT INTO todos (widget_id, title, done, position) VALUES (?,?,?,?)',
                        [$wid, $tt, (int)($td['done'] ?? 0), (int)($td['position'] ?? 0)]
                    );
                }
            }
        }
    }

    json_response(['ok' => true]);
}

json_error('Méthode non autorisée.', 405);
