<?php
// ============================================================
//  install.php — Exécuter UNE SEULE FOIS pour créer les tables
//  Supprimer ou protéger ce fichier après installation !
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$tables = [

'users' => "CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seed_hash  VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'pages' => "CREATE TABLE IF NOT EXISTS pages (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'widgets' => "CREATE TABLE IF NOT EXISTS widgets (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'bookmarks' => "CREATE TABLE IF NOT EXISTS bookmarks (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_id  INT UNSIGNED NOT NULL,
    title      VARCHAR(200),
    url        VARCHAR(2000) NOT NULL,
    favicon    VARCHAR(500),
    position   INT DEFAULT 0,
    FOREIGN KEY (widget_id) REFERENCES widgets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'rss_cache' => "CREATE TABLE IF NOT EXISTS rss_cache (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_id    INT UNSIGNED NOT NULL UNIQUE,
    url          VARCHAR(2000) NOT NULL,
    content_json LONGTEXT,
    fetched_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (widget_id) REFERENCES widgets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'todos' => "CREATE TABLE IF NOT EXISTS todos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_id  INT UNSIGNED NOT NULL,
    title      VARCHAR(500) NOT NULL,
    done       TINYINT(1) DEFAULT 0,
    position   INT DEFAULT 0,
    FOREIGN KEY (widget_id) REFERENCES widgets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'notes' => "CREATE TABLE IF NOT EXISTS notes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_id  INT UNSIGNED NOT NULL UNIQUE,
    content    LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (widget_id) REFERENCES widgets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

];

$ok = [];
$err = [];

foreach ($tables as $name => $sql) {
    try {
        db()->exec($sql);
        $ok[] = $name;
    } catch (PDOException $e) {
        $err[] = $name . ': ' . $e->getMessage();
    }
}

// Créer le dossier uploads
$uploadDir = ROOT_PATH . 'assets/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Installation — Startme</title>
<style>
  body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
  h1 { color: #38bdf8; }
  .ok  { color: #4ade80; }
  .err { color: #f87171; }
  .warn { background: #7c3aed33; border: 1px solid #7c3aed; padding: 1rem; border-radius: 8px; margin-top: 1rem; }
  a { color: #38bdf8; }
</style>
</head>
<body>
<h1>⚙️ Installation Startme</h1>

<?php foreach ($ok as $t): ?>
  <p class="ok">✅ Table <strong><?= $t ?></strong> créée</p>
<?php endforeach; ?>

<?php foreach ($err as $e): ?>
  <p class="err">❌ <?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<p class="ok">📁 Dossier uploads : <?= $uploadDir ?></p>

<?php if (empty($err)): ?>
  <div class="warn">
    ⚠️ <strong>Installation terminée !</strong><br>
    Supprime ou renomme ce fichier <code>install.php</code> avant de mettre en production.<br>
    <a href="<?= BASE_URL ?>">→ Aller sur la page d'accueil</a>
  </div>
<?php endif; ?>
</body>
</html>
