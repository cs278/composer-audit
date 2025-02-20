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
        $package = "{$packageName}:{$packageConstraint}";

        if (is_file("{$varDirectory}/data.lock") && is_dir("{$varDirectory}/data")) {
            [
                $mustUpdateLockFile,
                $installedPackage,
                $installedVersion,
                $lastUpdated,
            ] = $this->parseLockFile($varDirectory);
        } else {
            $mustUpdateLockFile = true;
            $installedPackage = null;
            $installedVersion = null;
            $lastUpdated = 0;
        }

        $mustUpdate = $mustUpdateLockFile
            || $this->mustUpdate
            || $installedPackage === null
            || $installedVersion === null
            || $installedPackage !== $package
            || (time() - $lastUpdated) > 3600;

        $mustUpdateLockFile = $mustUpdateLockFile
            || $installedPackage !== $package;

        // No version installed or an update is requested, fetch package data.
        if ($mustUpdate) {
            $packageObj = $this->repositoryManager->findPackage($packageName, $packageConstraint);
            $updated = true;

            // Hack to support #gifref constraints.
            if (preg_match('{#([a-f0-9]{40})$}', $packageConstraint, $m)) {
                $packageObj->setDistReference($m[1]);
                $packageObj->setSourceReference($m[1]);
            }

            $version = $packageObj->getName().'@'.$packageObj->getFullPrettyVersion(false, PackageInterface::DISPLAY_SOURCE_REF);
        } else {
            $package = $installedPackage;
            $version = $installedVersion;
            $updated = false;
        }

        if ($version !== $installedVersion) {
            $fs = new Filesystem();
            $fs->remove("{$varDirectory}/data.lock");
            $fs->remove("{$varDirectory}/data");

            $this->downloadAndInstall("{$varDirectory}/data", $packageObj);
            $this->writeLockFile($varDirectory, $package, $version);
        } elseif ($mustUpdateLockFile) {
            $this->writeLockFile($varDirectory, $package, $version);
        } elseif ($updated) {
            touch("{$varDirectory}/data.lock");
        }

        return "{$varDirectory}/data";
    }

    abstract protected function downloadAndInstall($targetDirectory, PackageInterface $package);

    private function writeLockFile(string $varDirectory, string $package, string $version): void
    {
        \file_put_contents("{$varDirectory}/data.lock", \json_encode([
            'v' => 2, // Lock file version.
            'package' => $package,
            'version' => $version,
        ]));
    }

    /** @return array{bool,?string,?string,int} */
    private function parseLockFile(string $varDirectory): array
    {
        $contents = trim(file_get_contents("{$varDirectory}/data.lock"));
        $lastUpdated = filemtime("{$varDirectory}/data.lock");

        if (substr($contents, 0, 1) === '{') {
            $contents = \json_decode($contents, true, 16);

            return [
                false,
                $contents['package'],
                $contents['version'],
                $lastUpdated,
            ];
        }

        return [true, null, $contents, $lastUpdated];
    }
}
