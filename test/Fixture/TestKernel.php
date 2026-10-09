<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Fixture;

use Atoolo\Oparl\AtooloOparlBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Kernel with the framework bundle and this bundle, configured with the
 * given framework configuration. The cache pool of the bundle is public.
 */
final class TestKernel extends Kernel
{
    /**
     * @param array<string, mixed> $frameworkConfig
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly array $frameworkConfig = [],
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new AtooloOparlBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', array_merge([
                'test' => true,
                'secret' => 'test',
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
            ], $this->frameworkConfig));
        });
    }

    protected function build(ContainerBuilder $container): void
    {
        // keep the pool, nothing uses it in a container without indexers
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition('atoolo_oparl.cache')->setPublic(true);
            }
        }, PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getCacheDir(): string
    {
        return $this->projectDir . '/var/cache/' . md5(serialize($this->frameworkConfig));
    }

    public function getLogDir(): string
    {
        return $this->projectDir . '/var/log';
    }
}
