# Développement local Docker

Environnement autonome pour tester l’interface, les sauvegardes de fichiers et de base,
les exports SQL et les tâches d’intégrité. Aucun service ou secret de production n’est nécessaire.

## Démarrage

Prérequis : Docker Engine et Docker Compose v2 ou ultérieur.
Depuis la racine du dépôt :

```bash
./docker/dev/start.sh
```

Si votre utilisateur n’a pas accès au service Docker :

```bash
sudo ./docker/dev/start.sh
```

Le script reprend l’UID/GID de l’utilisateur appelant, y compris sous `sudo` (`SUDO_UID`/`SUDO_GID`),
comme dans TyFramework. Il refuse une identité root. Il est aussi possible de définir explicitement
`WB_DEV_UID` et `WB_DEV_GID`, toujours avec des identifiants non root.

Au premier démarrage, le script génère `docker/dev/.env` (permissions 0600) avec cinq secrets aléatoires
et crée les dossiers `data` avec vos droits. Il construit PHP/Apache, démarre MariaDB et installe
les versions du `src/composer.lock`. Le premier lancement nécessite Internet et peut prendre plusieurs minutes.

- Interface : **http://127.0.0.1:8090/ui.php**
- Mot de passe : valeur `WB_DEV_UI_PASSWORD` dans `docker/dev/.env`.
- phpMyAdmin : **http://127.0.0.1:8091** ; utilisateur `backup_dev`, mot de passe `WB_DEV_DB_PASSWORD`.
  L’utilisateur root est également disponible avec `WB_DEV_DB_ROOT_PASSWORD` pour administrer la base de développement.

Les ports peuvent être changés via `HTTP_PORT` et `PHPMYADMIN_PORT` dans `.env`, puis relancer `start.sh`.
L’écoute reste limitée à `127.0.0.1`. MariaDB ne publie aucun port sur l’hôte.
Les secrets `.env` ne sont pas à utiliser en production.

## Ce qui est prêt à tester

- **Backups / demo_files** : archive du petit site de démonstration, avec son nom daté.
- **Backups / demo_database** : archive du dump de la base MariaDB `website_backup_dev`.
- **Archives locales** : une première archive est créée automatiquement ; recherche et téléchargement.
- **Tâches / demo_integrity** : création de l’inventaire et contrôle des fichiers de démonstration.
  L’inventaire initial est créé au premier démarrage. Modifier `data/site/notes.txt` permet de voir les différences.
  Les suppressions depuis les résultats concernent seulement ce site de test.
- **Export SQL** : opération `dump_and_download_db` avec `item=demo` et mot de passe en POST.
- **API des archives** : clé Bearer `WB_DEV_API_KEY`, indépendante du mot de passe de l’interface.

`WB_DEV_COMPRESSION=tar` est le défaut ; utiliser `phpzip` puis relancer pour tester les ZIP.
Aucun cron n’est installé. Les notifications, Google, IMAP et S3 ne sont pas configurés dans ce banc.
Les transferts, l’export et les tâches utilisent le dossier temporaire du projet : éviter de lancer
plusieurs sauvegardes ou tâches simultanément, car le code existant partage son répertoire `temp`.

## Isolation et permissions

Le code du dépôt est monté en lecture seule. Les modifications de code sur l’hôte sont visibles immédiatement ;
la reconstruction est nécessaire après modification du Dockerfile, du fichier Apache ou du `php.ini`.
Le serveur ne modifie pas le `src/Config/Config.php` local, même s’il existe : Apache charge explicitement
`php/bootstrap.php`, qui fournit la configuration de développement à partir de `Config.sample.php`.

Le serveur Apache démarre en root pour ouvrir le port 80, puis les processus PHP utilisent votre UID/GID.
Composer, l’initialisation, les tests et `php.sh` utilisent également votre identité.
Les données applicatives sont conservées dans des dossiers hôte ignorés par Git :

| Emplacement | Contenu |
| --- | --- |
| `data/vendor` | Dépendances Composer, montées dans `/workspace/src/vendor` |
| `data/composer` | Cache Composer |
| `data/site` | Fichiers du site de démonstration |
| `data/backups` | Archives locales et dossier temporaire des sauvegardes |
| `data/integrity` | Inventaire et marqueur d’initialisation |

La base utilise le volume Docker nommé `website-backup-dev_mariadb-data`, avec les droits propres à MariaDB.
Le script SQL initial ne s’exécute que lors de la première création du volume. Les redémarrages
conservent les modifications des fichiers, les archives, l’inventaire et la base.
Ne pas changer les mots de passe MariaDB de `.env` sans mettre à jour les utilisateurs dans la base existante.
Ne pas supprimer `.env` en conservant le volume : les nouveaux mots de passe ne correspondraient plus à la base.

Apache applique le `.htaccess` du projet et transmet l’en-tête Bearer à PHP.
Les dossiers `Config` et `vendor` sont explicitement interdits via HTTP ; les archives et les secrets
restent hors de la racine web. Le `.htaccess` de production n’est pas remplacé par un serveur PHP intégré.

## Commandes utiles

```bash
# Vérifications ciblées : authentification, chemins, téléchargements, export SQL réel et droits.
./docker/dev/test.sh

# Sauvegarde depuis la CLI du conteneur, avec la configuration DEV.
./docker/dev/php.sh backup.php -g demo_files
./docker/dev/php.sh backup.php -g demo_database
./docker/dev/php.sh tasks.php -g demo_integrity

# Arrêt sans suppression des données.
./docker/dev/stop.sh
```

Ces scripts acceptent également un lancement avec `sudo` si nécessaire.
`php.sh` utilise la configuration DEV pour les fichiers PHP exécutés ; avec `-r`, PHP ne charge
pas `auto_prepend_file`, il faut donc charger le bootstrap explicitement dans le code inline si nécessaire.

Pour les commandes Compose directes, charger l’identité d’abord :

```bash
cd docker/dev
source ./identity.sh
docker compose ps
docker compose logs --tail=100 web
```

Les deux suites isolées ne font aucun appel externe. Le contrôle HTTP supplémentaire appelle Apache
sur sa boucle locale, exporte la base de démonstration et crée une sauvegarde `demo_database`.
Il ne pilote aucun navigateur et ne teste pas de restauration SQL sur une base distante.
L’interface et phpMyAdmin restent à vérifier manuellement dans votre navigateur.

## API locale

L’environnement reste en HTTP sur la boucle locale pour simplifier les tests UI et cURL.
Par exemple, définir manuellement la variable `BACKUP_API_KEY` avec `WB_DEV_API_KEY` depuis `.env`, puis :

```bash
curl --fail -H "Authorization: Bearer $BACKUP_API_KEY" \
  'http://127.0.0.1:8090/api.php?operation=list_local_backups'
```

Le client `remote_scripts/script.php` impose volontairement HTTPS et ne peut donc pas cibler directement
cette URL HTTP. Pour tester ce client avec un vrai réseau, utiliser un endpoint HTTPS avec certificat valide.
Sa logique de transfert est couverte ici par la suite isolée avec transport simulé.

## Réinitialisation

`stop.sh` conserve les données. Pour repartir de zéro, arrêter l’environnement puis supprimer explicitement
le volume `website-backup-dev_mariadb-data` et les sous-dossiers de données souhaités.
Ces suppressions ne sont jamais exécutées par les scripts fournis.
Une réinitialisation complète de la base nécessite de supprimer son volume, pas uniquement les fichiers du site.
