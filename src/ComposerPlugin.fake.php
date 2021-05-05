<?php

namespace Cs278\ComposerAudit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

/**
 * Composer Audit Plugin declaration.
 *
 * @internal This class is used when loading the plugin with PHP < 7.1.
 */
final class ComposerPlugin implements PluginInterface, Capable, CommandProviderCapability
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
        return array(
            'Composer\\Plugin\\Capability\\CommandProvider' => \get_class($this),
        );
    }

    public function getCommands()
    {
        return array(
            new AuditNotCompatibleCommand(),
        );
    }
}
