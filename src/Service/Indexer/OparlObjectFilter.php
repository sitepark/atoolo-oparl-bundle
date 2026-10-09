<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer;

use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use SP\OparlClient\V1\Objects\OparlObjectV1;

/**
 * Decides which OParl objects are indexed.
 *
 * The bundle registers {@see AcceptAllOparlObjectFilter} as service
 * `atoolo_oparl.indexer.object_filter`; a project replaces that service to
 * leave out objects, e.g. those without a page in the council information
 * system (`web`). The filter is shared by all OParl indexers, the indexer
 * a call belongs to is given by `$parameter->source`.
 *
 * An object that is not accepted is removed from the index, should it have
 * been indexed before.
 */
interface OparlObjectFilter
{
    public function accept(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
    ): bool;
}
