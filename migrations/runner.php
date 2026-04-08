<?php
// ============================================================
//  migrations/runner.php — Logique d'exécution des migrations
//  Inclus par index.php (affichage) et config.php (auto-run)
// ============================================================

define('MIGRATIONS_DIR',  __DIR__);
define('MIGRATIONS_LOCK', ROOT_PATH . 'cache/migrations.lock');

/**
 * Retourne true si des migrations sont en attente
 * (comparaison rapide par fichier lock, sans toucher à la DB).
 */
function migrations_pending(): bool {
    $files = glob(MIGRATIONS_DIR . '/[0-9]*.php');
    $count = count($files);
    if (!file_exists(MIGRATIONS_LOCK)) return $count > 0;
    return (int)file_get_contents(MIGRATIONS_LOCK) !== $count;
}

/**
 * Joue toutes les migrations manquantes.
 * Retourne un tableau de résultats :
 *   ['name' => '001_...php', 'status' => 'ok'|'skip'|'error', 'msg' => '...']
 */
function migrations_run(): array {
    // Créer la table de suivi si absente
    db()->exec("CREATE TABLE IF NOT EXISTS migrations (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(200) NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $files = glob(MIGRATIONS_DIR . '/[0-9]*.php');
    sort($files);

    $applied = array_column(
        db_fetchAll('SELECT name FROM migrations'),
        'name'
    );

    $results = [];

    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $applied, true)) {
            $results[] = ['name' => $name, 'status' => 'skip'];
            continue;
        }
        try {
            require $file;
            db_query('INSERT INTO migrations (name) VALUES (?)', [$name]);
            $results[] = ['name' => $name, 'status' => 'ok'];
        } catch (Throwable $e) {
            $results[] = ['name' => $name, 'status' => 'error', 'msg' => $e->getMessage()];
            break; // Arrêt à la première erreur
        }
    }

    // Mettre à jour le lock uniquement si aucune erreur
    $hasError = !empty(array_filter($results, fn($r) => $r['status'] === 'error'));
    if (!$hasError) {
        if (!is_dir(dirname(MIGRATIONS_LOCK))) mkdir(dirname(MIGRATIONS_LOCK), 0755, true);
        file_put_contents(MIGRATIONS_LOCK, (string)count($files));
    }

    return $results;
}

/**
 * Auto-run silencieux : appelé au bootstrap.
 * Ne fait rien si le lock est à jour (zéro DB hit).
 */
function migrations_auto(): void {
    if (!migrations_pending()) return;
    migrations_run();
}
