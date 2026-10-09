<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Dto\Indexer;

use Atoolo\Index\Dto\Indexer\IndexerConfiguration;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Resource\DataBag;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OparlIndexerParameter::class)]
class OparlIndexerParameterTest extends TestCase
{
    public function testFromConfiguration(): void
    {
        $parameter = OparlIndexerParameter::fromConfiguration(
            new IndexerConfiguration('oparl-paper', 'Vorlagen', new DataBag([
                'systemUrl' => 'https://ris.test/oparl',
                'bodyUrls' => ['https://ris.test/oparl/body/1', ''],
                'cleanupThreshold' => 5,
                'chunkSize' => 50,
                'fullSyncInterval' => 12,
                'modifiedSinceOverlap' => 60,
                'omitInternal' => true,
                'group' => 12,
                'groupPath' => [1, '12', 'invalid'],
                'category' => [7],
                'categoryPath' => ['3', 7, 1.5],
                'custom' => 'value',
            ])),
        );

        $this->assertEquals(
            new OparlIndexerParameter(
                source: 'oparl-paper',
                name: 'Vorlagen',
                systemUrl: 'https://ris.test/oparl',
                bodyUrls: ['https://ris.test/oparl/body/1'],
                cleanupThreshold: 5,
                chunkSize: 50,
                fullSyncInterval: 12,
                modifiedSinceOverlap: 60,
                omitInternal: true,
                group: 12,
                groupPath: [1, 12],
                category: [7],
                categoryPath: [3, 7],
                data: $parameter->data,
            ),
            $parameter,
        );
        $this->assertSame('value', $parameter->data->getString('custom'));
    }

    public function testDefaults(): void
    {
        $parameter = OparlIndexerParameter::fromConfiguration(
            new IndexerConfiguration('oparl-meeting', 'oparl-meeting', new DataBag([
                'systemUrl' => 'https://ris.test/oparl',
            ])),
        );

        $this->assertSame([], $parameter->bodyUrls);
        $this->assertSame(10, $parameter->cleanupThreshold);
        $this->assertSame(500, $parameter->chunkSize);
        $this->assertSame(24, $parameter->fullSyncInterval);
        $this->assertFalse($parameter->omitInternal);
        $this->assertNull($parameter->group);
        $this->assertTrue($parameter->isDeltaEnabled());
    }

    public function testBodyUrlsWithoutSystemUrl(): void
    {
        $parameter = new OparlIndexerParameter(
            'oparl-meeting',
            'Sitzungen',
            '',
            ['https://ris.test/oparl/body/1'],
        );
        $this->assertSame('', $parameter->systemUrl);
    }

    public function testMissingUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OparlIndexerParameter('oparl-meeting', 'Sitzungen', '');
    }

    public function testChunkSizeTooSmall(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OparlIndexerParameter(
            'oparl-meeting',
            'Sitzungen',
            'https://ris.test/oparl',
            chunkSize: 9,
        );
    }

    public function testDeltaDisabled(): void
    {
        $parameter = new OparlIndexerParameter(
            'oparl-meeting',
            'Sitzungen',
            'https://ris.test/oparl',
            fullSyncInterval: 0,
        );
        $this->assertFalse($parameter->isDeltaEnabled());
    }
}
