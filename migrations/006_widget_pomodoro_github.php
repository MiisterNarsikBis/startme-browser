<?php
// Migration 006 — Ajout des types 'pomodoro' et 'github' dans le ENUM widgets.type

db()->exec("ALTER TABLE widgets MODIFY COLUMN type
    ENUM('bookmarks','rss','notes','todo','search','weather','clock','embed','calendar','image','pomodoro','github')
    NOT NULL");
