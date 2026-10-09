<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer\Schema2x;

use Atoolo\Oparl\Service\Indexer\Schema2x\SolrOparlDocumentRemover;
use Atoolo\Search\Service\Indexer\SolrIndexService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SolrOparlDocumentRemover::class)]
class SolrOparlDocumentRemoverTest extends TestCase
{
    public function testRemove(): void
    {
        $indexService = $this->createMock(SolrIndexService::class);
        $indexService->expects($this->once())
            ->method('deleteByQueryForAllLanguages')
            ->with(
                'sp_source:oparl\-meeting AND id:('
                . '"https://ris.test/oparl/meeting/1" OR '
                . '"https://ris.test/oparl/meeting/\"2\""'
                . ')',
            );

        (new SolrOparlDocumentRemover($indexService))->remove('oparl-meeting', [
            'https://ris.test/oparl/meeting/1',
            'https://ris.test/oparl/meeting/"2"',
            'https://ris.test/oparl/meeting/1',
        ]);
    }

    public function testRemovesInChunks(): void
    {
        $indexService = $this->createMock(SolrIndexService::class);
        $indexService->expects($this->exactly(3))
            ->method('deleteByQueryForAllLanguages');

        (new SolrOparlDocumentRemover($indexService))->remove(
            'oparl-meeting',
            array_map(
                static fn(int $n) => 'https://ris.test/oparl/meeting/' . $n,
                range(1, 250),
            ),
        );
    }

    public function testRemoveNothing(): void
    {
        $indexService = $this->createMock(SolrIndexService::class);
        $indexService->expects($this->never())
            ->method('deleteByQueryForAllLanguages');

        (new SolrOparlDocumentRemover($indexService))->remove('oparl-meeting', []);
    }
}
