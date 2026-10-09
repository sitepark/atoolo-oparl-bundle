<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Dto\Indexer;

use Atoolo\Oparl\Dto\Indexer\OparlIndexerState;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OparlIndexerState::class)]
class OparlIndexerStateTest extends TestCase
{
    /**
     * @return iterable<string, array{OparlIndexerState, int, bool}>
     */
    public static function states(): iterable
    {
        $now = new DateTimeImmutable('2026-10-08T12:00:00+00:00');
        yield 'first run' => [new OparlIndexerState(), 24, true];
        yield 'no full run yet' => [
            new OparlIndexerState($now->modify('-1 hour')),
            24,
            true,
        ];
        yield 'within interval' => [
            new OparlIndexerState($now->modify('-1 hour'), $now->modify('-23 hours')),
            24,
            false,
        ];
        yield 'interval reached' => [
            new OparlIndexerState($now->modify('-1 hour'), $now->modify('-24 hours')),
            24,
            true,
        ];
        yield 'delta disabled' => [
            new OparlIndexerState($now->modify('-1 hour'), $now->modify('-1 hour')),
            0,
            true,
        ];
    }

    #[DataProvider('states')]
    public function testIsFullRunDue(
        OparlIndexerState $state,
        int $interval,
        bool $expected,
    ): void {
        $this->assertSame(
            $expected,
            $state->isFullRunDue(
                new DateTimeImmutable('2026-10-08T12:00:00+00:00'),
                $interval,
            ),
        );
    }
}
