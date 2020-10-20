<?php

namespace Cs278\ComposerAudit;

use Composer\Downloader\DownloadManager;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Util\Loop;
use Composer\Util\SyncHelper;

/**
 * Handle installing advisories data with Composer 2.
 *
 * @copyright 2020 Chris Smith
 * @license MIT
 */
final class AdvisoriesInstallerV2 extends AdvisoriesInstaller
{
    /** @var Loop */
    private $loop;

    /** @var DownloadManager */
    private $downloadManager;

    public function __construct(Loop $loop, RepositoryManager $repositoryManager, DownloadManager $downloadManager)
    {
        parent::__construct($repositoryManager);

        $this->loop = $loop;
        $this->downloadManager = $downloadManager;
    }

    protected function downloadAndInstall($targetDirectory, PackageInterface $package)
    {
        $package->setInstallationSource('dist'); // Fingers crossed

        SyncHelper::downloadAndInstallPackageSync(
            $this->loop,
            $this->downloadManager->getDownloaderForPackage($package),
            $targetDirectory,
            $package
        );
    }
}
