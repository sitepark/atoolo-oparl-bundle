<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer;

use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\AcceptAllOparlObjectFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\V1\Objects\OparlMeeting;

#[CoversClass(AcceptAllOparlObjectFilter::class)]
class AcceptAllOparlObjectFilterTest extends TestCase
{
    public function testAccept(): void
    {
        $this->assertTrue((new AcceptAllOparlObjectFilter())->accept(
            new OparlMeeting(),
            new OparlIndexerParameter('oparl-meeting', 'Sitzungen', 'https://ris.test/oparl'),
        ));
    }
}
