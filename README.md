# website-backup

## Présentation
Ce projet permet d'effectuer des sauvegardes de sites PHP/MySql/MariaDB. Il est utilisable sur des hébergeurs comme OVH.

*This project allows to make backups of PHP/MySql/MariaDB sites. It is usable on hosts like OVH.*

## Fonctionnalités

- Dump de la base de données en deux options:
	- Avec la libraire **[Ifsnop\Mysqldump\Mysqldump]** (toutes ses options 
	sont configurables)
	- Avec la commande **mysqldump** (toutes ses options sont configurables)
- Compression du dump avec les fichiers du site:
	- ZIP : Extension PHP Zip
	- TAR : Commande **tar** avec conservation des permissions des fichiers
- Prise en charge de plusieurs répertoires de site
- Suppression des vielles sauvegardes

## Installation

Récupérez le contenu du dossier **"src"** et copiez-le sur votre hébergement dans un dossier non accessible depuis le web. (inutile de conserver le nom de dossier "src")

Copiez le fichier **Config/Config.sample.php** vers **Config/Config.php**
Ouvrez le fichier **Config/Config.php** et:
- Renommez le nom de la classe: _Config____Sample_ en _Config_
- Modifiez les paramètres en fonction de vos souhaits

Pour une execution automatique, paramétrez un cron job (consultez la documentation de l'hébergeur) sur le script PHP: _backup.php_

## Lancement

Soit via CLI
```
php .\backup.php

# Options:
php .\backup.php -g <Groupes de sauvegarde à lancer> --verbose
```

Ou via URL: `https://<YOUR_URL>/backup.php?token=<token>`

Avec les options: `https://<YOUR_URL>/backup.php?token=<token>&g=<Groupes de sauvegarde à lancer>&verbose=1`

Vous pouvez aussi forcer le remplacement des `<br />` par `\n` avec l'option: `&br=0`

Le paramètre `<token>` doit être spécifié dans le fichier de Configuration

Le paramètre `<Groupes de sauvegarde à lancer>` contient la liste des groupes à lancer séparés par une virgule `,`

Exemples:
```
php .\backup.php -g group1,group2 --verbose

URL: https://<YOUR_URL>/backup.php?token=xxxxx&g=group1,group2&verbose=1
```

## Paramètres du fichier Config.php

Liste des paramètres:

| Option | Exemples | Description |
| ------ | ------ | ------ |
| DB_USE_MYSQLDUMP_CMD | false | Si **true** => utilise la command mysqldump. Si **false** => utilise la libraire [Ifsnop\Mysqldump\Mysqldump] |
| DB_MYSQLDUMP_VARIABLES | ```[ 'triggers' => true ]``` | Utiliser la commande:  _**mysqldump --help**_ pour voir toutes les variables en option |	
| DB_DUMP_LIB_SETTINGS | ```[ 'add-locks' => false ]``` | **Dump Settings** pour [Ifsnop\Mysqldump\Mysqldump] |
| URL_TOKEN | Token d'authentification | Utilisé lors des appel via URL. Ajouter dans l'URL `&token=<valeur de votre token>` |
| groups() | Voir Config.sample.php | Liste des éléments à sauvegarder. (DB, Fichiers et Email) |
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

## Restauration

La restauration n'est pas prise en charge.

Lors de la restauration de la base de données, si vous utilisez PhpMyAdmin n'oubliez pas de **décocher**  _"Activer la vérification des clés étrangères"_

Dans le cas d'une compression **tar** utilisez la commande suivante pour décrompresser l'archive tar en préservant les permissions des fichiers:

```
tar --preserve-permissions -xf "<file>"
```



[Ifsnop\Mysqldump\Mysqldump]: https://github.com/ifsnop/mysqldump-php
[\DateInterval]: https://www.php.net/manual/fr/class.dateinterval.php
[AWS S3]: https://aws.amazon.com/fr/s3/
[Minio]: https://min.io/