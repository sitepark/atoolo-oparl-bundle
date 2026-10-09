<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer;

use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\Query\Modified;
use SP\OparlClient\Core\Query\OmitInternal;
use SP\OparlClient\OparlClient;
use SP\OparlClient\V1\Objects\OparlBody;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use SP\OparlClient\V1\Objects\OparlSystem;

/**
 * Opens the body wide lists of one OParl type. Only the first page of each
 * list is fetched here; its pagination tells the indexer how many objects
 * to expect, the remaining pages are fetched lazily by
 * {@see OparlList::all()}.
 */
class OparlListFetcher
{
    public function __construct(
        private readonly OparlClient $client,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @return list<OparlList<OparlObjectV1>>
     */
    public function openLists(
        OparlIndexerParameter $parameter,
        OparlType $type,
        ?DateTimeInterface $modifiedSince,
    ): array {
        $lists = [];
        foreach ($this->bodies($parameter) as $body) {
            $reference = $type->listOf($body);
            if ($reference === null) {
                $this->logger->warning(
                    'OParl body provides no list of type ' . $type->value,
                    ['body' => $body->getId()],
                );
                continue;
            }
            $lists[] = $reference->withQueryParams(
                $modifiedSince !== null
                    ? Modified::since($modifiedSince)
                    : null,
                $parameter->omitInternal ? OmitInternal::true() : null,
            )->get();
        }
        return $lists;
    }

    /**
     * @return iterable<OparlBody>
     */
    private function bodies(OparlIndexerParameter $parameter): iterable
    {
        if ($parameter->bodyUrls !== []) {
            foreach ($parameter->bodyUrls as $bodyUrl) {
                yield $this->client->get($bodyUrl, OparlBody::class);
            }
            return;
        }

        $system = $this->client->get(
            $parameter->systemUrl,
            OparlSystem::class,
        );
        $bodies = $system->getBody();
        if ($bodies === null) {
            $this->logger->warning(
                'OParl system provides no bodies',
                ['system' => $parameter->systemUrl],
            );
            return;
        }
        yield from $bodies->get()->all();
    }
}
