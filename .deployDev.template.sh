#!/bin/bash

# Copier ce fichier en .deployDev.sh et renseigner les valeurs ci-dessous.
# .deployDev.sh est ignoré par git (voir .gitignore) — ne jamais committer les credentials.

SERVER="mon-serveur.example.com"
USER="mon-utilisateur-ssh"
REMOTE_PATH="/home/mon-utilisateur-ssh/mon-site.example.com"

echo "Déploiement vers $SERVER"
echo "================================"

# Build Tailwind CSS (requiert tailwindcss.exe à la racine du projet — voir README)
echo "Build Tailwind CSS..."
./tailwindcss.exe -i assets/css/tailwind.input.css -o assets/css/tailwind.css --minify 2>/dev/null
echo "Tailwind OK ($(wc -c < assets/css/tailwind.css) bytes)"

# Nettoyer les anciens fichiers sur le serveur
echo "Nettoyage des anciens fichiers sur le serveur..."
ssh $USER@$SERVER "rm -rf $REMOTE_PATH/api $REMOTE_PATH/assets/js $REMOTE_PATH/assets/css"

# Créer un fichier de commandes SFTP temporaire
SFTP_BATCH=$(mktemp)

cat > "$SFTP_BATCH" << EOF
cd $REMOTE_PATH
lcd $(pwd)

# Fichiers PHP racine
put admin.php
put auth.php
put config.php
put index.php
put logout.php
put manifest.php
put sw.js

# Fichier .htaccess
put .htaccess

# Dossiers
put -r api
put -r assets
put -r includes
put -r migrations

bye
EOF

echo "Upload des fichiers via SFTP..."
sftp -b "$SFTP_BATCH" $USER@$SERVER

rm -f "$SFTP_BATCH"

echo "Déploiement terminé avec succès !"
