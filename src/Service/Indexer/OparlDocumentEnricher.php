<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer;

use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use SP\OparlClient\V1\Objects\OparlObjectV1;

/**
 * Maps an OParl object onto an index document.
 *
 * Enrichers are target specific and registered per target with a tag of
 * their own, for Solr `atoolo_oparl.indexer.document_enricher.schema2x`.
 * All enrichers of a target are called for every object, in the order of
 * their priority; an enricher that only handles one OParl type checks the
 * type itself. A project adds its own mapping by registering a further
 * enricher with a lower priority than the ones of this bundle.
 *
 * @template T of IndexDocument
 */
interface OparlDocumentEnricher
{
    /**
     * @param T $doc
     * @return T
     */
    public function enrichDocument(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
        IndexDocument $doc,
        string $processId,
    ): IndexDocument;
}
