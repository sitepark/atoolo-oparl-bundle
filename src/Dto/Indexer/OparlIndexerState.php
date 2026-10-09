<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Dto\Indexer;

use DateInterval;
use DateTimeImmutable;

/**
 * Start times of the last successful run and of the last successful full
 * run of an indexer. Start times, not end times: an object modified while a
 * run was in progress must be fetched again by the next one.
 */
final class OparlIndexerState
{
    public function __construct(
        public readonly ?DateTimeImmutable $lastRun = null,
        public readonly ?DateTimeImmutable $lastFullRun = null,
    ) {}

    /**
     * A full run is due if there was none yet, or if the last one is longer
     * ago than `$fullSyncInterval` hours. With an interval of 0 every run is
     * a full run.
     */
    public function isFullRunDue(
        DateTimeImmutable $now,
        int $fullSyncInterval,
    ): bool {
        if ($fullSyncInterval <= 0 || $this->lastRun === null) {
            return true;
        }
        if ($this->lastFullRun === null) {
            return true;
        }
        $nextFullRun = $this->lastFullRun->add(
            new DateInterval('PT' . $fullSyncInterval . 'H'),
        );
        return $nextFullRun <= $now;
    }
}
