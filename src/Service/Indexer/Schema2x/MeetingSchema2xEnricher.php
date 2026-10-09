<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer\Schema2x;

use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\OparlDocumentEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use SP\OparlClient\V1\Objects\OparlMeeting;
use SP\OparlClient\V1\Objects\OparlObjectV1;

/**
 * @implements OparlDocumentEnricher<IndexSchema2xDocument>
 */
class MeetingSchema2xEnricher implements OparlDocumentEnricher
{
    public function enrichDocument(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
        IndexDocument $doc,
        string $processId,
    ): IndexDocument {
        if (
            !$object instanceof OparlMeeting
            || !$doc instanceof IndexSchema2xDocument
        ) {
            return $doc;
        }

        $location = Schema2xFields::locationText($object->getLocation());

        Schema2xFields::setTitle($doc, $object->getName());
        Schema2xFields::setDates($doc, $object->getStart(), $object->getEnd());
        Schema2xFields::addContent(
            $doc,
            $object->getName(),
            $location,
            implode(' ', Schema2xFields::names($object->getAgendaItem() ?? [])),
        );

        if ($location !== null) {
            $doc->setMetaString('oparl_location', $location);
        }
        if ($object->getMeetingState() !== null) {
            $doc->setMetaString('oparl_meeting_state', $object->getMeetingState());
        }
        $doc->setMetaBool('oparl_cancelled', $object->getCancelled() ?? false);

        return $doc;
    }
}
