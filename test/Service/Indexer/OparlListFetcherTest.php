<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer;

use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\OparlListFetcher;
use Atoolo\Oparl\Service\Indexer\OparlType;
use Atoolo\Oparl\Test\Fixture\FakeOparlServer;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\OparlClient;

#[CoversClass(OparlListFetcher::class)]
class OparlListFetcherTest extends TestCase
{
    private const SYSTEM = 'https://ris.test/oparl';
    private const BODY_1 = self::SYSTEM . '/body/1';
    private const BODY_2 = self::SYSTEM . '/body/2';

    private FakeOparlServer $server;

    private OparlListFetcher $fetcher;

    protected function setUp(): void
    {
        $this->server = new FakeOparlServer();
        $this->server
            ->json(self::SYSTEM, [
                'id' => self::SYSTEM,
                'type' => 'https://schema.oparl.org/1.1/System',
                'body' => self::SYSTEM . '/bodies',
            ])
            ->page(self::SYSTEM . '/bodies', [self::body(1), self::body(2)])
            ->json(self::BODY_1, self::body(1))
            ->page(self::BODY_1 . '/papers', [['id' => 'p1', 'type' => 'https://schema.oparl.org/1.1/Paper']])
            ->page(self::BODY_2 . '/papers', []);
        $this->fetcher = new OparlListFetcher(new OparlClient(
            httpClient: $this->server,
            requestFactory: new Psr17Factory(),
        ));
    }

    public function testOpensListsOfAllBodiesOfTheSystem(): void
    {
        $lists = $this->fetcher->openLists(
            new OparlIndexerParameter('oparl-paper', 'Vorlagen', self::SYSTEM),
            OparlType::PAPER,
            null,
        );

        $this->assertCount(2, $lists);
        $this->assertContains(self::BODY_1 . '/papers', $this->server->requests);
        $this->assertContains(self::BODY_2 . '/papers', $this->server->requests);
    }

    public function testOpensListsOfConfiguredBodies(): void
    {
        $lists = $this->fetcher->openLists(
            new OparlIndexerParameter('oparl-paper', 'Vorlagen', '', [self::BODY_1]),
            OparlType::PAPER,
            null,
        );

        $this->assertCount(1, $lists);
        $this->assertNotContains(self::SYSTEM, $this->server->requests);
    }

    public function testAppendsFilters(): void
    {
        $this->fetcher->openLists(
            new OparlIndexerParameter(
                'oparl-paper',
                'Vorlagen',
                '',
                [self::BODY_1],
                omitInternal: true,
            ),
            OparlType::PAPER,
            new DateTimeImmutable('2026-10-08T12:00:00+00:00'),
        );

        $this->assertContains(
            self::BODY_1 . '/papers?modified_since=2026-10-08T12:00:00%2B00:00&omit_internal=true',
            $this->server->requests,
        );
    }

    public function testSkipsBodiesWithoutList(): void
    {
        $lists = $this->fetcher->openLists(
            new OparlIndexerParameter('oparl-file', 'Dateien', self::SYSTEM),
            OparlType::FILE,
            null,
        );

        $this->assertSame([], $lists);
    }

    public function testSystemWithoutBodies(): void
    {
        $this->server->json(self::SYSTEM, [
            'id' => self::SYSTEM,
            'type' => 'https://schema.oparl.org/1.1/System',
        ]);

        $lists = $this->fetcher->openLists(
            new OparlIndexerParameter('oparl-paper', 'Vorlagen', self::SYSTEM),
            OparlType::PAPER,
            null,
        );

        $this->assertSame([], $lists);
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(int $number): array
    {
        $id = self::SYSTEM . '/body/' . $number;
        return [
            'id' => $id,
            'type' => 'https://schema.oparl.org/1.1/Body',
            'paper' => $id . '/papers',
        ];
    }
}
