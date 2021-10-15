<?php

declare(strict_types=1);

namespace AndersBjorkland\InstagramDisplayExtension;

use AndersBjorkland\InstagramDisplayExtension\Exceptions\UnsupportedDatabaseException;
use Bolt\Extension\BaseExtension;
use Symfony\Component\Filesystem\Filesystem;

class Extension extends BaseExtension
{
    public const TOKEN_SQL_STATEMENTS = [
        "sqlite" => ["CREATE TABLE IF NOT EXISTS bolt_instagram_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_in DATETIME DEFAULT NULL, instagram_user_id VARCHAR(255) DEFAULT NULL)"],
        "mysql" => ["CREATE TABLE IF NOT EXISTS bolt_instagram_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_in DATETIME DEFAULT NULL, instagram_user_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))"],
        "postgresql" => ["CREATE TABLE IF NOT EXISTS bolt_instagram_token (id INT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_in TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, instagram_user_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))", "CREATE SEQUENCE IF NOT EXISTS instagram_token_id_seq INCREMENT BY 1 MINVALUE 1 START 1"]
    ];
    public const MEDIA_SQL_STATEMENTS = [
        "sqlite" => ["CREATE TABLE IF NOT EXISTS bolt_instagram_media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, instagram_id VARCHAR(255) NOT NULL, media_type VARCHAR(255) NOT NULL, caption CLOB DEFAULT NULL, timestamp VARCHAR(255) NOT NULL, instagram_url CLOB NOT NULL, filepath VARCHAR(255) DEFAULT NULL, permalink VARCHAR(255) DEFAULT NULL, instagram_username VARCHAR(255) DEFAULT NULL)"],
        "mysql" => ["CREATE TABLE IF NOT EXISTS bolt_instagram_media (id INT AUTO_INCREMENT NOT NULL, instagram_id VARCHAR(255) NOT NULL, media_type VARCHAR(255) NOT NULL, caption LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL, timestamp VARCHAR(255) NOT NULL, filepath VARCHAR(255) DEFAULT NULL, instagram_url TEXT DEFAULT NULL, permalink VARCHAR(255) DEFAULT NULL, instagram_username VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))"],
        "postgresql" => ["CREATE TABLE IF NOT EXISTS bolt_instagram_media (id INT NOT NULL, instagram_id VARCHAR(255) NOT NULL, media_type VARCHAR(255) NOT NULL, caption TEXT DEFAULT NULL, timestamp VARCHAR(255) NOT NULL, filepath VARCHAR(255) DEFAULT NULL, instagram_url TEXT DEFAULT NULL, permalink VARCHAR(255) DEFAULT NULL, instagram_username VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))", "CREATE SEQUENCE IF NOT EXISTS instagram_media_id_seq INCREMENT BY 1 MINVALUE 1 START 1"]
    ];

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


        $tokenSql = $this->decideTokenSql();
        $mediaSql = $this->decideMediaSql();

        $symfonyCommand = $this->verifyCommand('symfony') ? "symfony" : "";

        $output = [];
        
        for ($i = 0; $i < count($tokenSql); $i++) {
            $query = $tokenSql[$i];
            exec("$symfonyCommand php bin/console doctrine:query:sql \"$query\"", $output, $result);
        }

        for ($i = 0; $i < count($mediaSql); $i++) {
            $query = $mediaSql[$i];
            exec("$symfonyCommand php bin/console doctrine:query:sql \"$query\"", $output, $result);
        }

    }

    /**
     * @return array
     * @throws UnsupportedDatabaseException
     */
    protected function decideTokenSql(): array
    {
        $databasePlatformName = $this->getObjectManager()->getConnection()->getDatabasePlatform()->getName();

        $tokenSql = "";

        $supportedDatabasePlatforms = array_keys($this::TOKEN_SQL_STATEMENTS);

        if (in_array(strtolower($databasePlatformName), $supportedDatabasePlatforms)) {
            $tokenSql = $this::TOKEN_SQL_STATEMENTS[strtolower($databasePlatformName)];
        } else {
            throw new UnsupportedDatabaseException($databasePlatformName . " is not supported. Supported database platforms are: " . implode(", ", $supportedDatabasePlatforms));
        }

        return $tokenSql;
    }

    /**
     * @return array
     * @throws UnsupportedDatabaseException
     */
    protected function decideMediaSql(): array
    {
        $databasePlatformName = $this->getObjectManager()->getConnection()->getDatabasePlatform()->getName();

        $mediaSql = "";

        $supportedDatabasePlatforms = array_keys($this::MEDIA_SQL_STATEMENTS);

        if (in_array(strtolower($databasePlatformName), $supportedDatabasePlatforms)) {
            $mediaSql = $this::MEDIA_SQL_STATEMENTS[strtolower($databasePlatformName)];
        } else {
            throw new UnsupportedDatabaseException($databasePlatformName . " is not supported. Supported database platforms are: " . implode(", ", $supportedDatabasePlatforms));
        }

        return $mediaSql;
    }

    protected function verifyCommand(string $command): bool 
    {
        $windows = strpos(PHP_OS, 'WIN') === 0;
        $test = $windows ? 'where' : 'command -v';
        $result = shell_exec("$test $command");
        $isExecutable = false;

        if ($result) {
            $isExecutable = is_executable(trim($result));
        }

        return $isExecutable;
    }
}
