# Acme ReferenceExtension

Author: YourNameHere

This Bolt extension can be used as a starting point to base your own extensions on.

Installation:

```bash
composer require andersbjorkland/instagram-display-extension
```

Add a database table for storing the long-lasting Instagram token:
```bash
php bin/console doctrine:query:sql 'CREATE TABLE bolt_instagram_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_in DATETIME DEFAULT NULL)'
```

Removing:
If you don't want to be using the Instagram Display Extension, you may want to remove the corresponding database table:
```bash
php bin/console doctrine:query:sql 'DROP TABLE bolt_instagram_token'
```


## Running PHPStan and Easy Codings Standard

First, make sure dependencies are installed:

```
COMPOSER_MEMORY_LIMIT=-1 composer update
```

And then run ECS:

```
vendor/bin/ecs check src
```
