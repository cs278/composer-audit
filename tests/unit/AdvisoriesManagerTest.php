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
 * @covers Cs278\ComposerAudit\AdvisoriesManager
 */
final class AdvisoriesManagerTest extends TestCase
{
    /**
     * @dataProvider dataFindByPackageNameAndVersion
     *
     */
    public function testFindByPackageNameAndVersion(array $expected, string $packageName, string $packageVersion, string $advisories)
    {
        $manager = $this->createManager($advisories);
        $results = [];

        foreach ($manager->findByPackageNameAndVersion($packageName, $packageVersion) as $advisory) {
            $results[] = $advisory['title'];

            self::assertEquals(sprintf('composer://%s', $packageName), $advisory['reference']);
        }

        self::assertEquals($expected, $results);
    }

    public function dataFindByPackageNameAndVersion(): iterable
    {
        yield [
            [],
            'foo/bar',
            '13.37.0',
            'empty',
        ];
        yield [
            [
                'CVE-9999-1234567: Left the front door open',
            ],
            'foo/bar',
            '13.37',
            'simple',
        ];
    }

    private function createManager(string $advisories): AdvisoriesManager
    {
        $installer = new class($advisories) implements AdvisoriesInstallerInterface {
            private $advisories;

            public function __construct(string $advisories)
            {
                $this->advisories = __DIR__.'/advisories/'.$advisories;

                if (!is_dir($this->advisories)) {
                    throw new \InvalidArgumentException(sprintf(
                        '%s is invalid, `%s` is not a directory',
                        $advisories,
                        $this->advisories
                    ));
                }
            }

            public function mustUpdate()
            {
                return; // No op
            }

            public function install($varDirectory, $packageName, $packageConstraint)
            {
                return $this->advisories;
            }
        };

        return new AdvisoriesManager($installer);
    }
}
