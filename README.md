# website-backup

## Présentation
Ce projet permet d'effectuer des sauvegardes de sites PHP/MySql/MariaDB. Il est utilisable sur des hébergeurs comme OVH.

*This project allows to make backups of PHP/MySql/MariaDB sites. It is usable on hosts like OVH.*

## Fonctionnalités

- Dump de la base de données en deux options:
	- Avec la libraire **[Ifsnop\Mysqldump\Mysqldump]** et toutes ses options 
	sont configurable
	- Avec la commande **mysqldump** et toutes ses options sont configurables
- Compressions du dump avec les fichiers du site:
	- ZIP : Extension PHP Zip
	- TAR : Commande **tar** avec conservation des permissions des fichiers
- Prise en charge de plusieurs répertoires de site
- Suppression des vielles sauvegardes

## Installation

Récupérez le contenu du dossier "src" et copiez-le sur votre hébergement dans un dossier non accessible depuis le web. (inutile de conserver le nom de dossier "src")

Copiez le fichier **Config/Config.sample.php** vers **Config/Config.php**
Ouvrez le fichier **Config/Config.php** et:
- Renommez le nom de la classe: _Config____Sample_ en _Config_
- Modifiez les paramètres en fonction de vos souhaits

Pour une execution automatique, paramétrez un cron job (consultez la documentation de l'hébergeur) pour executer le script PHP: _backup.php_

## Paramètres du fichier Config.php

Liste des paramètres:

| Option | Exemples | Description |
| ------ | ------ | ------ |
| DB_ENABLED | true | Dump de base de données activée |
| DB_HOST | 'localhost' | Hôte du serveur de DB |
| DB_PORT | 3306 | Port du serveur de DB |
| DB_USER | 'mydb' | User du serveur de DB |
| DB_PWD | 'yuj4f6ghj514d6fj516gh51sdgh' | Mot de passe du serveur de DB |
| DB_DATABASE | 'mydb' | Nom de la base de données |
| DB_USE_MYSQLDUMP_CMD | false | Si **true** => utilise la command mysqldump. Si **false** => utilise la libraire [Ifsnop\Mysqldump\Mysqldump] |
| DB_MYSQLDUMP_VARIABLES | ```[ 'triggers' => true ]``` | Utiliser la commande:  _**mysqldump --help**_ pour voir toutes les variables en option |	
| DB_DUMP_LIB_SETTINGS | ```[ 'add-locks' => false ]``` | **Dump Settings** pour [Ifsnop\Mysqldump\Mysqldump] |
| FILES_ENABLED | true | Sauvegarde des fichiers du site activée |
| filesDirs() | ```return [ dirname(dirname(__DIR__)) . '/ /site_test' ];``` | Liste des répertoires à sauvegarder. **Ne pas mettre de / à la fin** |
| COMPRESSION_TYPE | 'tar' \| 'phpzip' | Format de compression. Utiliser **tar** pour conserver les permissions de fichiers |
| localStorageBackupDir() | ```return dirname(dirname(__DIR__)) . '/backups';``` | Répertoire contenant toutes les sauvegardes. **Ne pas mettre de / à la fin** |
| LOCAL_BACKUP_RETENTION | 'P1M' | Temps de rétention des sauvegardes locales. Au format accepté par [\DateInterval] |
| S3_ENABLED | false | Envoi des sauvegardes sur un stockage S3 (type [AWS S3] ou [Minio] ) |
| S3_REGION | 'eu-west-3' | Region S3 |
| S3_ENDPOINT | '' | Endpoint S3 |
| S3_ACCESS_KEY_ID | '' | Access Key S3 |
| S3_SECRET_ACCESS_KEY | '' | Secret Key S3 |
| S3_BUCKET | 'my-bucket' | Nom du Bucket S3 |
| S3_DIR | 'dir' | Dossier dans le Bucket S3 |
| S3_BACKUP_RETENTION | 'P1M' |  Temps de rétention des sauvegardes sur S3. Au format accepté par [\DateInterval]  |
| NOTIF_DISCORD_USERNAME | 'Cron job' | Notification Discord Username (pour affichage dans le salon) |
| NOTIF_DISCORD_WEBHOOK_URL | null | Webhook du salon Discord |
| NOTIF_ERROR_DISCORD_WEBHOOK_URL | null | Webhook du salon Discord pour les erreurs |

## Restauration

La restauration n'est pas prise en charge.
Dans le cas d'une compression **tar** utilisez la commande suivante pour décrompresser l'archive tar en préservant les permissions des fichiers:

```
tar --preserve-permissions -xf "<file>"
```


[Ifsnop\Mysqldump\Mysqldump]: https://github.com/ifsnop/mysqldump-php
[\DateInterval]: https://www.php.net/manual/fr/class.dateinterval.php
[AWS S3]: https://aws.amazon.com/fr/s3/
[Minio]: https://min.io/