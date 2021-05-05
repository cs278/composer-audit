<?php

namespace Cs278\ComposerAudit\Legacy;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

if (!class_exists(__NAMESPACE__.'\\ComposerPlugin', false)) {
    if (\PHP_VERSION_ID >= 70100) {
        \class_alias(substr(__NAMESPACE__, 0, strrpos(__NAMESPACE__, '\\')).'\\ComposerPlugin', __NAMESPACE__.'\\ComposerPlugin');
    } else {
        /**
         * Composer Audit Plugin declaration.
         *
         * @internal This class is used when loading the plugin with PHP < 7.1.
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
                return array(
                    'Composer\\Plugin\\Capability\\CommandProvider' => __NAMESPACE__.'\\CommandProvider',
                );
            }
        }
    }
}
