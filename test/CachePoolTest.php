<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test;

use Atoolo\Oparl\Test\Fixture\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

/**
 * The cache pool `atoolo_oparl.cache` of the bundle can be replaced by a
 * framework cache pool of the same name.
 */
#[CoversNothing]
class CachePoolTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/atoolo-oparl-kernel-' . uniqid();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testFilesystemCacheByDefault(): void
    {
        $container = $this->boot();

        $cache = $container->get('atoolo_oparl.cache');

        $this->assertInstanceOf(FilesystemAdapter::class, $cache);
        $item = $cache->getItem('key');
        $item->set('value');
        $cache->save($item);
        $this->assertNotSame(
            [],
            glob($this->projectDir . '/var/oparl/*'),
            'the cache should be written to var/oparl',
        );
    }

    public function testReplaceableByFrameworkPool(): void
    {
        $container = $this->boot([
            'cache' => [
                'pools' => [
                    'atoolo_oparl.cache' => ['adapter' => 'cache.adapter.array'],
                ],
            ],
        ]);

        $this->assertInstanceOf(
            ArrayAdapter::class,
            $container->get('atoolo_oparl.cache'),
        );
    }

    public function testClearableAsPool(): void
    {
        $container = $this->boot();

        $clearer = $container->get('cache.global_clearer');

        $this->assertInstanceOf(Psr6CacheClearer::class, $clearer);
        $this->assertTrue($clearer->hasPool('atoolo_oparl.cache'));
    }

    /**
     * @param array<string, mixed> $frameworkConfig
     */
    private function boot(array $frameworkConfig = []): ContainerInterface
    {
        $kernel = new TestKernel($this->projectDir, $frameworkConfig);
        $kernel->boot();
        return $kernel->getContainer()->get('test.service_container');
    }
}
