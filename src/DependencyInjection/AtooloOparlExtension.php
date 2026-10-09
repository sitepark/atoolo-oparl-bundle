<?php

declare(strict_types=1);

namespace Atoolo\Oparl\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * Registers the cache pool `atoolo_oparl.cache` with the framework bundle.
 *
 * The pool is prepended, so that a project overrides it like any other
 * pool in `framework.cache.pools`. A service definition of the bundle could
 * not be overridden that way: definitions of the container itself win over
 * those of extensions.
 *
 * The services themselves are loaded by {@see \Atoolo\Oparl\AtooloOparlBundle}.
 */
class AtooloOparlExtension extends Extension implements PrependExtensionInterface
{
    public const CACHE_POOL = 'atoolo_oparl.cache';

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('framework')) {
            return;
        }
        $container->prependExtensionConfig('framework', [
            'cache' => [
                'pools' => [
                    self::CACHE_POOL => [
                        'adapter' => 'atoolo_oparl.cache.adapter.filesystem',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void {}
}
