<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer\Schema2x;

use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\OparlDocumentEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use SP\OparlClient\V1\Objects\OparlPerson;

/**
 * Indexes name, titles and status.
 * Contact data (phone, email, address) is deliberately left out; a project
 * that wants it in the index adds an enricher of its own.
 *
 * @implements OparlDocumentEnricher<IndexSchema2xDocument>
 */
class PersonSchema2xEnricher implements OparlDocumentEnricher
{
    public function enrichDocument(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
        IndexDocument $doc,
        string $processId,
    ): IndexDocument {
        if (
            !$object instanceof OparlPerson
            || !$doc instanceof IndexSchema2xDocument
        ) {
            return $doc;
        }

        $name = $object->getName() ?? Schema2xFields::join(
            ' ',
            implode(' ', $object->getTitle() ?? []),
            $object->getGivenName(),
            $object->getFamilyName(),
        );
        $sortValue = Schema2xFields::join(
            ' ',
            $object->getFamilyName(),
            $object->getGivenName(),
        );

        Schema2xFields::setTitle($doc, $name, $sortValue);
        Schema2xFields::addContent(
            $doc,
            $name,
            implode(' ', $object->getStatus() ?? []),
        );

        if ($object->getFamilyName() !== null) {
            $doc->setMetaString('oparl_family_name', $object->getFamilyName());
        }
        if ($object->getGivenName() !== null) {
            $doc->setMetaString('oparl_given_name', $object->getGivenName());
        }
        if ($object->getFormOfAddress() !== null) {
            $doc->setMetaString(
                'oparl_form_of_address',
                $object->getFormOfAddress(),
            );
        }
        if (($object->getTitle() ?? []) !== []) {
            $doc->setMetaString('oparl_title', $object->getTitle() ?? []);
        }
        if (($object->getStatus() ?? []) !== []) {
            $doc->setMetaString('oparl_status', $object->getStatus() ?? []);
        }

        return $doc;
    }
}
