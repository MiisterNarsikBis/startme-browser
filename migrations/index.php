<?php
// ============================================================
//  migrations/index.php — Interface de visualisation
//  Affiche le statut de chaque migration.
// ============================================================
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once __DIR__ . '/runner.php';

$results  = migrations_run();
$files    = glob(MIGRATIONS_DIR . '/[0-9]*.php');
$hasError = !empty(array_filter($results, fn($r) => $r['status'] === 'error'));
$newCount = count(array_filter($results, fn($r) => $r['status'] === 'ok'));

?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Migrations — Startme</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Courier New', monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; margin: 0; }
  h1 { color: #818cf8; margin-bottom: 0.25rem; }
  p.sub { color: #475569; margin-top: 0; margin-bottom: 2rem; font-size: 0.85rem; }
  .list { display: flex; flex-direction: column; gap: 0.5rem; max-width: 600px; }
  .row { display: flex; align-items: center; gap: 1rem; padding: 0.6rem 1rem; border-radius: 8px; font-size: 0.9rem; }
  .ok    { background: #14532d55; border: 1px solid #16a34a55; }
  .skip  { background: #1e293b;   border: 1px solid #334155; color: #64748b; }
  .error { background: #450a0a55; border: 1px solid #b91c1c55; }
  .badge { font-size: 0.75rem; font-weight: bold; padding: 0.2rem 0.5rem; border-radius: 4px; white-space: nowrap; }
  .badge.ok    { background: #16a34a; color: #fff; }
  .badge.skip  { background: #334155; color: #94a3b8; }
  .badge.error { background: #b91c1c; color: #fff; }
  .msg { font-size: 0.78rem; color: #f87171; margin-top: 0.25rem; }
  .summary { margin-top: 2rem; padding: 1rem 1.25rem; border-radius: 10px; max-width: 600px; font-size: 0.9rem; }
  .summary.all-ok     { background: #14532d44; border: 1px solid #16a34a66; color: #4ade80; }
  .summary.has-err    { background: #450a0a44; border: 1px solid #b91c1c66; color: #f87171; }
  .summary.up-to-date { background: #1e293b;   border: 1px solid #334155;   color: #64748b; }
</style>
</head>
<body>

<h1>⚙️ Migrations Startme</h1>
<p class="sub">Base : <strong style="color:#94a3b8"><?= DB_NAME ?></strong> — <?= count($files) ?> migration(s) trouvée(s)</p>

<div class="list">
<?php foreach ($results as $r): ?>
  <div class="row <?= $r['status'] ?>">
    <span class="badge <?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span>
    <div>
      <div><?= htmlspecialchars($r['name']) ?></div>
      <?php if (!empty($r['msg'])): ?>
        <div class="msg">❌ <?= htmlspecialchars($r['msg']) ?></div>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<div class="summary <?= $hasError ? 'has-err' : ($newCount > 0 ? 'all-ok' : 'up-to-date') ?>">
<?php if ($hasError): ?>
  ❌ Erreur lors de la migration — corrige le problème et recharge la page.
<?php elseif ($newCount > 0): ?>
  ✅ <?= $newCount ?> migration(s) appliquée(s) avec succès.
<?php else: ?>
  ✔ Base de données à jour — aucune migration à jouer.
<?php endif; ?>
</div>

</body>
</html>
