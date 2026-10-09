<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test;

use Atoolo\Index\Service\Indexer\IndexerConfigurationLoader;
use Atoolo\Index\Service\Indexer\IndexerStatusStore;
use Atoolo\Index\Service\Indexer\IndexingAborter;
use Atoolo\Index\Service\Indexer\PhpLimitIncreaser;
use Atoolo\Index\Service\IndexName;
use Atoolo\Oparl\AtooloOparlBundle;
use Atoolo\Oparl\Service\Indexer\OparlIndexer;
use Atoolo\Oparl\Service\Indexer\OparlObjectType;
use Atoolo\Search\Service\Indexer\SolrIndexService;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Loads the service configuration of the bundle into a container with
 * stand-ins for the services of the other bundles.
 */
#[CoversNothing]
class AtooloOparlBundleTest extends TestCase
{
    public function testAllIndexersCanBeCreated(): void
    {
        $container = $this->createContainer();

        $sources = [];
        foreach (array_keys($container->findTaggedServiceIds('atoolo_index.indexer')) as $id) {
            $indexer = $container->get($id);
            $this->assertInstanceOf(OparlIndexer::class, $indexer);
            $sources[$indexer->getSource()] = $indexer->getObjectType();
        }

        $expected = [];
        foreach (OparlObjectType::cases() as $type) {
            $expected[$type->source()] = $type;
        }
        $this->assertEquals($expected, $sources);
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        (new AtooloOparlBundle())->build($container);

        foreach (array_keys($container->findTaggedServiceIds('atoolo_index.indexer')) as $id) {
            $container->getDefinition($id)->setPublic(true);
        }
        $stubs = [
            'logger' => new NullLogger(),
            'atoolo_index.index_name' => $this->createStub(IndexName::class),
            'atoolo_index.indexer.status_store' => new IndexerStatusStore(sys_get_temp_dir()),
            'atoolo_index.indexer.aborter' => new IndexingAborter(sys_get_temp_dir(), 'test'),
            'atoolo_index.indexer.configuration_loader' => $this->createStub(IndexerConfigurationLoader::class),
            'atoolo_index.indexer.php_limit_increaser' => new PhpLimitIncreaser(60, '128M'),
            'atoolo_search.indexer.solr_index_service' => $this->createStub(SolrIndexService::class),
        ];
        foreach (array_keys($stubs) as $id) {
            $container->register($id)->setSynthetic(true)->setPublic(true);
        }
        $container->compile();
        foreach ($stubs as $id => $service) {
            $container->set($id, $service);
        }
        return $container;
    }
}
