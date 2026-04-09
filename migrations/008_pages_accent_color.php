<?php
// Migration 008 — Ajoute la colonne accent_color à la table pages
return function(PDO $pdo): void {
    $cols = $pdo->query("SHOW COLUMNS FROM pages LIKE 'accent_color'")->fetchAll();
    if (!$cols) {
        $pdo->exec("ALTER TABLE pages ADD COLUMN accent_color VARCHAR(20) NOT NULL DEFAULT '#6366f1' AFTER bg_value");
    }
};
