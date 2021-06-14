# Instagram Display Extension

Author: Anders Björkland

This Bolt extension can be used to display your Instagram posts on your website. 
Add ``{% include "@instagram-display-extension/partials/_media.html.twig" %}`` to a template where you want to display it.

Installation:

```bash
composer require andersbjorkland/instagram-display-extension
```

When installing this extension, there will be two tables added to your database:  
* bolt_instagram_token - used for storing your instagram token.
* bolt_instagram_media - used for storing meta-information about Instagram images and video.

When using this extension, you will be fetching media via [Instagram Basic Display](https://developers.facebook.com/docs/instagram-basic-display-api). 
A Facebook developer account is required. You can then create an app with the Instagram Basic Display product. Once this is done, 
you will find **Instagram App ID** and **Instagram App Secret**. Add these as environment variables on the form as:  

```bash
INSTAGRAM_APP_ID=your_add_id
INSTAGRAM_APP_SECRET=your_app_secret
```

This extension will look for these environment variables and use them when you authenticate your website with your Instagram account, 
and on the api-calls to fetch media from your Instagram account.  
  
If you need to add and table manually you can execute any of these commands:  
bolt_instagram_token:
```bash
php bin/console doctrine:query:sql "CREATE TABLE IF NOT EXISTS bolt_instagram_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_in DATETIME DEFAULT NULL, instagram_user_id VARCHAR(255) DEFAULT NULL)"
```
  
bolt_instagram_media:
```bash
php bin/console doctrine:query:sql "CREATE TABLE IF NOT EXISTS bolt_instagram_media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, instagram_id VARCHAR(255) NOT NULL, media_type VARCHAR(255) NOT NULL, caption CLOB DEFAULT NULL, timestamp VARCHAR(255) NOT NULL, instagram_url VARCHAR(255) NOT NULL, filepath VARCHAR(255) DEFAULT NULL)"
```

*Removing*  
If you don't want to be using the Instagram Display Extension, you may want to remove the corresponding database tables.  
bolt_instagram_token:  
```bash
php bin/console doctrine:query:sql 'DROP TABLE bolt_instagram_token'
```

bolt_instagram_media:
```bash
php bin/console doctrine:query:sql 'DROP TABLE bolt_instagram_media'
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
