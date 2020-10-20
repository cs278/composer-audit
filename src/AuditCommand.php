<?php

namespace Cs278\ComposerAudit;

use Composer\Command\BaseCommand;
use Composer\Semver\Semver;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Command to audit composer dependencies using lock file information.
 *
 * @copyright 2019 Chris Smith
 * @license MIT
 */
final class AuditCommand extends BaseCommand
{
    /** @var bool */
    private $dev;

    /** @var bool */
    private $updateAdvisories;

    protected function configure()
    {
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

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->dev = !$input->getOption('no-dev');
        $this->updateAdvisories = (bool) $input->getOption('update');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $advisoriesManager = AdvisoriesManager::create($this->getComposer());

        if ($this->updateAdvisories) {
            $advisoriesManager->mustUpdate();
        }

        // @todo Use installed.json when lock file is disabled?
        $lockData = $this->getComposer()->getLocker()->getLockData();

        if ($this->dev) {
            $packages = array_merge(
                $lockData['packages'],
                $lockData['packages-dev']
            );
        } else {
            $packages = $lockData['packages'];
        }

        $packages = array_map(function (array $package) {
            return [
                'name' => $package['name'],
                'version' => $package['version'],
                'reference' => sprintf('composer://%s', $package['name']),
            ];
        }, $packages);

        $packages = array_column($packages, 'version', 'reference');

        $advisories = [];

        // Find all the advisories for installed packages.
        foreach ($advisoriesManager->findAll() as $file) {
            $advisory = Yaml::parseFile($file);
            $advisory['_file'] = $file;

            if (isset($packages[$advisory['reference']])) {
                $installedVersion = $packages[$advisory['reference']];

                foreach ($advisory['branches'] as $branch) {
                    $constraint = implode(',', $branch['versions']);

                    if (Semver::satisfies($installedVersion, $constraint)) {
                        $advisories[$advisory['reference']][] = $advisory;
                        break;
                    }
                }
            }
        }

        if ($advisories !== []) {
            // Advise the user of the advisories.
            $totalAdvisories = array_sum(array_map(function (array $packageAdvisories) {
                return \count($packageAdvisories);
            }, $advisories));
            $packagesAffected = \count($advisories);

            // @todo Pluralization?
            $output->writeln(sprintf(
                '<error>Found %u advisories affecting %u package(s).</error>',
                $packagesAffected,
                $totalAdvisories
            ));

            $output->writeln('');

            ksort($advisories, \SORT_NATURAL | \SORT_ASC);

            foreach ($advisories as $reference => $packageAdvisories) {
                $output->writeln(sprintf('<info>%s (%s)</info>', $reference, $packages[$reference]));

                foreach ($packageAdvisories as $advisory) {
                    $title = $advisory['title'];

                    $output->write(' * ');

                    if (isset($advisory['cve']) && strlen($advisory['cve']) > 0) {
                        $cveLink = sprintf(
                            'https://cve.mitre.org/cgi-bin/cvename.cgi?name=%s',
                            rawurlencode($advisory['cve'])
                        );

                        $output->write(self::formatHyperlink($output, $cveLink, $advisory['cve']).': ');

                        // Strip any reference of the CVE from the start of the advisory title.
                        $title = preg_replace(
                            sprintf('{^%s\s*[:-]?\s*}', preg_quote($advisory['cve'])),
                            '',
                            $title
                        );
                    }

                    if (isset($advisory['link']) && strlen($advisory['link']) > 0) {
                        $title = self::formatHyperlink($output, $advisory['link'], $title);
                    }

                    $output->writeln($title);
                }

                $output->writeln('');
            }

            return 1;
        }

        return 0;
    }

    private static function formatHyperlink(OutputInterface $output, string $link, ?string $label): string
    {
        $useEscapeSequence = $output->getFormatter()->isDecorated();

        if ($label !== null) {
            $format = $useEscapeSequence
                ? "\033]8;;%s\033\\%s\033]8;;\033\\"
                : '%2$s: <%1$s>';
        } else {
            $format = $useEscapeSequence
                ? "\033]8;;%1\$s\033\\%1\$s\033]8;;\033\\"
                : '<%s>';
        }

        return sprintf($format, $link, $label);
    }
}
