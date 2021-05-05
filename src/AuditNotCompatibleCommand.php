<?php

namespace Cs278\ComposerAudit;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dummy command which tells the user they are using an unsupported PHP.
 *
 * @copyright 2021 Chris Smith
 * @license MIT
 */
final class AuditNotCompatibleCommand extends BaseCommand
{
    protected function configure()
    {
        // Configuration is copied from AuditCommand so that the command accepts the same inputs.
        $this->setName('audit');
        $this->setDescription('Check packages for security advisories.');
        $this->addOption(
            'no-dev',
            null,
            InputOption::VALUE_NONE,
            'Disable checking of development dependencies.'
        );
        $this->addOption(
            'update',
            null,
            InputOption::VALUE_NONE,
            'Update security advisory information if a new version is available.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $output->writeln(sprintf('<error>Composer Audit is not compatible with PHP %s', PHP_VERSION));

        return 2;
    }
}
