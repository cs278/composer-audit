<?php

namespace Cs278\ComposerAudit;

/**
 * Handles installing security advisories.
 *
 * @copyright 2020 Chris Smith
 * @license MIT
 */
interface AdvisoriesInstallerInterface
{
    /**
     * Require the installer to check if updates are available.
     *
     * @return void
     */
    public function mustUpdate();

    /**
     * Install advisories database package in to the given directory.
     *
     * @param string $varDirectory Directory to store the database
     * @param string $packageName Package name of the advisories database
     * @param string $packageConstraint Required constraint for the advisories
     *                                  database package.
     *
     * @return string The base directory of the advisories database, this will
     *                usually be $varDirectory or a sub-directory of it but
     *                consumers shouldn't rely on this.
     */
    public function install($varDirectory, $packageName, $packageConstraint);
}
