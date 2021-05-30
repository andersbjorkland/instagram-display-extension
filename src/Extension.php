<?php

declare(strict_types=1);

namespace AndersBjorkland\InstagramDisplayExtension;

use Bolt\Extension\BaseExtension;

class Extension extends BaseExtension
{
    /**
     * Return the full name of the extension
     */
    public function getName(): string
    {
        return 'AcmeCorp ReferenceExtension';
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
        $this->addWidget(new ReferenceWidget());

        $this->addTwigNamespace('instagram-display-extension');

        $this->addListener('kernel.response', [new EventListener(), 'handleEvent']);
    }

    /**
     * Ran automatically, if the current request is from the command line (CLI).
     * You can use this method to set up things in your extension.
     *
     * Note: This runs on every request. Make sure what happens here is quick
     * and efficient.
     */
    public function initializeCli(): void
    {
    }

    public function install(): void
    {
       passthru("php bin/console doctrine:query:sql \"CREATE TABLE bolt_instagram_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_in DATETIME DEFAULT NULL)\"", $result);
        //passthru('php bin/console doctrine --help');
    }
}
