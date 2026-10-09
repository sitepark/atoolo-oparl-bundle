<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer;

use Atoolo\Oparl\Service\Indexer\OparlType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\V1\Objects\OparlBody;

#[CoversClass(OparlType::class)]
class OparlTypeTest extends TestCase
{
    public function testListOf(): void
    {
        $body = new OparlBody(
            organization: new OparlReference('o'),
            person: new OparlReference('p'),
            meeting: new OparlReference('m'),
            paper: new OparlReference('pa'),
            agendaItem: new OparlReference('a'),
            file: new OparlReference('f'),
        );

        $uris = array_map(
            static fn(OparlType $type) => $type->listOf($body)?->getUri(),
            OparlType::cases(),
        );

        $this->assertSame(['m', 'pa', 'o', 'p', 'a', 'f'], $uris);
    }

    public function testListOfMissing(): void
    {
        $this->assertNull(OparlType::FILE->listOf(new OparlBody()));
    }

    public function testSource(): void
    {
        $this->assertSame('oparl-agendaItem', OparlType::AGENDA_ITEM->source());
    }
}
