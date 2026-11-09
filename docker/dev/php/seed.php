<?php
require_once '/workspace/src/vendor/autoload.php';

foreach (['/data/site', '/data/backups', '/data/integrity'] as $directory) {
    if (!is_writable($directory)) throw new RuntimeException('Dossier DEV non accessible en écriture : ' . $directory);
}
foreach (['index.php', 'notes.txt'] as $name) {
    if (!file_exists('/data/site/' . $name) && !copy('/opt/website-backup-fixtures/' . $name, '/data/site/' . $name)) {
        throw new RuntimeException('Initialisation du site de test impossible');
    }
}
if (!file_exists('/data/integrity/site.json')) {
    \App\Tasks::runSingleTask('demo_integrity', 'Créer l’inventaire');
}
if (!file_exists('/data/integrity/.seeded')) {
    \App\Backup::run('demo_files');
    if ((new \App\LocalBackups('/data/backups'))->list() === []) throw new RuntimeException('Archive de démonstration non créée');
    if (file_put_contents('/data/integrity/.seeded', '1') === false) throw new RuntimeException('Initialisation DEV impossible');
}
echo "Données DEV disponibles ; configuration de production ignorée.\n";
