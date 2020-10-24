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
    /**
     * @coversNothing
     * @dataProvider dataRun
     */
    public function testRun(int $expectedExit, string $expectedOutput, string $condition, array $composerJson, array $args)
    {
        $composerJsonTemplate = [
            'require-dev' => [
                'cs278/composer-audit' => '*@dev',
            ],
            'config' => [
                'notify-on-install' => false,
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
        $composer = function (...$args) use ($workingDir) {
            array_unshift($args, getcwd().'/vendor/bin/composer');

            return new Process($args, $workingDir);
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
            } else {
                file_put_contents($workingDir.'/composer.json', json_encode($composerJson));
                $composer('update')->mustRun();
            }

            $proc = $composer('audit', ...$args);

            if ($expectedExit === 0) {
                $proc->mustRun();
            } else {
                $proc->run();

                if (!$proc->isSuccessful() && $proc->getExitCode() !== $expectedExit) {
                    throw new ProcessFailedException($proc);
                }
            }

            self::assertEquals($expectedOutput, $proc->getOutput());
            self::assertEquals($expectedExit, $proc->getExitCode());
        } finally {
            (new Filesystem())->remove($workingDir); // @todo Add require-dev for this
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

    private static function executeCondition(string $condition): bool
    {
        $template = <<<'EOT'
$isComposer = function ($constraint) {
    return \Composer\Semver\Semver::satisfies(\Composer\Composer::VERSION, $constraint);
};

return %s;
EOT;

        return (bool) eval(sprintf($template, $condition));
    }
}
