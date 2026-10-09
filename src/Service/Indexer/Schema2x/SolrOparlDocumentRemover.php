<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer\Schema2x;

use Atoolo\Oparl\Service\Indexer\OparlDocumentRemover;
use Atoolo\Search\Service\Indexer\SolrIndexService;
use Solarium\Core\Query\Helper;

/**
 * Deletes OParl documents from Solr by a query on their `id`.
 */
class SolrOparlDocumentRemover implements OparlDocumentRemover
{
    private const CHUNK_SIZE = 100;

    private readonly Helper $helper;

    public function __construct(
        private readonly SolrIndexService $indexService,
    ) {
        $this->helper = new Helper();
    }

    public function remove(string $source, array $oparlIds): void
    {
        $oparlIds = array_values(array_unique($oparlIds));
        foreach (array_chunk($oparlIds, self::CHUNK_SIZE) as $chunk) {
            $this->indexService->deleteByQueryForAllLanguages(
                'sp_source:' . $this->helper->escapeTerm($source)
                . ' AND id:(' . implode(' OR ', array_map(
                    $this->helper->escapePhrase(...),
                    $chunk,
                )) . ')',
            );
        }
    }
}
