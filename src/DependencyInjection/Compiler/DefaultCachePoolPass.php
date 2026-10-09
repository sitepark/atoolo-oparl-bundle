<?php

declare(strict_types=1);

namespace Atoolo\Oparl\DependencyInjection\Compiler;

use Atoolo\Oparl\DependencyInjection\AtooloOparlExtension;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Creates the cache pool `atoolo_oparl.cache` as a plain filesystem cache
 * if no framework bundle registered it.
 */
class DefaultCachePoolPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has(AtooloOparlExtension::CACHE_POOL)) {
            return;
        }
        $container->setDefinition(
            AtooloOparlExtension::CACHE_POOL,
            new Definition(FilesystemAdapter::class, [
                'atoolo_oparl',
                0,
                '%atoolo_oparl.cache_dir%',
            ]),
        );
    }
}
