<?php

namespace Cs278\ComposerAudit;

use Composer\Composer;
use Composer\IO\IOInterface;

/**
 * Find Symfony Polyfill libraries and loads them.
 */
final class PolyfillLoader
{
    private static $loaded = false;

    public static function load(Composer $composer, IOInterface $io)
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        $packages = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
        $includeFiles = [];

        foreach ($packages as $package) {
            if (strpos($package->getName(), 'symfony/polyfill-') === 0) {
                $io->debug(sprintf('PolyfillLoader finding files in: %s', $package->getName()));
                $autoload = $package->getAutoload();

                if (isset($autoload['files'])) {
                    $installPath = $composer->getInstallationManager()->getInstallPath($package);

                    foreach ($autoload['files'] as $file) {
                        $path = $installPath.\DIRECTORY_SEPARATOR.$file;

                        if (\file_exists($path)) {
                            $io->debug(sprintf('PolyfillLoader found: %s %s', $package->getName(), $file));
                            $includeFiles[] = $path;
                        } else {
                            $io->warning(sprintf('PolyfillLoader file not found: %s %s', $package->getName(), $path));
                        }
                    }
                }
            }
        }

        foreach ($includeFiles as $includeFile) {
            if (in_array($includeFile, \get_included_files(), true)) {
                $io->debug(sprintf('PolyfillLoader %s is already loaded', $includeFile));
                return;
            }

            $io->debug(sprintf('PolyfillLoader loading: %s', $includeFile));
            self::incldueFile($includeFile);
        }
    }

    private static function incldueFile($path): void
    {
        require $path;
    }
}
