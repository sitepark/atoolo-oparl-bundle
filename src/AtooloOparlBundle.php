<?php

declare(strict_types=1);

namespace Atoolo\Oparl;

use Atoolo\Oparl\DependencyInjection\Compiler\DefaultCachePoolPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\GlobFileLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @codeCoverageIgnore
 */
class AtooloOparlBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $configDir = __DIR__ . '/../config';

        $locator = new FileLocator($configDir);
        $loader = new GlobFileLoader($locator);
        $loader->setResolver(
            new LoaderResolver(
                [
                    new YamlFileLoader($container, $locator),
                ],
            ),
        );

        $loader->load('services.yaml');

        $container->addCompilerPass(
            new DefaultCachePoolPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            100,
        );
    }
}
