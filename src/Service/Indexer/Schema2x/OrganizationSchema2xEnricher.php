<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer\Schema2x;

use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\OparlDocumentEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use SP\OparlClient\V1\Objects\OparlOrganization;

/**
 * @implements OparlDocumentEnricher<IndexSchema2xDocument>
 */
class OrganizationSchema2xEnricher implements OparlDocumentEnricher
{
    public function enrichDocument(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
        IndexDocument $doc,
        string $processId,
    ): IndexDocument {
        if (
            !$object instanceof OparlOrganization
            || !$doc instanceof IndexSchema2xDocument
        ) {
            return $doc;
        }

        Schema2xFields::setTitle($doc, $object->getName());
        Schema2xFields::setDates(
            $doc,
            $object->getStartDate(),
            $object->getEndDate(),
        );
        Schema2xFields::addContent(
            $doc,
            $object->getName(),
            $object->getShortName(),
            $object->getClassification(),
            implode(' ', $object->getPost() ?? []),
        );

        if ($object->getShortName() !== null) {
            $doc->setMetaString('oparl_short_name', $object->getShortName());
        }
        if ($object->getOrganizationType() !== null) {
            $doc->setMetaString(
                'oparl_organization_type',
                $object->getOrganizationType(),
            );
        }
        if ($object->getClassification() !== null) {
            $doc->setMetaString(
                'oparl_classification',
                $object->getClassification(),
            );
        }
        if ($object->getWebsite() !== null) {
            $doc->setMetaString('oparl_website', $object->getWebsite());
        }

        return $doc;
    }
}
