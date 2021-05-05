<?php

namespace Cs278\ComposerAudit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

/**
 * Composer Audit Plugin declaration.
 */
final class ComposerPlugin implements PluginInterface, Capable
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

    public function getCapabilities()
    {
        return [
            CommandProviderCapability::class => CommandProvider::class,
        ];
    }
}
