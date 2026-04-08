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
| **Flux RSS** | Multi-flux avec onglets, cache serveur configurable, date de dernière mise à jour |
| **Notes** | Zone de texte libre, sauvegarde automatique |
| **Todo** | Liste de tâches avec cases à cocher et réorganisation |
| **Recherche** | Barre de recherche vers le moteur de son choix |
| **Horloge** | Heure en temps réel |
| **Embed** | Intégration d'une URL en iframe |
| **Calendrier** | Affichage mensuel |
| **Image** | Bloc image personnalisé |

### Général
- Pages multiples avec navigation par onglets
- Fond d'écran par page : couleur, dégradé ou image uploadée
- Réorganisation des widgets par glisser-déposer (grille)
- Interface d'administration dédiée (`/admin`)
- Authentification par phrase mnémotechnique BIP39 (12 mots)
- Auto-connexion persistante via cookie sécurisé (`HttpOnly`, renouvelé à chaque visite)
- Protection brute-force sur la page de connexion (rate limiting par IP)
- Migrations de base de données appliquées automatiquement au démarrage
- API REST v1 (`/api/v1/`) pour toutes les opérations

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

> **Important** : avant de mettre en production, désactiver `display_errors` dans `config.php`.

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

## API REST v1

Toutes les requêtes passent par `/api/v1/{ressource}`.

| Ressource | Méthodes | Description |
|-----------|----------|-------------|
| `auth` | `POST /login`, `POST /logout` | Authentification |
| `pages` | `GET`, `POST`, `PUT /{id}`, `DELETE /{id}` | Gestion des pages |
| `widgets` | `GET`, `POST`, `PUT /{id}`, `DELETE /{id}`, `POST /reorder` | Widgets |
| `bookmarks` | `GET`, `POST`, `DELETE /{id}`, `POST /reorder` | Marque-pages |
| `notes` | `POST` | Sauvegarde de note |
| `todos` | `POST`, `PUT /{id}`, `DELETE /{id}` | Tâches |
| `weather` | `GET ?city=` ou `?lat=&lon=` | Données météo |
| `rss` | `GET ?widget_id=&url=` | Lecture de flux RSS |
| `upload` | `POST ?page_id=` | Upload fond d'écran |

---

## Migrations

Les migrations sont appliquées automatiquement à chaque chargement (si non déjà appliquées).

```
migrations/
├── 001_initial_schema.php        — Tables de base
├── 002_remember_tokens.php       — Tokens "se souvenir de moi"
├── 003_login_attempts.php        — Rate limiting connexion
├── 004_widget_image_type.php     — Type widget image
└── 005_rss_cache_unique_per_feed.php — Cache RSS par flux (fix contrainte)
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
│   ├── css/app.css               — Styles (Tailwind)
│   ├── js/app.js                 — Logique front principale
│   ├── js/admin.js               — Interface d'administration
│   └── uploads/                  — Fonds d'écran uploadés
├── cache/                        — Cache fichiers météo
├── includes/
│   ├── db.php                    — Connexion PDO + helpers
│   ├── functions.php             — Fonctions utilitaires
│   ├── auth_check.php            — Vérification session
│   └── bip39_fr.php              — Liste de mots BIP39 (français)
├── migrations/                   — Migrations auto
├── config.php                    — Configuration (à créer depuis le template)
├── config.template.php           — Template de configuration
├── index.php                     — Page principale
├── admin.php                     — Interface d'administration
├── auth.php                      — Page de connexion
└── logout.php                    — Déconnexion
```

---

## Licence

MIT — libre d'utilisation, de modification et de distribution.
