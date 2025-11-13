<?php

declare(strict_types=1);

namespace Cs278\ComposerAudit;

use Composer\Composer;
use Composer\Semver\Semver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function Cs278\Mktemp\temporaryDir;

/**
 * @copyright 2020 Chris Smith
 * @license MIT
 */
final class IntegrationTest extends TestCase
{
    /** @var string|null */
    private static $cacheDir = null;

    /** @var \Closure[]  */
    private static $cleanupAfterClass = [];

    /**
     * @beforeClass
     */
    public static function prepareCache()
    {
        // Find cache directory of the users Composer installation, if it cannot
        // be found fallback to a temporary one for the lifetime of these tests.
        $process = new Process([
            'composer',
            'config',
            'cache-dir',
        ]);

        $process->run();
        $result = trim($process->getOutput());

        if ($process->isSuccessful() && $result !== '' && is_dir($result)) {
            self::$cacheDir = $result;
        } else {
            self::$cacheDir = temporaryDir();
            self::$cleanupAfterClass[] = function () {
                (new Filesystem())->remove(self::$cacheDir);
            };
        }

        self::$cleanupAfterClass[] = function () {
            self::$cacheDir = null;
        };
    }

    /**
     * @afterClass
     */
    public static function cleanup()
    {
        try {
            foreach (self::$cleanupAfterClass as $callback) {
                $callback();
            }
        } finally {
            self::$cleanupAfterClass = [];
        }
    }

    /**
     * @coversNothing
     * @dataProvider dataRun
     */
    public function testRun(int $expectedExit, string $expectedOutput, string $condition, array $composerJson, array $args)
    {
        \assert(self::$cacheDir !== null);

        $composerJsonTemplate = [
            'require-dev' => [
                'cs278/composer-audit' => '*@dev',
            ],
            'config' => [
                'notify-on-install' => false,
                'allow-plugins' => [
                    'cs278/composer-audit' => true,
                ],
            ],
            'repositories' => [
                ['type' => 'path', 'url' => getcwd()],
            ],
        ];

        $composerJson = array_merge_recursive($composerJsonTemplate, $composerJson);

        if (!self::executeCondition($condition)) {
            $this->markTestSkipped($condition);
        }

        $workingDir = temporaryDir();

        (new Filesystem())->dumpFile(
            "{$workingDir}/.composer/config.json",
            \json_encode([
                'config' => [
                    'audit' => [
                        'block-insecure' => false,
                    ],
                ],
            ])
		);

        // Environment variable instructs tests to use another Composer binary,
        // this allows testing with the systems Composer installation.
        $composerBin = getenv('COMPOSER_AUDIT_TEST_COMPOSER_BINARY');

        if (!is_string($composerBin) || is_dir($composerBin) || !is_executable($composerBin)) {
            $composerBin = getcwd().'/vendor/bin/composer';
        }

        $composer = function (...$args) use ($composerBin, $workingDir) {
            array_unshift($args, $composerBin);

            return new Process($args, $workingDir, [
                'COMPOSER_HOME' => $workingDir.'/.composer',
                'COMPOSER_CACHE_DIR' => self::$cacheDir,
                'COMPOSER_NO_AUDIT' => '1', // Disable built in auditing.
                'COMPOSER_AUDIT_TEST' => 1,
                'COMPOSER_AUDIT_ADVISORIES_PACKAGE' => 'sensiolabs/security-advisories:dev-master#d1749520b5e16eceeb6bceeae73af790773a371b'
            ]);
        };

        try {
            if (Semver::satisfies(Composer::VERSION, '>= 2') && ($composerJson['config']['lock'] ?? true)) {
                // When running Composer 2 there is no need to install packages.

                // But the plugin needs to be installed first.
                file_put_contents($workingDir.'/composer.json', json_encode($composerJsonTemplate));
                $composer('update')->mustRun();

                // Update the lock file with new requirements.
                file_put_contents($workingDir.'/composer.json', json_encode($composerJson));
                $composer('update', '--no-install')->mustRun();

                $lockedPackages = self::extractLockedPackages($workingDir.'/composer.lock');
            } else {
                file_put_contents($workingDir.'/composer.json', json_encode($composerJson));
                $composer('update')->mustRun();

                $lockedPackages = file_exists($workingDir.'/composer.lock')
                    ? self::extractLockedPackages($workingDir.'/composer.lock')
                    : null;
            }

            // Execute condition again now package data is available.
            if (!self::executeCondition($condition, $lockedPackages)) {
                $this->markTestSkipped($condition);
            }

            $proc = $composer('security-audit', ...$args);

            if ($expectedExit === 0) {
                $proc->mustRun();
            } else {
                $proc->run();

                if (!$proc->isSuccessful() && $proc->getExitCode() !== $expectedExit) {
                    throw new ProcessFailedException($proc);
                }
            }

            // Filter out deprecation notices as there is no way of suppressing these when running Composer.
            // https://github.com/composer/composer/blob/50cded331ced9acb4e926be3dda1f74b86af2a3b/bin/composer#L21
            $output = \preg_replace('{\nDeprecated: .+?\n}m', '', $proc->getOutput());
            $output = \preg_replace('{\nUser Deprecated: .+?\n}m', '', $output);

            self::assertEquals($expectedOutput, $output);
            self::assertEquals($expectedExit, $proc->getExitCode());
        } finally {
            (new Filesystem())->remove($workingDir);
        }
    }

    public function dataRun(): iterable
    {
        foreach (glob(__DIR__.'/*.test') as $file) {
            $test = file_get_contents($file);

            preg_match('/--TEST--\s*(?<name>.*?)\s*(?:--CONDITION--\s*(?<condition>.*?))?\s*--COMPOSER--\s*(?<composer>\{.*?\})\s*(?:--ARGS--\s*(?<args>.*?))?\s*--EXPECT-EXIT--\n(?<expectedExit>\d+)\n--EXPECT-OUTPUT--\n(?<expectedOutput>.*)/s', $test, $match);

            if ($match === []) {
                $this->fail(sprintf('Failed to parse %s', $file));
            }

            yield sprintf('%s (%s)', $match[1], basename($file)) => [
                (int) $match['expectedExit'], // expected exit code
                $match['expectedOutput'], // expected output
                $match['condition'], // condition
                json_decode($match['composer'], true),
                $match['args'] !== '' ? explode("\n", $match['args']) : [],
            ];

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->fail(sprintf('Failed to parse %s: %s', $file, json_last_error_msg()));
            }
        }
    }

    /** @return array<string,string> */
    private static function extractLockedPackages(string $lockFile): array
    {
        $result = \json_decode(file_get_contents($lockFile), true);

        if ($result === null) {
            throw new \RuntimeException(sprintf('Failed to parse lock file: %s', $lockFile));
        }

        $packages = [];

        foreach (['packages', 'packages-dev'] as $type) {
            if (isset($result[$type])) {
                foreach ($result[$type] as $package) {
                    $packages[$package['name']] = $package['version'];
                }
            }
        }

        return $packages;
    }

    private static function executeCondition(string $condition, ?array $packages = null): bool
    {
        $template = <<<'EOT'
$isComposer = function ($constraint) {
    return \Composer\Semver\Semver::satisfies(\Composer\Composer::VERSION, $constraint);
};

if ($packages === null) {
    $isPackage = function () { return true; };
} else {
    $isPackage = function (string $package, string $constraint) use ($packages) {
        return isset($packages[$package])
            ? \Composer\Semver\Semver::satisfies($packages[$package], $constraint)
            : false;
    };
}

unset($packages); // Prevent direct access to packages data.

return (%1$s);
EOT;

        return (bool) eval(sprintf($template, $condition));
    }
}
