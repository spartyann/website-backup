<?php

// Copier vers config.php. Ne jamais publier ce dossier ni versionner les secrets.
// Exécuter avec : php remote_scripts/script.php
// HTTPS et certificat valide obligatoires ; les redirections sont refusées.
define('CONF', [
    [
        'enabled' => false,
        'type' => 'local_backups',
        'api_url' => 'https://<server>/<dir>/api.php', // Sans paramètres.
        'api_key' => '', // LOCAL_BACKUP_API_KEY du serveur, limitée à la lecture des archives.
        'destination' => __DIR__ . '/backups',
        'max_age_days' => null, // null = tout télécharger ; 7 = archives de moins de 7 jours.
        'retention_days' => null, // null = aucune suppression ; 30 = supprimer les copies de plus de 30 jours.
        'min_versions' => 1, // Minimum conservé PAR SÉRIE (préfixe + suffixe), même si les copies sont anciennes.
        'backup_timezone' => 'Europe/Paris', // Même fuseau PHP que celui du serveur qui crée les noms.
        // Âge et ordre des versions : date Y-m-d__H-i-s du NOM, jamais date du téléchargement.
        // La purge ne s'exécute qu'après une synchronisation réussie, dans ce seul dossier.
        // Ce dossier doit être réservé aux copies d'archives de cette source.
    ],
    [
        'enabled' => false,
        'type' => 'dump_sql',
        'urls' => [
            'https://<server>/<dir>/api.php?item=<ITEM>&operation=dump_and_download_db',
        ],
        'api_password' => '', // UI_PASSWORD, envoyé en POST, jamais dans l’URL.
        // La clé des archives ne donne volontairement aucun accès aux exports SQL.
        'db_host' => 'localhost',
        'db_user' => 'xxxxxxxxxxxxxxxxxx',
        'db_pwd' => 'xxxxxxxxxxxxxxxxxx',
        'db_db' => 'xxxxxxxxxxxxxxxxxx',
        'db_root_user' => 'root',
        'db_root_pwd' => 'xxxxxxxxxxxxxxxxxx',
    ],
]);
