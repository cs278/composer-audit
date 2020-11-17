<?php

declare(strict_types=1);

namespace Cs278\ComposerAudit;

use Composer\Command\BaseCommand;
use Composer\Package\PackageInterface;
use Composer\Semver\Semver;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
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

        // NULL if option is unknown.
        $lockOption = $this->getComposer()->getConfig()->get('lock');

        if ($lockOption === null || $lockOption === true) {
            if (!$this->getComposer()->getLocker()->isLocked()) {
                $output->writeln('<error>Lock file not found.</error>');

                return 2;
            }

            $lockData = $this->getComposer()->getLocker()->getLockData();
            $usingInstalled = false;
        } else {
            $lockData = [];
            $lockData['packages'] = array_map(static function (PackageInterface $package): array {
                return [
                    'name' => $package->getName(),
                    'version' => $package->getPrettyVersion(),
                ];
            }, $this->getComposer()->getRepositoryManager()->getLocalRepository()->getCanonicalPackages());
            $lockData['packages-dev'] = [];

            if (!$this->dev) {
                $output->writeln('<warning>Warning --no-dev option has no effect when lock file generation is disabled.</warning>');
            }

            $usingInstalled = true;
        }

        if ($this->dev) {
            $packages = array_merge(
                $lockData['packages'],
                $lockData['packages-dev']
            );
        } else {
            $packages = $lockData['packages'];
        }

        $packages = array_map(static function (array $package): array {
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

        self::clearLine(
            $output instanceof ConsoleOutputInterface
                ? $output->getErrorOutput()
                : $output
        );

        if ($advisories !== []) {
            // Advise the user of the advisories.
            $totalAdvisories = array_sum(array_map(static function (array $packageAdvisories): int {
                return \count($packageAdvisories);
            }, $advisories));
            $packagesAffected = \count($advisories);

            // @todo Pluralization?
            $output->writeln(sprintf(
                $usingInstalled
                    ? '<error>Found %u advisories affecting %u installed package(s).</error>'
                    : '<error>Found %u advisories affecting %u package(s).</error>',
                $totalAdvisories,
                $packagesAffected
            ));

            $output->writeln('');

            ksort($advisories, \SORT_NATURAL | \SORT_ASC);

            foreach ($advisories as $reference => $packageAdvisories) {
                $output->writeln(sprintf('<info>%s (%s)</info>', $reference, $packages[$reference]));

                foreach ($packageAdvisories as $advisory) {
                    $title = $advisory['title'];

                    if (isset($advisory['link']) && strlen($advisory['link']) > 0) {
                        $link = $advisory['link'];
                    } else {
                        $link = null;
                    }

                    if (isset($advisory['cve']) && strlen($advisory['cve']) > 0) {
                        $cve = $advisory['cve'];
                        $cveLink = sprintf(
                            'https://cve.mitre.org/cgi-bin/cvename.cgi?name=%s',
                            rawurlencode($advisory['cve'])
                        );

                        // Strip any reference of the CVE from the start of the advisory title.
                        $title = preg_replace(
                            sprintf('{^%s\s*[:-]?\s*}', preg_quote($advisory['cve'])),
                            '',
                            $title
                        );
                    } else {
                        $cve = $cveLink = null;
                    }

                    if ($output->isDecorated()) {
                        $output->writeln(sprintf(
                            $cve !== null ? '* %s: %s' : '* %2$s',
                            $cveLink !== null ? self::formatHyperlink($output, $cveLink, $cve): $cve,
                            $link !== null ? self::formatHyperlink($output, $link, $title) : $title
                        ));
                    } else {
                        $output->writeln(sprintf(
                            $cve !== null ? '* %s %s' : '* %2$s',
                            $cve,
                            $title
                        ));

                        foreach ([$cveLink, $link] as $url) {
                            if ($url !== null) {
                                $output->writeln(sprintf("  - <%s>", $url));
                            }
                        }
                    }
                }

                $output->writeln('');
            }

            return 1;
        }

        if ($output->isVerbose()) {
            $output->writeln('No advisories found for any packages.');
        }

        return 0;
    }

    private static function formatHyperlink(OutputInterface $output, string $link, ?string $label): string
    {
        $useEscapeSequence = $output->isDecorated();

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

    private static function clearLine(OutputInterface $output): void
    {
        if ($output->isDecorated()) {
            if (\class_exists(Cursor::class)) {
                (new Cursor($output))
                    ->clearLine()
                    ->moveToColumn(1);
            } else {
                $output->write("\x1b[2K");
                $output->write(sprintf("\x1b[%dG", 1));
            }
        } else {
            $output->writeln('');
        }
    }
}
