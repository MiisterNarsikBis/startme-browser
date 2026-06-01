<?php
// Migration 009 — Ajout du type 'json' dans le ENUM widgets.type + table json_cache

db()->exec("ALTER TABLE widgets MODIFY COLUMN type
    ENUM('bookmarks','rss','notes','todo','search','weather','clock','embed','calendar','image','pomodoro','github','countdown','crypto','lofi','json')
    NOT NULL");

db()->exec("CREATE TABLE IF NOT EXISTS json_cache (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_id    INT UNSIGNED NOT NULL UNIQUE,
    url          TEXT NOT NULL,
    content_json LONGTEXT,
    fetched_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_json_cache_widget
        FOREIGN KEY (widget_id) REFERENCES widgets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
