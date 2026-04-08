<?php
// Migration 004 — Ajout du type 'image' dans le ENUM widgets.type

db()->exec("ALTER TABLE widgets MODIFY COLUMN type
    ENUM('bookmarks','rss','notes','todo','search','weather','clock','embed','calendar','image')
    NOT NULL");
