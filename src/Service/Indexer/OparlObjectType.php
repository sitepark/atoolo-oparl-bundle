<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer;

use SP\OparlClient\Core\OparlList;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\V1\Objects\OparlBody;
use SP\OparlClient\V1\Objects\OparlObjectV1;

/**
 * The OParl object types that can be indexed. Each type is fetched through
 * the body wide list of the same name; one indexer is registered per type.
 *
 * The lists for agenda items and files only exist since OParl 1.1. A
 * server that only speaks 1.0 does not provide them.
 */
enum OparlObjectType: string
{
    case MEETING = 'meeting';
    case PAPER = 'paper';
    case ORGANIZATION = 'organization';
    case PERSON = 'person';
    case AGENDA_ITEM = 'agendaItem';
    case FILE = 'file';

    /**
     * @return OparlReference<OparlList<OparlObjectV1>>|null
     */
    public function listOf(OparlBody $body): ?OparlReference
    {
        /** @var OparlReference<OparlList<OparlObjectV1>>|null $reference */
        $reference = match ($this) {
            self::MEETING => $body->getMeeting(),
            self::PAPER => $body->getPaper(),
            self::ORGANIZATION => $body->getOrganization(),
            self::PERSON => $body->getPerson(),
            self::AGENDA_ITEM => $body->getAgendaItem(),
            self::FILE => $body->getFile(),
        };
        return $reference;
    }

    /**
     * Default source name of the indexer, also the name of the CMS side
     * configuration file `configs/indexer/<source>.php`.
     */
    public function source(): string
    {
        return 'oparl-' . $this->value;
    }
}
