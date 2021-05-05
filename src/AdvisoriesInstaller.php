<?php

namespace Cs278\ComposerAudit;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Util\Filesystem;

/**
 * Handles installing security advisories.
 *
 * This is logic is split from AdvisoriesManager to ease compatability between
 * Composer 1 and 2.
 *
 * @copyright 2020 Chris Smith
 * @license MIT
 */
abstract class AdvisoriesInstaller implements AdvisoriesInstallerInterface
{
    /** @var RepositoryManager */
    private $repositoryManager;

    /** @var bool Update security advisories even if already present. */
    protected $mustUpdate = false;

    public function __construct(RepositoryManager $repositoryManager)
    {
        $this->repositoryManager = $repositoryManager;
    }

    public function mustUpdate()
    {
        $this->mustUpdate = true;
    }

    public function install($varDirectory, $packageName, $packageConstraint)
    {
        if (is_file("{$varDirectory}/data.lock") && is_dir("{$varDirectory}/data")) {
            $installedVersion = trim(file_get_contents("{$varDirectory}/data.lock"));
            $lastUpdated = filemtime("{$varDirectory}/data.lock");
        } else {
            $installedVersion = null;
            $lastUpdated = 0;
        }

        // No version installed or an update is requested, fetch package data.
        if ((time() - $lastUpdated) > 3600 || $this->mustUpdate) {
            $package = $this->repositoryManager->findPackage($packageName, $packageConstraint);
            $version = $package->getName().'@'.$package->getFullPrettyVersion(false);
            $updated = true;
        } else {
            $version = $installedVersion;
            $updated = false;
        }

        if ($version !== $installedVersion) {
            $fs = new Filesystem();
            $fs->remove("{$varDirectory}/data.lock");
            $fs->remove("{$varDirectory}/data");

            $this->downloadAndInstall("{$varDirectory}/data", $package);

            file_put_contents("{$varDirectory}/data.lock", $version."\n");
        } elseif ($updated) {
            touch("{$varDirectory}/data.lock");
        }

        return "{$varDirectory}/data";
    }

    abstract protected function downloadAndInstall($targetDirectory, PackageInterface $package);
}
