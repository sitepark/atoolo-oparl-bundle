<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Fixture;

use Atoolo\Index\Dto\Indexer\IndexerStatus;
use Atoolo\Index\Service\Indexer\IndexerProgressHandler;
use Throwable;

final class RecordingProgressHandler implements IndexerProgressHandler
{
    /**
     * @var list<string>
     */
    public array $events = [];

    /**
     * @var list<Throwable>
     */
    public array $errors = [];

    public int $advanced = 0;

    public int $skipped = 0;

    public function prepare(string $message): void
    {
        $this->events[] = 'prepare';
    }

    public function start(int $total): void
    {
        $this->events[] = 'start:' . $total;
    }

    public function startUpdate(int $total): void
    {
        $this->events[] = 'startUpdate:' . $total;
    }

    public function advance(int $step): void
    {
        $this->advanced += $step;
    }

    public function skip(int $step): void
    {
        $this->skipped += $step;
    }

    public function error(Throwable $throwable): void
    {
        $this->errors[] = $throwable;
    }

    public function finish(): void
    {
        $this->events[] = 'finish';
    }

    public function abort(): void
    {
        $this->events[] = 'abort';
    }

    public function getStatus(): IndexerStatus
    {
        return IndexerStatus::empty();
    }
}
