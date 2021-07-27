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

        // Pull config from the extra array in composer.json.
        $config = $this->getComposer()->getPackage()->getExtra()['composer-audit'] ?? [];
        $ignoreCves = [];

        foreach ($config['ignore'] ?? [] as $rule) {
            $type = (string) $rule['type'] ?? '';
            $value = (string) $rule['value'] ?? '';

            if ($type === 'cve' && $value !== '') {
                $ignoreCves[] = $value;
            } else {
                $output->writeln(sprintf(
                    'Ignoring invalid ignore rule: `%s`',
                    json_encode($rule)
                ), OutputInterface::VERBOSITY_VERBOSE);
            }
        }

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

        $packages = array_column($packages, 'version', 'name');

        $advisories = [];

        // Find all the advisories for installed packages.
        foreach ($packages as $name => $version) {
            $output->writeln(sprintf(
                'Checking <info>%s</info> (<info>%s</info>) for advisories...',
                $name,
                $name !== 'cs278/composer-audit' ? $version : 'N/A'
            ), OutputInterface::VERBOSITY_DEBUG);

            foreach ($advisoriesManager->findByPackageNameAndVersion($name, $version) as $advisory) {
                $advisories[$name][] = $advisory;
            }

            if (\count($advisories[$name] ?? []) > 0) {
                $output->writeln(sprintf(
                    'Found %u advisories for <info>%s</info> (<info>%s</info>)',
                    \count($advisories[$name]),
                    $name,
                    $name !== 'cs278/composer-audit' ? $version : 'N/A'
                ), OutputInterface::VERBOSITY_VERY_VERBOSE);
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
            $ignoredAdvisories = 0;

            // @todo Pluralization?
            $output->writeln(sprintf(
                $usingInstalled
                    ? '<error>Found %u advisories affecting %u installed package(s).</error>'
                    : '<error>Found %u advisories affecting %u package(s).</error>',
                $totalAdvisories,
                $packagesAffected
            ));

            $exitCode = 0;

            ksort($advisories, \SORT_NATURAL | \SORT_ASC);
            $firstAdvisory = true;

            foreach ($advisories as $reference => $packageAdvisories) {
                $firstAdvisoryForPackage = true;

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

                    if (\in_array($cve, $ignoreCves, true)) {
                        ++$ignoredAdvisories;

                        continue;
                    }

                    if ($firstAdvisory) {
                        $output->writeln('');
                        $firstAdvisory = false;
                    }

                    if ($firstAdvisoryForPackage) {
                        $output->writeln(sprintf('<info>composer://%s (%s)</info>', $reference, $packages[$reference]));
                        $firstAdvisoryForPackage = false;
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

                    $exitCode = 1;
                }

                // Only need a spacer if any adisories were written.
                if (!$firstAdvisory) {
                    $output->writeln('');
                }
            }

            if ($ignoredAdvisories) {
                $output->writeln(sprintf(
                    '<info>%u advisories were ignored.</info>',
                    $ignoredAdvisories
                ));

                // Change exit code to indicate some things were ignored.
                if ($exitCode === 1) {
                    $exitCode = 2;
                }
            }

            return $exitCode;
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
