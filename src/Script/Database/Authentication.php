<?php

declare(strict_types=1);

namespace Script\Database;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Exception;
use PDO;

class Authentication
{
    /** @var array */
    private $config;

    /** @var Composer */
    private $composer;

    /** @var string Path to this file. */
    private $installerSource;

    /** @var IOInterface */
    private $io;

    /** @var string */
    private $projectRoot;

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;

        // Get composer.json location
        $composerFile = Factory::getComposerFile();

        // Calculate project root from composer.json, if necessary
        $this->projectRoot = realpath(dirname($composerFile)) ?? '';
        $this->projectRoot = rtrim($this->projectRoot, '/\\').'/';

        // Parse the composer.json
        // $this->parseComposerDefinition($composer, $composerFile);

        $this->config = require $this->projectRoot.'config/autoload/local.php';

        // Source path for this file
        $this->installerSource = realpath(__DIR__).'/';
    }

    public static function init(Event $event): void
    {
        $installer = new self($event->getIO(), $event->getComposer());

        $sql = file_get_contents('resources/sql/authentication.sql');

        $pdo = $installer->config['authentication']['pdo'];

        $db = new PDO($pdo['dsn'], $pdo['username'], $pdo['password']);
        $q = $db->exec($sql);

        if ($q === false) {
            $error = $db->errorInfo();

            throw new Exception($error[2]);
        }

        $installer->io->write("\n<info>Authentication tables created!</info>");
    }
}
