<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer\Schema2x;

use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\OparlDocumentEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use SP\OparlClient\V1\Objects\OparlObjectV1;

/**
 * Fields every OParl document needs, independent of its type: identity,
 * the fields of the indexer lifecycle (`sp_source`, `crawl_process_id`)
 * and the group and category of the configuration.
 *
 * `url` points to the page of the object in the council information
 * system (`web`), or to the object in the API if there is none;
 * `contenttype` is set accordingly.
 *
 * @implements OparlDocumentEnricher<IndexSchema2xDocument>
 */
class CommonSchema2xEnricher implements OparlDocumentEnricher
{
    public function enrichDocument(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
        IndexDocument $doc,
        string $processId,
    ): IndexDocument {
        $id = $object->getId();
        if (!$doc instanceof IndexSchema2xDocument || $id === null) {
            return $doc;
        }

        $doc->id = $id;
        // the page in the council information system, otherwise the object
        // in the API
        $web = $object->getWeb();
        $doc->url = $web ?? $id;
        $doc->contenttype = $web !== null
            ? 'text/html; charset=UTF-8'
            : 'application/json; charset=UTF-8';
        $doc->sp_source = [$parameter->source];
        $doc->crawl_process_id = $processId;
        $doc->sp_changed = Schema2xFields::toDateTime(
            $object->getModified() ?? $object->getCreated(),
        );

        $keywords = $object->getKeyword();
        if ($keywords !== null && $keywords !== []) {
            $doc->keywords = $keywords;
        }

        if ($parameter->group !== null) {
            $doc->sp_group = $parameter->group;
        }
        if ($parameter->groupPath !== []) {
            $doc->sp_group_path = $parameter->groupPath;
        }
        // the schema keeps category ids as strings, group ids as numbers
        if ($parameter->category !== []) {
            $doc->sp_category = array_map('strval', $parameter->category);
        }
        if ($parameter->categoryPath !== []) {
            $doc->sp_category_path = array_map(
                'strval',
                $parameter->categoryPath,
            );
        }

        $doc->setMetaString('oparl_id', $id);
        if ($object->getType() !== null) {
            $doc->setMetaString('oparl_type', $object->getType());
        }
        if ($object->getWeb() !== null) {
            $doc->setMetaString('oparl_web', $object->getWeb());
        }

        return $doc;
    }
}
