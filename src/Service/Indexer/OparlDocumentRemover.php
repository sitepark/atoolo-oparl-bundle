<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer;

/**
 * Deletes single documents by their OParl id.
 *
 * The {@see \Atoolo\Index\Service\Indexer\IndexService} port only deletes by
 * `sp_id`, the id of a CMS resource, which OParl documents do not have. The
 * documents are identified by their `id`, the OParl URL, instead; how that
 * is done is up to the target.
 */
interface OparlDocumentRemover
{
    /**
     * Deletes the documents of the given OParl ids of a source from all
     * indices. Committing is left to the caller.
     *
     * @param string[] $oparlIds
     */
    public function remove(string $source, array $oparlIds): void;
}
