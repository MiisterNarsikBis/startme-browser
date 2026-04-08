# startme-browser

Page de démarrage personnalisée auto-hébergée, servie par PHP + MySQL.

> **Projet personnel** — Fait pour un usage perso. Le code est fourni tel quel, sans garantie d'aucune sorte.

**Site démo :** https://start.miisternarsik.fr/

## Fonctionnalités

- Gestion de marque-pages (affichage grille ou liste, suppression au survol)
- Widgets : météo, flux RSS, notes, todo, horloge, recherche, embed, calendrier
- Météo avec autocomplétion (Open-Meteo) et recherche par ville depuis le widget
- Favicons mis en cache localement (30 jours, pas d'appel Google à chaque chargement)
- Fond d'écran personnalisable (couleur, dégradé, image uploadée)
- Authentification par phrase mnémotechnique BIP39 (12 mots)
- Auto-connexion persistante via cookie sécurisé (`HttpOnly`, renouvelé à chaque visite)
- Migrations de base de données automatiques au démarrage
- Interface d'administration (ajout/suppression/réorganisation des widgets)

## Prérequis

- PHP 8.1+
- MySQL / MariaDB
- Serveur web (Apache, Nginx…)

## Installation

1. Cloner le dépôt dans le répertoire web
2. Copier `config.template.php` en `config.php` et renseigner les valeurs
3. Les tables sont créées automatiquement au premier chargement (migrations auto)

## Configuration

Toutes les options se trouvent dans `config.php` :

| Constante | Description |
|-----------|-------------|
| `DB_HOST` | Hôte MySQL |
| `DB_NAME` | Nom de la base |
| `DB_USER` / `DB_PASS` | Identifiants MySQL |
| `BASE_URL` | URL publique du site (sans slash final) |
| `RSS_CACHE_MINUTES` | Durée du cache RSS (défaut : 60 min) |
| `WEATHER_CACHE_MINUTES` | Durée du cache météo (défaut : 30 min) |
| `SESSION_LIFETIME` | Durée de session (défaut : 30 jours) |

## Migrations

Les migrations de base de données sont appliquées automatiquement au démarrage.  
Pour visualiser l'état : accéder à `/migrations/` depuis le navigateur.  
Pour ajouter une migration : créer un fichier `migrations/00X_nom.php`.

## Licence

MIT — libre d'utilisation, de modification et de distribution.
