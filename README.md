# startme-browser

Page de démarrage personnalisée auto-hébergée, servie par PHP + MySQL.

> **Projet personnel** — Fait pour un usage perso. Le code est fourni tel quel, sans garantie d'aucune sorte.

**Site démo :** https://start.miisternarsik.fr/

---

## Fonctionnalités

### Widgets disponibles
| Widget | Description |
|--------|-------------|
| **Marque-pages** | Grille de liens avec favicons mis en cache (30 j), affichage grille ou liste |
| **Météo** | Conditions actuelles + prévisions 5 jours, recherche par ville ou code postal, géolocalisation |
| **Flux RSS** | Multi-flux avec onglets, cache serveur configurable, **rafraîchissement automatique** (15/30/60/120 min) |
| **Notes** | Zone de texte libre, sauvegarde automatique, **rendu Markdown** avec toggle édition/aperçu |
| **Todo** | Liste de tâches avec cases à cocher, **réorganisation par glisser-déposer**, **dates d'échéance** avec indicateur visuel (rouge/orange/gris) |
| **Recherche** | Barre de recherche (Google, DuckDuckGo, Brave, Bing, **Kagi, Perplexity, Ecosia**), bangs (`!g`, `!yt`, `!kagi`, `!eco`, `!pp`…) |
| **Horloge** | Heure en temps réel |
| **Embed** | Intégration d'une URL en iframe, rafraîchissement automatique configurable |
| **Calendrier** | Affichage mensuel |
| **Image** | Bloc image personnalisé |
| **Pomodoro** | Timer Pomodoro avec phases travail/pause, persistance localStorage, notifications navigateur |
| **GitHub / GitLab** | Heatmap d'activité, événements récents |
| **Countdown** | Compte à rebours vers une date cible |
| **Crypto** | Cours de cryptomonnaies en temps réel |
| **Lofi** | Lecteur de radios lo-fi intégré |
| **JSON** | Requête HTTP vers une URL, extraction de champs, cache configurable, multi-sources |

### Général
- **Onboarding** : message de bienvenue et lien vers l'admin quand la page est vide
- Pages multiples avec navigation par onglets
- Couleur d'accent personnalisable par page
- Fond d'écran par page : couleur, dégradé ou image uploadée
- Galerie d'images avec déduplication MD5 et suppression
- Réorganisation des widgets par glisser-déposer (grille) et **duplication de widget**
- **Réorganisation des pages** par glisser-déposer (barre d'admin)
- Palette de commandes (`Ctrl+K`) et raccourcis clavier globaux
- Interface d'administration dédiée (`/admin`)
- Import / Export de la configuration (sauvegarde complète, inclut les dates d'échéance)
- Authentification par phrase mnémotechnique BIP39 (12 mots)
- Confirmation avant déconnexion (rappel de la phrase secrète)
- Auto-connexion persistante via cookie sécurisé (`HttpOnly`, rotation atomique anti-race condition)
- Protection brute-force sur la page de connexion (rate limiting par IP)
- Migrations de base de données appliquées automatiquement au démarrage
- API REST v1 (`/api/v1/`) pour toutes les opérations
- PWA installable (Service Worker + Web App Manifest)
- CSS généré via **Tailwind CLI standalone** (pas de CDN, pas de npm requis)

---

## Prérequis

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Serveur web (Apache, Nginx…)
- Extensions PHP : `pdo_mysql`, `simplexml`, `fileinfo`

---

## Installation

1. Cloner le dépôt dans le répertoire web
2. Copier `config.template.php` en `config.php` et renseigner les valeurs
3. Pointer le virtual host sur la racine du projet
4. Visiter le site — les tables sont créées automatiquement au premier chargement
5. Se connecter et générer une phrase mnémotechnique depuis la page d'accueil

---

## Configuration

Toutes les options se trouvent dans `config.php` :

| Constante | Description | Défaut |
|-----------|-------------|--------|
| `DB_HOST` | Hôte MySQL | `127.0.0.1` |
| `DB_NAME` | Nom de la base | — |
| `DB_USER` / `DB_PASS` | Identifiants MySQL | — |
| `BASE_URL` | URL publique du site (sans slash final) | — |
| `RSS_CACHE_MINUTES` | Durée du cache RSS en minutes | `60` |
| `WEATHER_CACHE_MINUTES` | Durée du cache météo en minutes | `30` |
| `SESSION_LIFETIME` | Durée de session en secondes | `2592000` (30 j) |
| `UPLOAD_DIR` | Dossier d'upload des fonds d'écran | `assets/uploads/` |
| `CACHE_DIR` | Dossier cache fichiers (météo) | `cache/` |

---

## Build CSS (Tailwind)

Le CSS est généré via le [Tailwind CSS CLI standalone](https://tailwindcss.com/blog/standalone-cli) — aucun npm requis.

```bash
# Télécharger le binaire (une seule fois, ignoré par git)
curl -sL https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-windows-x64.exe -o tailwindcss.exe

# Build unique
./tailwindcss.exe -i assets/css/tailwind.input.css -o assets/css/tailwind.css --minify

# Watcher (dev)
./tailwindcss.exe --watch -i assets/css/tailwind.input.css -o assets/css/tailwind.css
```

Le deploy script (`deployDev.sh`) lance le build automatiquement avant chaque upload.

---

## API REST v1

Toutes les requêtes passent par `/api/v1/{ressource}`.

| Ressource | Méthodes | Description |
|-----------|----------|-------------|
| `auth` | `POST /login`, `POST /logout` | Authentification |
| `pages` | `GET`, `POST`, `PUT /{id}`, `DELETE /{id}` | Gestion des pages |
| `widgets` | `GET`, `POST`, `PUT /{id}`, `DELETE /{id}`, `POST /reorder` | Widgets |
| `bookmarks` | `GET`, `POST`, `DELETE /{id}`, `POST /reorder` | Marque-pages |
| `notes` | `POST` | Sauvegarde de note |
| `todos` | `POST`, `PUT /{id}`, `DELETE /{id}`, `POST /reorder` | Tâches (toggle done, mise à jour `due_date`, réorganisation) |
| `weather` | `GET ?city=` ou `?lat=&lon=` | Données météo |
| `rss` | `GET ?widget_id=&url=` | Lecture de flux RSS |
| `upload` | `POST ?page_id=` | Upload fond d'écran / galerie |
| `github` | `GET ?username=` | Activité GitHub / GitLab |
| `crypto` | `GET ?ids=` | Cours de cryptomonnaies |
| `json` | `GET ?widget_id=` | Proxy + cache pour widget JSON |
| `backup` | `GET` (export), `POST` (import) | Import / Export de la configuration |

---

## Migrations

Les migrations sont appliquées automatiquement à chaque chargement (si non déjà appliquées).

```
migrations/
├── 001_initial_schema.php               — Tables de base
├── 002_remember_tokens.php              — Tokens "se souvenir de moi"
├── 003_login_attempts.php               — Rate limiting connexion
├── 004_widget_image_type.php            — Type widget image
├── 005_rss_cache_unique_per_feed.php    — Cache RSS par flux (fix contrainte)
├── 006_widget_pomodoro_github.php       — Widgets Pomodoro et GitHub
├── 007_widget_countdown_crypto_lofi.php — Widgets Countdown, Crypto et Lofi
├── 008_pages_accent_color.php           — Couleur d'accent par page
├── 009_widget_json.php                  — Widget JSON (cache + sélection de champs)
├── 010_json_cache_multi_url.php         — Widget JSON multi-sources
└── 011_todo_due_date.php                — Colonne due_date sur les todos
```

Pour ajouter une migration : créer `migrations/00X_description.php`.  
Pour visualiser l'état : accéder à `/migrations/` depuis le navigateur (authentifié).

---

## Structure du projet

```
startme/
├── api/v1/
│   ├── router.php                — Routeur REST
│   └── resources/                — Ressources de l'API
├── assets/
│   ├── css/app.css               — Styles personnalisés
│   ├── css/tailwind.css          — CSS Tailwind généré (build artifact)
│   ├── css/tailwind.input.css    — Point d'entrée Tailwind CLI
│   ├── js/app.js                 — Logique front principale
│   ├── js/admin.js               — Interface d'administration
│   └── uploads/                  — Fonds d'écran uploadés
├── cache/                        — Cache fichiers météo
├── includes/
│   ├── db.php                    — Connexion PDO + helpers
│   ├── functions.php             — Fonctions utilitaires
│   ├── auth_check.php            — Vérification session / remember_me
│   └── bip39_fr.php              — Liste de mots BIP39 (français)
├── migrations/                   — Migrations auto
├── tailwind.config.js            — Configuration Tailwind v3
├── config.php                    — Configuration (à créer depuis le template)
├── config.template.php           — Template de configuration
├── manifest.php                  — Web App Manifest (PWA)
├── sw.js                         — Service Worker (PWA, cache-first assets)
├── index.php                     — Page principale
├── admin.php                     — Interface d'administration
├── auth.php                      — Page de connexion
└── logout.php                    — Déconnexion
```

---

## Licence

MIT — libre d'utilisation, de modification et de distribution.
