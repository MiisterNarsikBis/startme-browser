<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

// Déjà connecté → rediriger vers la première page
$uid = get_current_user_id();
if ($uid) {
    $pages = get_user_pages($uid);
    $slug  = $pages[0]['slug'] ?? 'accueil';
    header('Location: ' . BASE_URL . '/p/' . $slug);
    exit;
}
?><!DOCTYPE html>
<html lang="fr" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — Startme</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: { DEFAULT: '#6366f1', dark: '#4f46e5' }
      },
      fontFamily: { mono: ['JetBrains Mono', 'Fira Code', 'monospace'] }
    }
  }
}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style type="text/tailwindcss">
  body { font-family: 'Inter', sans-serif; }
  .word-chip {
    @apply bg-white/10 border border-white/20 rounded-lg px-3 py-1.5 text-sm font-mono
           cursor-pointer hover:bg-white/20 transition select-none;
  }
  .seed-input {
    @apply bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm font-mono
           w-full focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand
           text-white placeholder-white/40;
  }
  .glass {
    background: rgba(255,255,255,0.07);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.12);
  }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 text-white flex items-center justify-center p-4">

<div class="w-full max-w-lg">

  <!-- Logo -->
  <div class="text-center mb-8">
    <div class="text-5xl mb-3">🚀</div>
    <h1 class="text-3xl font-bold text-white">Startme</h1>
    <p class="text-white/50 mt-1">Votre page d'accueil personnalisée</p>
  </div>

  <!-- Tabs -->
  <div class="glass rounded-2xl p-1 flex mb-6">
    <button onclick="showTab('login')" id="tab-login"
      class="flex-1 py-2.5 rounded-xl text-sm font-medium transition tab-active">
      Me connecter
    </button>
    <button onclick="showTab('new')" id="tab-new"
      class="flex-1 py-2.5 rounded-xl text-sm font-medium transition text-white/50 hover:text-white">
      Créer un espace
    </button>
  </div>

  <!-- Connexion -->
  <div id="panel-login" class="glass rounded-2xl p-6">
    <p class="text-white/60 text-sm mb-3">
      Collez ou tapez vos 12 mots séparés par des espaces.
    </p>
    <textarea id="seed-textarea" rows="3" autocomplete="off" autocorrect="off"
      autocapitalize="off" spellcheck="false"
      placeholder="mot1 mot2 mot3 mot4 mot5 mot6 mot7 mot8 mot9 mot10 mot11 mot12"
      class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-sm font-mono
             text-white placeholder-white/30 focus:outline-none focus:border-brand focus:ring-1
             focus:ring-brand resize-none mb-1"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();doLogin()}"
    ></textarea>
    <p id="word-count" class="text-xs text-white/30 text-right mb-4">0 / 12 mots</p>
    <button onclick="doLogin()" class="w-full bg-brand hover:bg-brand-dark text-white py-3 rounded-xl font-semibold transition">
      Connexion →
    </button>
    <p id="login-error" class="text-red-400 text-sm mt-3 hidden text-center"></p>
  </div>

  <!-- Nouvel espace -->
  <div id="panel-new" class="glass rounded-2xl p-6 hidden">
    <p class="text-white/60 text-sm mb-4">
      Votre phrase secrète de <strong class="text-white">12 mots</strong>.
      Notez-la soigneusement — c'est votre seul moyen de connexion.
    </p>

    <div id="seed-words" class="grid grid-cols-3 gap-2 mb-5">
      <div class="col-span-3 text-center text-white/30 text-sm py-4">
        Cliquez sur "Générer" pour créer votre phrase secrète
      </div>
    </div>

    <div class="flex gap-3 mb-5">
      <button onclick="generateSeed()" class="flex-1 bg-white/10 hover:bg-white/20 py-2.5 rounded-xl text-sm font-medium transition">
        🎲 Générer
      </button>
      <button onclick="copySeed()" id="btn-copy" class="flex-1 bg-white/10 hover:bg-white/20 py-2.5 rounded-xl text-sm font-medium transition disabled:opacity-40" disabled>
        📋 Copier
      </button>
    </div>

    <div id="confirm-box" class="hidden">
      <label class="flex items-start gap-3 cursor-pointer mb-4">
        <input type="checkbox" id="confirm-saved" class="mt-0.5 accent-indigo-500 w-4 h-4 flex-shrink-0">
        <span class="text-sm text-white/70">
          J'ai noté ma phrase secrète dans un endroit sûr. Je comprends que je ne pourrai plus la récupérer si je la perds.
        </span>
      </label>
      <button onclick="doCreate()" id="btn-create"
        class="w-full bg-brand hover:bg-brand-dark text-white py-3 rounded-xl font-semibold transition disabled:opacity-40 disabled:cursor-not-allowed"
        disabled>
        Créer mon espace →
      </button>
    </div>

    <p id="create-error" class="text-red-400 text-sm mt-3 hidden text-center"></p>
  </div>

</div>

<script>
let generatedWords = [];

function showTab(tab) {
  const tabs = ['login', 'new'];
  tabs.forEach(t => {
    document.getElementById('panel-' + t).classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('tab-' + t);
    if (t === tab) {
      btn.classList.add('bg-white/10', 'text-white');
      btn.classList.remove('text-white/50');
    } else {
      btn.classList.remove('bg-white/10', 'text-white');
      btn.classList.add('text-white/50');
    }
  });
}
showTab('login');

// Compteur de mots en temps réel
document.getElementById('seed-textarea')?.addEventListener('input', function() {
  const count = parseWords(this.value).length;
  const el = document.getElementById('word-count');
  el.textContent = count + ' / 12 mots';
  el.className = 'text-xs text-right mb-4 ' + (count === 12 ? 'text-green-400' : 'text-white/30');
});

function parseWords(text) {
  return text.trim().toLowerCase().split(/\s+/).filter(w => w.length > 0);
}

async function doLogin() {
  const textarea = document.getElementById('seed-textarea');
  const words    = parseWords(textarea.value);
  const err      = document.getElementById('login-error');
  err.classList.add('hidden');

  if (words.length !== 12) {
    err.textContent = `${words.length} mot${words.length > 1 ? 's' : ''} détecté${words.length > 1 ? 's' : ''} — il en faut exactement 12.`;
    err.classList.remove('hidden');
    return;
  }

  const res = await fetch('<?= BASE_URL ?>/api/auth.php?action=login', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ words })
  });
  const data = await res.json();

  if (data.error) {
    err.textContent = data.error;
    err.classList.remove('hidden');
  } else {
    window.location.href = data.redirect;
  }
}

async function generateSeed() {
  const res  = await fetch('<?= BASE_URL ?>/api/auth.php?action=generate', { method: 'POST' });
  const data = await res.json();
  generatedWords = data.words;

  const grid = document.getElementById('seed-words');
  grid.innerHTML = generatedWords.map((w, i) =>
    `<div class="flex items-center gap-1.5 bg-white/10 rounded-lg px-2.5 py-2">
       <span class="text-white/30 text-xs font-mono w-4 text-right">${i+1}.</span>
       <span class="font-mono text-sm font-medium">${w}</span>
     </div>`
  ).join('');

  document.getElementById('btn-copy').disabled = false;
  document.getElementById('confirm-box').classList.remove('hidden');

  const check = document.getElementById('confirm-saved');
  check.addEventListener('change', () => {
    document.getElementById('btn-create').disabled = !check.checked;
  });
}

function copySeed() {
  if (!generatedWords.length) return;
  navigator.clipboard.writeText(generatedWords.join(' ')).then(() => {
    const btn = document.getElementById('btn-copy');
    btn.textContent = '✅ Copié !';
    setTimeout(() => btn.textContent = '📋 Copier', 2000);
  });
}

async function doCreate() {
  if (!generatedWords.length) return;
  const err = document.getElementById('create-error');
  err.classList.add('hidden');

  const res = await fetch('<?= BASE_URL ?>/api/auth.php?action=login', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ words: generatedWords })
  });
  const data = await res.json();

  if (data.error) {
    err.textContent = data.error;
    err.classList.remove('hidden');
  } else {
    window.location.href = data.redirect;
  }
}
</script>
</body>
</html>
