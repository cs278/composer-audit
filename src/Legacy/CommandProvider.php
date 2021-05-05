<?php

namespace Cs278\ComposerAudit\Legacy;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

/**
 * @internal This class is used when loading the plugin with PHP < 7.1.
 */
final class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return array(
            new AuditNotCompatibleCommand(),
        );
    }
}
