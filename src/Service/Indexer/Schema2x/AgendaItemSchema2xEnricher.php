<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer\Schema2x;

use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\OparlDocumentEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use SP\OparlClient\V1\Objects\OparlAgendaItem;
use SP\OparlClient\V1\Objects\OparlObjectV1;

/**
 * Most systems give agenda items no date of their own; the document has
 * none then. The meeting is referenced by `oparl_meeting_id`.
 *
 * @implements OparlDocumentEnricher<IndexSchema2xDocument>
 */
class AgendaItemSchema2xEnricher implements OparlDocumentEnricher
{
    public function enrichDocument(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
        IndexDocument $doc,
        string $processId,
    ): IndexDocument {
        if (
            !$object instanceof OparlAgendaItem
            || !$doc instanceof IndexSchema2xDocument
        ) {
            return $doc;
        }

        Schema2xFields::setTitle($doc, Schema2xFields::join(
            ' ',
            $object->getNumber(),
            $object->getName(),
        ));
        Schema2xFields::setDates(
            $doc,
            $object->getStart(),
            $object->getEnd(),
        );
        Schema2xFields::addContent(
            $doc,
            $object->getName(),
            $object->getResult(),
            $object->getResolutionText(),
        );

        if ($object->getNumber() !== null) {
            $doc->setMetaString('oparl_number', $object->getNumber());
        }
        if ($object->getResult() !== null) {
            $doc->setMetaString('oparl_result', $object->getResult());
        }
        if ($object->getPublic() !== null) {
            $doc->setMetaBool('oparl_public', $object->getPublic());
        }
        if ($object->getMeeting() !== null) {
            $doc->setMetaString(
                'oparl_meeting_id',
                $object->getMeeting()->getUri(),
            );
        }

        return $doc;
    }
}
