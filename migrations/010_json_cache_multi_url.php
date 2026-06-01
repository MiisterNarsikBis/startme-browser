<?php
// Migration 010 — json_cache : cache par (widget_id, url_hash) pour les widgets multi-sources
// La contrainte UNIQUE était sur widget_id seul → une seule URL par widget.
// On passe à (widget_id, url_hash) pour supporter plusieurs sources par widget.

// Vider le cache (données non critiques) pour éviter les conflits de migration
db()->exec("TRUNCATE TABLE json_cache");

// Supprimer l'ancienne contrainte UNIQUE widget_id si elle existe
$indexes = db()->query("SHOW INDEX FROM json_cache WHERE Key_name = 'widget_id'")->fetchAll();
if ($indexes) {
    db()->exec("ALTER TABLE json_cache DROP INDEX widget_id");
}

// Ajouter la colonne url_hash (MD5 de l'URL) si absente
$cols = array_column(db()->query("SHOW COLUMNS FROM json_cache")->fetchAll(), 'Field');
if (!in_array('url_hash', $cols)) {
    db()->exec("ALTER TABLE json_cache ADD COLUMN url_hash VARCHAR(32) NOT NULL DEFAULT '' AFTER widget_id");
}

// Ajouter la contrainte composite si absente
$existing = db()->query("SHOW INDEX FROM json_cache WHERE Key_name = 'uq_json_widget_url'")->fetchAll();
if (!$existing) {
    db()->exec("ALTER TABLE json_cache ADD UNIQUE KEY uq_json_widget_url (widget_id, url_hash)");
}
