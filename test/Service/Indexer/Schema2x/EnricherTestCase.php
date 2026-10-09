<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer\Schema2x;

use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Test\Fixture\FakeOparlServer;
use PHPUnit\Framework\TestCase;

abstract class EnricherTestCase extends TestCase
{
    protected const SYSTEM = 'https://ris.test/oparl';
    protected const ORGANIZATION = self::SYSTEM . '/organization/1';
    protected const MEETING = self::SYSTEM . '/meeting/1';

    protected FakeOparlServer $server;

    protected function setUp(): void
    {
        $this->server = new FakeOparlServer();
        $this->server
            ->json(self::ORGANIZATION, [
                'id' => self::ORGANIZATION,
                'type' => 'https://schema.oparl.org/1.1/Organization',
                'name' => 'Rat der Stadt',
            ])
            ->json(self::MEETING, [
                'id' => self::MEETING,
                'type' => 'https://schema.oparl.org/1.1/Meeting',
                'name' => '12. Sitzung des Rates',
                'start' => '2026-10-08T17:00:00+02:00',
                'end' => '2026-10-08T20:00:00+02:00',
            ]);
    }

    protected static function parameter(): OparlIndexerParameter
    {
        return new OparlIndexerParameter(
            'oparl-test',
            'Test',
            self::SYSTEM,
        );
    }
}
