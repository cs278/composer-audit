<?php

namespace Cs278\ComposerAudit;

use Composer\Downloader\DownloadManager;
use Composer\Repository\RepositoryManager;
use Composer\Util\Filesystem;

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
    /** @var RepositoryManager */
    private $repositoryManager;

    /** @var DownloadManager */
    private $downloadManager;

    /** @var bool Update security advisories even if already present. */
    private $mustUpdate = false;

    private $packageName = 'sensiolabs/security-advisories';
    private $packageConstraint = 'dev-master';

    /** @var string */
    private $directory;

    public function __construct(RepositoryManager $repositoryManager, DownloadManager $downloadManager)
    {
        $this->repositoryManager = $repositoryManager;
        $this->downloadManager = $downloadManager;
    }

    public function mustUpdate()
    {
        $this->mustUpdate = true;
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
            $directory = dirname(__DIR__).'/var';

            if (is_file("{$directory}/data.lock") && is_dir("{$directory}/data")) {
                $installedVersion = trim(file_get_contents("{$directory}/data.lock"));
            } else {
                $installedVersion = null;
            }

            // No version installed or an update is requested, fetch package data.
            if ($installedVersion === null || $this->mustUpdate) {
                $package = $this->repositoryManager->findPackage($this->packageName, $this->packageConstraint);
                $version = $package->getFullPrettyVersion(false);
            } else {
                $version = $installedVersion;
            }

            if ($version !== $installedVersion) {
                $fs = new Filesystem();
                $fs->remove("{$directory}/data.lock");
                $fs->remove("{$directory}/data");

                $this->downloadManager->download($package, "{$directory}/data", false);

                file_put_contents("{$directory}/data.lock", $version."\n");
            }

            $this->directory = "{$directory}/data";
        }

        return $this->directory;
    }
}
