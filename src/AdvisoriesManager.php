<?php

namespace Cs278\ComposerAudit;

use Composer\Composer;
use Composer\Plugin\PluginInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles finding security advisories.
 *
 * Pulls a composer package of security advisory data which is not managed as
 * a normal dependency.
 *
 * @copyright 2020 Chris Smith
 * @license MIT
 */
final class AdvisoriesManager
{
    /** @var AdvisoriesInstaller */
    private $installer;

    /** @var VersionParser */
    private $versionParser;

    private $packageName = 'sensiolabs/security-advisories';
    private $packageConstraint = 'dev-master';

    /** @var string */
    private $directory;

    /** @var array<string,array<mixed,mixed>> */
    private $advisories;

    public function __construct(AdvisoriesInstaller $installer)
    {
        $this->installer = $installer;
        $this->versionParser = new VersionParser();
    }

    public static function create(Composer $composer)
    {
        if (version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0.0', '>=')) {
            $installer = new AdvisoriesInstallerV2(
                $composer->getLoop(),
                $composer->getRepositoryManager(),
                $composer->getDownloadManager()
            );
        } else {
            $installer = new AdvisoriesInstallerV1(
                $composer->getRepositoryManager(),
                $composer->getDownloadManager()
            );
        }

        return new self($installer);
    }

    public function mustUpdate()
    {
        $this->installer->mustUpdate();
    }

    /**
     * Find all advisories.
     *
     * @return iterable<int,array<mixed,mixed>>
     */
    public function findAll(): iterable
    {
        if (!isset($this->advisories)) {
            $this->advisories = [];

            $advisoriesDir = $this->getDirectory();
            \assert(is_dir($advisoriesDir));

            // Find all the advisories for installed packages.
            foreach (glob("$advisoriesDir/*/*/*.yaml") as $file) {
                $advisory = Yaml::parseFile($file);

                $this->advisories[$file] = $advisory;
            }
        }

        yield from $this->advisories;
    }

    /**
     * Find any advisory applying to the given package name and version.
     *
     * @return iterable<int,array<mixed,mixed>>
     */
    public function findByPackageNameAndVersion(string $name, string $version): iterable
    {
        $reference = sprintf('composer://%s', $name);
        $constraint = new Constraint('==', $this->versionParser->normalize($version));

        foreach ($this->findAll() as $advisory) {
            if ($advisory['reference'] === $reference) {
                if ($this->createConstraint($advisory)->matches($constraint)) {
                    yield $advisory;
                }
            }
        }
    }

    /**
     * Construct contstraint from the advistory.
     */
    private function createConstraint(array $advisory): ConstraintInterface
    {
        $constraints = [];

        foreach ($advisory['branches'] as $branch) {
            $branchConstraints = [];

            foreach ($branch['versions'] as $version) {
                $branchConstraints[] = $this->versionParser->parseConstraints($version);
            }

            if ($branchConstraints !== []) {
                $constraints[] = count($branchConstraints) > 1
                    ? new MultiConstraint($branchConstraints, true)
                    : $branchConstraints[0];
            }
        }

        if (\count($constraints) === 1) {
            return $constraints[0];
        }

        return new MultiConstraint($constraints, false);
    }

    private function getDirectory()
    {
        if (!isset($this->directory)) {
            $this->directory = $this->installer->install(
                dirname(__DIR__).'/var',
                $this->packageName,
                $this->packageConstraint
            );
        }

        return $this->directory;
    }
}
