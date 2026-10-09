<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer;

use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use SP\OparlClient\V1\Objects\OparlObjectV1;

/**
 * Default {@see OparlObjectFilter}: every object is indexed.
 */
class AcceptAllOparlObjectFilter implements OparlObjectFilter
{
    public function accept(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
    ): bool {
        return true;
    }
}
