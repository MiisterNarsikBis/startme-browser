<?php
// Migration 003 — Table login_attempts pour le rate limiting

db()->exec("CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    attempts   SMALLINT UNSIGNED DEFAULT 1,
    blocked_until DATETIME NULL,
    last_attempt  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
