<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer\Schema2x;

use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\OparlDocumentEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use SP\OparlClient\V1\Objects\OparlFile;
use SP\OparlClient\V1\Objects\OparlObjectV1;

/**
 * Indexes the metadata of a file and the text the server extracted from it
 * (`text`), if any. The file itself is not downloaded.
 *
 * The document links to the file itself.
 *
 * @implements OparlDocumentEnricher<IndexSchema2xDocument>
 */
class FileSchema2xEnricher implements OparlDocumentEnricher
{
    public function enrichDocument(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
        IndexDocument $doc,
        string $processId,
    ): IndexDocument {
        if (
            !$object instanceof OparlFile
            || !$doc instanceof IndexSchema2xDocument
        ) {
            return $doc;
        }

        Schema2xFields::linkToFile($doc, $object);

        Schema2xFields::setTitle(
            $doc,
            $object->getName() ?? $object->getFileName(),
        );
        Schema2xFields::setDates($doc, $object->getDate());
        Schema2xFields::addContent(
            $doc,
            $object->getName(),
            $object->getFileName(),
            $object->getText(),
        );

        if ($object->getFileName() !== null) {
            $doc->setMetaString('oparl_file_name', $object->getFileName());
        }
        if ($object->getMimeType() !== null) {
            $doc->setMetaString('oparl_mime_type', $object->getMimeType());
        }
        if ($object->getSize() !== null) {
            $doc->setMetaLong('oparl_size', $object->getSize());
        }
        if ($object->getAccessUrl() !== null) {
            $doc->setMetaString('oparl_access_url', $object->getAccessUrl());
        }
        if ($object->getDownloadUrl() !== null) {
            $doc->setMetaString('oparl_download_url', $object->getDownloadUrl());
        }

        return $doc;
    }
}
