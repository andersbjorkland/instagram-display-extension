<?php

declare(strict_types=1);

namespace AndersBjorkland\InstagramDisplayExtension;

use Bolt\Extension\BaseExtension;
use Symfony\Component\Filesystem\Filesystem;

class Extension extends BaseExtension
{
    /**
     * Return the full name of the extension
     */
    public function getName(): string
    {
        return 'Instagram Display Extension';
    }

    /**
     * Ran automatically, if the current request is in a browser.
     * You can use this method to set up things in your extension.
     *
     * Note: This runs on every request. Make sure what happens here is quick
     * and efficient.
     */
    public function initialize($cli = false): void
    {
        $this->addWidget(new ReferenceWidget($this->getObjectManager()));

        $this->addTwigNamespace('instagram-display-extension');

        $this->addListener('kernel.response', [new EventListener(), 'handleEvent']);

    }

    public function install(): void
    {
        $this->getConfig();

        /**
         * @var Container
         */
        $container =  $this->getContainer();
        $projectDir = $container->getParameter('kernel.project_dir');
        $public = $container->getParameter('bolt.public_folder');

        $source = dirname(__DIR__) . '/assets/';
        $destination = $projectDir . '/' . $public . '/assets/';

        $filesystem = new Filesystem();
        $filesystem->mirror($source, $destination);

        passthru("php bin/console doctrine:query:sql \"CREATE TABLE IF NOT EXISTS bolt_instagram_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_in DATETIME DEFAULT NULL, instagram_user_id VARCHAR(255) DEFAULT NULL)\"", $result);
        passthru("php bin/console doctrine:query:sql \"CREATE TABLE IF NOT EXISTS bolt_instagram_media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, instagram_id VARCHAR(255) NOT NULL, media_type VARCHAR(255) NOT NULL, caption CLOB DEFAULT NULL, timestamp VARCHAR(255) NOT NULL, instagram_url VARCHAR(255) NOT NULL, filepath VARCHAR(255) DEFAULT NULL, permalink VARCHAR(255) DEFAULT NULL, instagram_username VARCHAR(255) DEFAULT NULL)\"");
    }
}
