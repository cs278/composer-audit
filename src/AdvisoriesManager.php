<?php

namespace Cs278\ComposerAudit;

use Composer\Composer;
use Composer\Plugin\PluginInterface;

/**
 * Handles finding security advisories.
 *
 * Pulls a composer package of security advisory data which is not managed as
 * a normal dependency.
 *
 * @copyright 2020 Chris Smith
 * @license MIT
 */
final class AdvisoriesManager
{
    /** @var AdvisoriesInstaller */
    private $installer;

    private $packageName = 'sensiolabs/security-advisories';
    private $packageConstraint = 'dev-master';

    /** @var string */
    private $directory;

    public function __construct(AdvisoriesInstaller $installer)
    {
        $this->installer = $installer;
    }

    public static function create(Composer $composer)
    {
        if (version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0.0', '>=')) {
            $installer = new AdvisoriesInstallerV2(
                $composer->getLoop(),
                $composer->getRepositoryManager(),
                $composer->getDownloadManager()
            );
        } else {
            $installer = new AdvisoriesInstallerV1(
                $composer->getRepositoryManager(),
                $composer->getDownloadManager()
            );
        }

        return new self($installer);
    }

    public function mustUpdate()
    {
        $this->installer->mustUpdate();
    }

    public function findAll()
    {
        $advisoriesDir = $this->getDirectory();

        // Find all the advisories for installed packages.
        return glob("$advisoriesDir/*/*/*.yaml");
    }

    private function getDirectory()
    {
        if (!isset($this->directory)) {
            $this->directory = $this->installer->install(
                dirname(__DIR__).'/var',
                $this->packageName,
                $this->packageConstraint
            );
        }

        return $this->directory;
    }
}
