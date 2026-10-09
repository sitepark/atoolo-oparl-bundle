<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer\Schema2x;

use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\OparlDocumentEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use SP\OparlClient\V1\Objects\OparlPaper;

/**
 * A paper links to its main file, if it has one; otherwise to its page in
 * the council information system, like every other object.
 *
 * @implements OparlDocumentEnricher<IndexSchema2xDocument>
 */
class PaperSchema2xEnricher implements OparlDocumentEnricher
{
    public function enrichDocument(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
        IndexDocument $doc,
        string $processId,
    ): IndexDocument {
        if (
            !$object instanceof OparlPaper
            || !$doc instanceof IndexSchema2xDocument
        ) {
            return $doc;
        }

        Schema2xFields::linkToFile($doc, $object->getMainFile());
        Schema2xFields::setTitle($doc, $object->getName());
        Schema2xFields::setDates($doc, $object->getDate());
        Schema2xFields::addContent(
            $doc,
            $object->getName(),
            $object->getReference(),
            $object->getPaperType(),
            $object->getMainFile()?->getName(),
        );

        if ($object->getReference() !== null) {
            $doc->setMetaString('oparl_reference', $object->getReference());
        }
        if ($object->getPaperType() !== null) {
            $doc->setMetaString('oparl_paper_type', $object->getPaperType());
            $doc->setMetaString('kicker', $object->getPaperType());
        }
        $mainFileUrl = $object->getMainFile()?->getAccessUrl();
        if ($mainFileUrl !== null) {
            $doc->setMetaString('oparl_main_file_url', $mainFileUrl);
        }

        return $doc;
    }
}
