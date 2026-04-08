<?php
// Migration 005 — Corriger la contrainte UNIQUE de rss_cache
// La clé unique était sur (widget_id) seul → un seul cache par widget.
// Elle doit être sur (widget_id, url) pour les widgets multi-flux.

// Supprimer l'ancienne contrainte si elle existe encore
$indexes = db()->query("SHOW INDEX FROM rss_cache WHERE Key_name = 'widget_id'")->fetchAll();
if ($indexes) {
    db()->exec("ALTER TABLE rss_cache DROP INDEX widget_id");
}

// Ajouter la bonne contrainte composite
$existing = db()->query("SHOW INDEX FROM rss_cache WHERE Key_name = 'uq_widget_url'")->fetchAll();
if (!$existing) {
    db()->exec("ALTER TABLE rss_cache ADD UNIQUE KEY uq_widget_url (widget_id, url(512))");
}
