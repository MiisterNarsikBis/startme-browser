# startme-browser

Page de démarrage personnalisée auto-hébergée, servie par PHP + MySQL.

> **Projet personnel** — Fait en quelques minutes pour un usage perso. Le code est fourni tel quel, sans garantie d'aucune sorte. Je ne suis pas responsable de son utilisation.

## Fonctionnalités

- Gestion de marque-pages par pages/catégories
- Widgets : météo, flux RSS, horloge
- Fond d'écran personnalisable (upload)
- Authentification par phrase mnémotechnique BIP39
- Interface d'administration
- Installation guidée via `install.php`

## Prérequis

- PHP 8.0+
- MySQL / MariaDB
- Serveur web (Apache, Nginx…)

## Installation

1. Cloner le dépôt dans le répertoire web
2. Copier `config.template.php` en `config.php` et renseigner les valeurs
3. Créer la base de données et lancer `install.php` depuis le navigateur
4. Supprimer `install.php` après installation

## Configuration

Toutes les options se trouvent dans `config.php` (créé depuis `config.template.php`) :

| Constante | Description |
|-----------|-------------|
| `DB_HOST` | Hôte MySQL |
| `DB_NAME` | Nom de la base |
| `DB_USER` / `DB_PASS` | Identifiants MySQL |
| `BASE_URL` | URL publique du site (sans slash final) |
| `RSS_CACHE_MINUTES` | Durée du cache RSS |
| `WEATHER_CACHE_MINUTES` | Durée du cache météo |

## Licence

MIT — libre d'utilisation, de modification et de distribution.
