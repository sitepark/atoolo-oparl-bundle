<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SP\OparlClient\Core\OparlException;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\OparlClient;
use SP\OparlClient\V1\Objects\OparlObjectV1;

/**
 * Resolves references to other OParl objects, e.g. the organizations of a
 * meeting, for enrichers that need more than the URL.
 *
 * A reference loaded through {@see OparlReference::get()} always costs a
 * request. The resolver instead loads through a client whose responses are
 * cached (see {@see Http\CachingPsr18Client}) and additionally keeps the
 * objects of the current process in memory, so that an organization
 * referenced by hundreds of meetings is fetched once.
 *
 * A reference that cannot be resolved is logged and yields `null`; a single
 * broken reference must not fail the document.
 */
class OparlReferenceResolver
{
    /**
     * @var array<string, OparlObjectV1|null>
     */
    private array $memory = [];

    /**
     * @param int $maxMemoryEntries
     *    Objects kept in memory; when exceeded the memory is emptied.
     *    0 keeps nothing in memory, every object then comes from the cache.
     */
    public function __construct(
        private readonly OparlClient $client,
        private readonly int $maxMemoryEntries = 2000,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @template T of OparlObjectV1
     * @param OparlReference<T>|null $reference
     * @param class-string<T> $class
     * @return T|null
     */
    public function resolve(
        ?OparlReference $reference,
        string $class,
    ): ?OparlObjectV1 {
        if ($reference === null) {
            return null;
        }
        $uri = $reference->getUri();
        if (array_key_exists($uri, $this->memory)) {
            $object = $this->memory[$uri];
        } else {
            $object = $this->load($uri, $class);
            $this->remember($uri, $object);
        }
        return $object instanceof $class ? $object : null;
    }

    /**
     * @template T of OparlObjectV1
     * @param list<OparlReference<T>>|null $references
     * @param class-string<T> $class
     * @return list<T>
     */
    public function resolveAll(?array $references, string $class): array
    {
        $objects = [];
        foreach ($references ?? [] as $reference) {
            $object = $this->resolve($reference, $class);
            if ($object !== null) {
                $objects[] = $object;
            }
        }
        return $objects;
    }

    private function remember(string $uri, ?OparlObjectV1 $object): void
    {
        if ($this->maxMemoryEntries <= 0) {
            return;
        }
        if (count($this->memory) >= $this->maxMemoryEntries) {
            $this->memory = [];
        }
        $this->memory[$uri] = $object;
    }

    public function reset(): void
    {
        $this->memory = [];
    }

    /**
     * @param class-string<OparlObjectV1> $class
     */
    private function load(string $uri, string $class): ?OparlObjectV1
    {
        try {
            return $this->client->get($uri, $class);
        } catch (OparlException $e) {
            $this->logger->warning(
                'Unable to resolve OParl reference: ' . $e->getMessage(),
                ['uri' => $uri, 'exception' => $e],
            );
            return null;
        }
    }
}
