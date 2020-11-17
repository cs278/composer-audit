<?php

namespace Cs278\ComposerAudit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Composer Audit Plugin declaration.
 *
 * @internal This class is used when loading the plugin with PHP < 7.1.
 */
final class ComposerPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {

    }

    public function deactivate(Composer $composer, IOInterface $io)
    {

    }

    public function uninstall(Composer $composer, IOInterface $io)
    {

    }
}
