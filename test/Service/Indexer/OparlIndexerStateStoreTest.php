<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer;

use Atoolo\Oparl\Dto\Indexer\OparlIndexerState;
use Atoolo\Oparl\Service\Indexer\OparlIndexerStateStore;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(OparlIndexerStateStore::class)]
class OparlIndexerStateStoreTest extends TestCase
{
    public function testSaveAndLoad(): void
    {
        $store = new OparlIndexerStateStore(new ArrayAdapter());
        $state = new OparlIndexerState(
            new DateTimeImmutable('2026-10-08T12:00:00+02:00'),
            new DateTimeImmutable('2026-10-07T03:00:00+02:00'),
        );

        $store->save('index-oparl-meeting', $state);

        $this->assertEquals($state, $store->load('index-oparl-meeting'));
    }

    public function testLoadUnknown(): void
    {
        $store = new OparlIndexerStateStore(new ArrayAdapter());

        $this->assertEquals(new OparlIndexerState(), $store->load('unknown'));
    }

    public function testLoadInvalid(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('state.key');
        $item->set(['lastRun' => 'invalid', 'lastFullRun' => 42]);
        $cache->save($item);

        $state = (new OparlIndexerStateStore($cache))->load('key');

        $this->assertEquals(new OparlIndexerState(), $state);
    }

    public function testKeysAreSanitized(): void
    {
        $cache = new ArrayAdapter();
        $store = new OparlIndexerStateStore($cache);

        $store->save('a{b}c', new OparlIndexerState());

        $this->assertTrue($cache->hasItem('state.a_b_c'));
    }
}
