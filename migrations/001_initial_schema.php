<?php
// Migration 001 — Schéma initial (tables de base)
// Correspond à ce que install.php créait manuellement.

db()->exec("CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seed_hash  VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db()->exec("CREATE TABLE IF NOT EXISTS pages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    slug       VARCHAR(100) NOT NULL,
    icon       VARCHAR(10)  DEFAULT '📄',
    bg_type    ENUM('color','gradient','image') DEFAULT 'color',
    bg_value   VARCHAR(1000) DEFAULT '#0f172a',
    position   INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_slug (user_id, slug),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db()->exec("CREATE TABLE IF NOT EXISTS widgets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id     INT UNSIGNED NOT NULL,
    type        ENUM('bookmarks','rss','notes','todo','search','weather','clock','embed','calendar') NOT NULL,
    title       VARCHAR(100),
    config_json JSON,
    grid_x      INT DEFAULT 0,
    grid_y      INT DEFAULT 0,
    grid_w      INT DEFAULT 4,
    grid_h      INT DEFAULT 4,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db()->exec("CREATE TABLE IF NOT EXISTS bookmarks (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_id  INT UNSIGNED NOT NULL,
    title      VARCHAR(200),
    url        VARCHAR(2000) NOT NULL,
    favicon    VARCHAR(500),
    position   INT DEFAULT 0,
    FOREIGN KEY (widget_id) REFERENCES widgets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db()->exec("CREATE TABLE IF NOT EXISTS rss_cache (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_id    INT UNSIGNED NOT NULL UNIQUE,
    url          VARCHAR(2000) NOT NULL,
    content_json LONGTEXT,
    fetched_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (widget_id) REFERENCES widgets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db()->exec("CREATE TABLE IF NOT EXISTS todos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_id  INT UNSIGNED NOT NULL,
    title      VARCHAR(500) NOT NULL,
    done       TINYINT(1) DEFAULT 0,
    position   INT DEFAULT 0,
    FOREIGN KEY (widget_id) REFERENCES widgets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db()->exec("CREATE TABLE IF NOT EXISTS notes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_id  INT UNSIGNED NOT NULL UNIQUE,
    content    LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (widget_id) REFERENCES widgets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
