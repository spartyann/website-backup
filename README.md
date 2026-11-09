# website-backup

## Présentation
Ce projet permet d'effectuer des sauvegardes de sites PHP/MySql/MariaDB. Il est utilisable sur des hébergeurs comme OVH.

*This project allows to make backups of PHP/MySql/MariaDB sites. It is usable on hosts like OVH.*

## Fonctionnalités

- Dump de la base de données en deux options:
	- Avec la libraire **[druidfi\mysqldump-php]** (toutes ses options 
	sont configurables)
	- Avec la commande **mysqldump** (toutes ses options sont configurables)
- Compression du dump avec les fichiers du site:
	- ZIP : Extension PHP Zip
	- TAR : Commande **tar** avec conservation des permissions des fichiers
- Prise en charge de plusieurs répertoires de site
- Suppression des vielles sauvegardes

## Développement local avec Docker

Un environnement isolé (Apache/PHP, MariaDB et phpMyAdmin) est disponible dans
[`docker/dev`](docker/dev/README.md), avec alignement des UID/GID locaux et données de démonstration.

```bash
./docker/dev/start.sh
# Puis ouvrir http://127.0.0.1:8090/ui.php
```

Le mot de passe est généré dans `docker/dev/.env` (`WB_DEV_UI_PASSWORD`).
Cet environnement ne modifie pas votre configuration de production.

## Installation

Récupérez le contenu du dossier **"src"** et copiez-le sur votre hébergement dans un dossier non accessible depuis le web. (inutile de conserver le nom de dossier "src")

Copiez le fichier **Config/Config.sample.php** vers **Config/Config.php**
Ouvrez le fichier **Config/Config.php** et:
- Renommez le nom de la classe: _Config____Sample_ en _Config_
- Modifiez les paramètres en fonction de vos souhaits

Pour une execution automatique, paramétrez un cron job (consultez la documentation de l'hébergeur) sur le script PHP: _backup.php_

## Lancement

### Via CLI

```
php .\backup.php

# Options:
php .\backup.php -g <Groupes de sauvegarde à lancer> --verbose
```

### Via URL

URL: `https://<YOUR_URL>/backup.php?token=<token>`

Avec les options: `https://<YOUR_URL>/backup.php?token=<token>&g=<Groupes de sauvegarde à lancer>&verbose=1`

Vous pouvez aussi forcer le remplacement des `<br />` par `\n` avec l'option: `&br=0`

Le paramètre `<token>` doit être spécifié dans le fichier de Configuration

Le paramètre `<Groupes de sauvegarde à lancer>` contient la liste des groupes à lancer séparés par une virgule `,`

Exemples:
```
php .\backup.php -g group1,group2 --verbose

URL: https://<YOUR_URL>/backup.php?token=xxxxx&g=group1,group2&verbose=1
```

### Via Interface

URL: `https://<YOUR_URL>/ui.php`

Utilisez le mot de passe défini en Config.

### Archives locales dans l’interface

L’onglet **Archives locales** permet de rechercher et télécharger les archives finalisées `.tar` et `.zip`
présentes directement dans `Config::localStorageBackupDir()`, triées par date de sauvegarde décroissante (date extraite du nom).
Les noms doivent contenir la date au format produit par le projet (`2026-11-09__10-00-00`).
Les sous-dossiers, le dossier `temp` et les liens symboliques sont exclus.
Le bouton **Actualiser** recharge la liste, notamment après une nouvelle sauvegarde.

### Via API

Toutes les réponses utilisent `Cache-Control: no-store`. Les erreurs sont renvoyées en JSON avec
`success: false` et un statut HTTP : 400 (paramètres), 401 (authentification), 403 (droits),
404 (fichier ou élément absent), 405 (méthode), 500 (erreur interne).
Utiliser HTTPS pour protéger les secrets et les sauvegardes pendant le transfert.

**Migration : `apipwd` dans l’URL est désormais refusé sur toute l’API**, même s’il est aussi envoyé en POST.
Les opérations existantes (`login`, `load`, `run_backup`, `run_tasks`, `run_single_task`, `delete_item`,
`dump_and_download_db`) exigent POST et le mot de passe `UI_PASSWORD` dans le corps du formulaire.
La réponse `load` ne renvoie plus la configuration complète des groupes, uniquement leurs noms
et les informations de tâches nécessaires à l’interface.

Exemple d’export SQL des tables configurées dans `Config::downloadDBApi()` :

```bash
# Définir BACKUP_UI_PASSWORD dans l’environnement du script.
curl --fail --request POST \
  --data-urlencode "apipwd=$BACKUP_UI_PASSWORD" \
  --data-urlencode 'operation=dump_and_download_db' \
  --data-urlencode 'item=mon_export' \
  --output dump.sql \
  'https://exemple.fr/api.php'
```

Les deux nouvelles opérations d’archives acceptent :

- **GET ou POST avec `Authorization: Bearer <clé>`**, pour les scripts ;
- **POST avec `apipwd` dans le corps**, pour l’interface.

Ajouter `public const LOCAL_BACKUP_API_KEY = 'votre_cle';` dans votre `Config.php` existant.
Une clé absente ou vide désactive l’accès Bearer. Générer une clé aléatoire :

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Cette clé autorise uniquement la liste et le téléchargement des archives locales. Elle ne permet
ni l’export SQL, ni le lancement des sauvegardes, ni les tâches, ni les suppressions.
Pour révoquer la clé, la changer dans `Config.php` et mettre à jour les scripts clients.
L’en-tête `Authorization` doit être transmis à PHP par l’hébergement ; une règle est fournie dans
`src/.htaccess` pour Apache. Si un proxy ou une autre configuration serveur filtre cet en-tête,
il faut adapter sa configuration.

```bash
# Définir BACKUP_API_KEY dans l’environnement du script.
curl --fail \
  -H "Authorization: Bearer $BACKUP_API_KEY" \
  'https://exemple.fr/api.php?operation=list_local_backups'
```

Pour ne lister que les archives créées il y a **moins de 7 jours**, ajouter `max_age_days=7` :

```bash
curl --fail --get \
  -H "Authorization: Bearer $BACKUP_API_KEY" \
  --data-urlencode 'operation=list_local_backups' \
  --data-urlencode 'max_age_days=7' \
  'https://exemple.fr/api.php'
```

`max_age_days` est facultatif ; absent, toutes les archives reconnues sont listées.
Il doit être un entier décimal de 1 à 365000, sans signe, espace ou décimales ; une valeur invalide renvoie 400.
La date de création vient du segment `Y-m-d__H-i-s` du nom, interprété dans le fuseau PHP du serveur
qui crée les sauvegardes. Une archive exactement à la limite d’âge est exclue du filtre « moins de ».
`created_at` expose cette date ; `modified_at` reste disponible à titre informatif.
Les noms avec une date invalide ou plusieurs segments de date complets sont exclus.

Exemple de réponse (les identifiants sont à réutiliser tels quels) :

```json
{
  "success": true,
  "backups": [
    {
      "id": "my_site_2026-11-09__10-00-00.zip",
      "name": "my_site_2026-11-09__10-00-00.zip",
      "size": 123456,
      "created_at": "2026-11-09T10:00:00+00:00",
      "modified_at": "2026-11-09T10:00:00+00:00"
    }
  ]
}
```

```bash
curl --fail --get \
  -H "Authorization: Bearer $BACKUP_API_KEY" \
  --data-urlencode 'operation=download_local_backup' \
  --data-urlencode 'id=my_site_2026-11-09__10-00-00.zip' \
  --output sauvegarde.zip \
  'https://exemple.fr/api.php'
```

Le fichier est transmis en flux. S’il a été supprimé entre la liste et le téléchargement
(par exemple par la rétention), l’API renvoie 404. Les sauvegardes doivent rester dans un dossier
hors de la racine web : l’authentification de l’API ne protège pas une URL statique exposée par le serveur.

### Scripts distants

Le dossier `remote_scripts` contient le client PHP à exécuter en CLI sur la machine qui récupère
les données (extension cURL requise, client `mysql` pour les imports SQL).
Copier `remote_scripts/config.sample.php` vers `remote_scripts/config.php`, renseigner les secrets,
puis activer les entrées souhaitées avec `'enabled' => true`.
Garder ce dossier hors de la racine web.

- `type: local_backups` : renseigner `api_url` (sans paramètres), `api_key` et `destination`.
  Sans `max_age_days`, toutes les archives disponibles sont téléchargées ; avec une valeur entière, ce filtre
  est transmis à l’API. Un fichier présent de même nom et de même taille est ignoré.
  Cette comparaison ne vérifie pas son intégrité cryptographique.
  La rétention locale est optionnelle et indépendante de celle du serveur (voir ci-dessous).
- `type: dump_sql` : conserver les URL avec `operation` et `item`, retirer `apipwd` des URL
  et renseigner `api_password` avec `UI_PASSWORD`. Les exports sont téléchargés en POST puis importés.
  La clé des archives ne peut pas remplacer ce mot de passe.
- `type: matomo_json` : le mode existant reste disponible avec une URL HTTPS.

```bash
php remote_scripts/script.php
```

Le script s’arrête à la première erreur avec le **code de sortie 1** et écrit le message sur `stderr`.
Cela inclut les erreurs de configuration, de téléchargement, d’écriture, de rétention et d’import MySQL.
Une exécution réussie (y compris sans tâche activée) retourne **0**.

Le client impose HTTPS avec validation du certificat et refuse les redirections.
Il vérifie le statut HTTP, le type de contenu des réponses de l’API et la taille des archives.
Les fichiers téléchargés sont privés (permissions 0600) et finalisés après succès ; les fichiers temporaires
sont supprimés en cas d’erreur normale. Les exports SQL et fichiers d’identifiants MySQL sont temporaires ;
les mots de passe MySQL ne sont plus passés en arguments de commande.
Un arrêt forcé du processus peut nécessiter un nettoyage manuel des fichiers temporaires.
Le délai maximal de transfert est d’une heure par requête ; aucun téléchargement partiel n’est repris.

### Rétention des copies dans le script distant

Options disponibles pour chaque entrée `type: local_backups` :

```php
'max_age_days' => 7,              // Télécharger seulement les archives de moins de 7 jours ; null = toutes.
'retention_days' => 30,           // Purger les copies de plus de 30 jours ; null = désactivé (défaut).
'min_versions' => 3,              // Toujours garder au moins 3 archives PAR SÉRIE ; défaut = 1.
'backup_timezone' => 'Europe/Paris', // Même fuseau PHP que le serveur créateur.
```

La suppression exige **les deux conditions** : la copie est plus ancienne que `retention_days`
ET elle ne fait pas partie des `min_versions` archives les plus récentes de sa série.
Toutes les copies plus récentes que la limite restent conservées : `min_versions` est un minimum, pas un maximum.
Une copie exactement à la limite est conservée. Si une série a moins de Y copies, toutes sont conservées.
Le minimum porte sur les copies déjà présentes ; il ne recrée pas celles qui manquent.

Une série est le couple **préfixe + suffixe**, autour de l’unique segment `Y-m-d__H-i-s` du nom,
sans tenir compte de l’extension `.tar` ou `.zip`. Par exemple `site_2026-11-09__10-00-00_daily.tar`
et `site_2026-11-08__10-00-00_daily.zip` appartiennent à la même série ; `_weekly` est une autre série.
Chaque fichier d’archive compte pour une version, même si deux fichiers portent le même horodatage.
L’âge et l’ordre des versions reposent exclusivement sur la date du nom, jamais sur la date de téléchargement
ou de modification du fichier. Les jours sont calculés en retranchant X jours à l’instant courant dans le fuseau utilisé.
Si `backup_timezone` est omis, le fuseau PHP de la machine distante est utilisé.

`max_age_days` et `retention_days` acceptent des entiers PHP de 1 à 365000, ou `null` ;
`min_versions` accepte un entier de 0 à 1000000 (0 = aucun minimum). Une configuration invalide
arrête le traitement avant les transferts et les suppressions.

La purge s’exécute seulement après un listing et une synchronisation entièrement réussis, même si aucune
nouvelle archive n’a été téléchargée. Une erreur réseau, de réponse, de métadonnées ou de taille empêche la purge.
Un verrou refuse deux synchronisations simultanées du même dossier.
Seuls les fichiers réguliers avec un nom d’archive reconnu, directement dans `destination`, sont concernés :
les liens symboliques, les sous-dossiers, les dates invalides et les noms ambigus sont ignorés.
Le dossier `destination` doit être réservé aux copies d’archives d’une seule source.
La rétention est désactivée pour les configurations existantes qui n’indiquent pas `retention_days`.

### Vérifications de développement

Les tests isolés utilisent des fichiers temporaires et des doublures, sans accès réseau ni base réelle :

```bash
php tests/api_security.php
php -n tests/remote_scripts_security.php
php tests/remote_scripts_exit_codes.php
```

Le second test simule cURL dans un namespace isolé ; `-n` limite les extensions chargées.

## Paramètres du fichier Config.php

Liste des paramètres:

| Option | Exemples | Description |
| ------ | ------ | ------ |
| DB_USE_MYSQLDUMP_CMD | false | Si **true** => utilise la command mysqldump. Si **false** => utilise la libraire [Ifsnop\Mysqldump\Mysqldump] |
| DB_MYSQLDUMP_VARIABLES | ```[ 'triggers' => true ]``` | Utiliser la commande:  _**mysqldump --help**_ pour voir toutes les variables en option |	
| DB_DUMP_LIB_SETTINGS | ```[ 'add-locks' => false ]``` | **Dump Settings** pour [Ifsnop\Mysqldump\Mysqldump] |
| URL_TOKEN | Token d'authentification | Utilisé lors des appel via URL. Ajouter dans l'URL `&token=<valeur de votre token>` |
| UI_PASSWORD | Mot de passe d’authentification | Interface et opérations API existantes, envoyé en POST |
| LOCAL_BACKUP_API_KEY | Clé aléatoire ou chaîne vide | Accès Bearer en lecture aux archives locales ; vide ou absente = désactivé |
| groups() | Voir Config.sample.php | Liste des éléments à sauvegarder. (DB, Fichiers, Email, Sync Google Docs, Google Search Console keywords) |
| downloadDBApi() | Permet un export de DB | Via API permet de récupérer le SQL de tables spécifiques. Voir section API. |
| COMPRESSION_TYPE | 'tar' \| 'phpzip' | Format de compression. Utiliser **tar** pour conserver les permissions de fichiers |
| localStorageBackupDir() | ```return dirname(dirname(__DIR__)) . '/backups';``` | Répertoire contenant toutes les sauvegardes. **Ne pas mettre de / à la fin** |
| LOCAL_BACKUP_RETENTION | 'P1M' \| null | Temps de rétention des sauvegardes locales. Au format accepté par [\DateInterval] |
| S3_ENABLED | false | Envoi des sauvegardes sur un stockage S3 (type [AWS S3] ou [Minio] ) |
| S3_REGION | 'eu-west-3' | Region S3 |
| S3_ENDPOINT | '' | Endpoint S3 |
| S3_ACCESS_KEY_ID | '' | Access Key S3 |
| S3_SECRET_ACCESS_KEY | '' | Secret Key S3 |
| S3_BUCKET | 'my-bucket' | Nom du Bucket S3 |
| S3_DIR | 'dir' | Dossier dans le Bucket S3 |
| S3_BACKUP_RETENTION | 'P1M' \| null |  Temps de rétention des sauvegardes sur S3. Au format accepté par [\DateInterval]  |
| NOTIF_DISCORD_USERNAME | 'Cron job' | Notification Discord Username (pour affichage dans le salon) |
| NOTIF_DISCORD_WEBHOOK_URL | null | Webhook du salon Discord |
| NOTIF_ERROR_DISCORD_WEBHOOK_URL | null | Webhook du salon Discord pour les erreurs |
| NOTIF_SLACK_WEBHOOK_URL | null | Webhook du salon Slack |
| NOTIF_ERROR_SLACK_WEBHOOK_URL | null | Webhook du salon Slack pour les erreurs |
| NOTIF_TELEGRAM_CHAT_ID | null | Webhook du bot Telegram - ChatId |
| NOTIF_TELEGRAM_TOKEN | null | Webhook du bot Telegram - Token |
| NOTIF_ERROR_TELEGRAM_CHAT_ID | null | Webhook du bot Telegram pour les erreurs - ChatId |
| NOTIF_ERROR_TELEGRAM_TOKEN | null | Webhook du bot Telegram pour les erreurs - Token |


## Restauration

La restauration n'est pas prise en charge.

Lors de la restauration de la base de données, si vous utilisez PhpMyAdmin n'oubliez pas de **décocher**  _"Activer la vérification des clés étrangères"_

Dans le cas d'une compression **tar** utilisez la commande suivante pour décrompresser l'archive tar en préservant les permissions des fichiers:

```
tar --preserve-permissions -xf "<file>"
```



[druidfi\mysqldump-php]: https://github.com/druidfi/mysqldump-php
[\DateInterval]: https://www.php.net/manual/fr/class.dateinterval.php
[AWS S3]: https://aws.amazon.com/fr/s3/
[Minio]: https://min.io/
