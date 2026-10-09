<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer;

use Atoolo\Oparl\Dto\Indexer\OparlIndexerState;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Remembers when an indexer last finished successfully, which decides
 * whether the next run fetches everything or only what was modified since.
 *
 * Losing the state is harmless: the next run is a full run.
 */
class OparlIndexerStateStore
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {}

    public function load(string $key): OparlIndexerState
    {
        $item = $this->cache->getItem($this->cacheKey($key));
        $value = $item->isHit() ? $item->get() : null;
        if (!is_array($value)) {
            return new OparlIndexerState();
        }
        return new OparlIndexerState(
            self::parseDate($value['lastRun'] ?? null),
            self::parseDate($value['lastFullRun'] ?? null),
        );
    }

    public function save(string $key, OparlIndexerState $state): void
    {
        $item = $this->cache->getItem($this->cacheKey($key));
        $item->set([
            'lastRun' => $state->lastRun?->format(DateTimeInterface::ATOM),
            'lastFullRun' => $state->lastFullRun?->format(
                DateTimeInterface::ATOM,
            ),
        ]);
        $this->cache->save($item);
    }

    private function cacheKey(string $key): string
    {
        return 'state.' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $key);
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ATOM,
            $value,
        );
        return $date === false ? null : $date;
    }
}
