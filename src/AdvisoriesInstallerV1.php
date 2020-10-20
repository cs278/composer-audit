<?php

namespace Cs278\ComposerAudit;

use Composer\Downloader\DownloadManager;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryManager;

/**
 * @copyright 2020 Chris Smith
 * @license MIT
 */
final class AdvisoriesInstallerV1 extends AdvisoriesInstaller
{
    /** @var DownloadManager */
    private $downloadManager;

    public function __construct(RepositoryManager $repositoryManager, DownloadManager $downloadManager)
    {
        parent::__construct($repositoryManager);

        $this->downloadManager = $downloadManager;
    }

    protected function downloadAndInstall($targetDirectory, PackageInterface $package)
    {
        $this->downloadManager->download($package, $targetDirectory, false);
    }
}
