<?php
// Migration 007 — Ajout des types 'countdown', 'crypto', 'lofi' dans le ENUM widgets.type

db()->exec("ALTER TABLE widgets MODIFY COLUMN type
    ENUM('bookmarks','rss','notes','todo','search','weather','clock','embed','calendar','image','pomodoro','github','countdown','crypto','lofi')
    NOT NULL");
