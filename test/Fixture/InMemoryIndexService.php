<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Fixture;

use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Index\Service\Indexer\IndexService;
use Atoolo\Index\Service\Indexer\IndexUpdater;
use Atoolo\Index\Service\Indexer\IndexUpdateResult;
use Atoolo\Oparl\Service\Indexer\OparlDocumentRemover;
use Atoolo\Resource\ResourceLanguage;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;

/**
 * Index target keeping its documents in memory, keyed by `id`; also
 * removes them by id.
 */
class InMemoryIndexService implements IndexService, OparlDocumentRemover
{
    /**
     * @var array<string, IndexSchema2xDocument>
     */
    public array $documents = [];

    /**
     * @var list<string>
     */
    public array $calls = [];

    public ?string $failUpdatesWith = null;

    public function getIndex(ResourceLanguage $lang): string
    {
        return 'test';
    }

    public function getManagedIndices(): array
    {
        return ['test'];
    }

    public function updater(ResourceLanguage $lang): IndexUpdater
    {
        $service = $this;
        return new class ($service) implements IndexUpdater {
            /**
             * @var list<IndexSchema2xDocument>
             */
            private array $pending = [];

            public function __construct(
                private readonly InMemoryIndexService $service,
            ) {}

            public function createDocument(): IndexDocument
            {
                return new IndexSchema2xDocument();
            }

            public function addDocument(IndexDocument $document): void
            {
                assert($document instanceof IndexSchema2xDocument);
                $this->pending[] = $document;
            }

            public function clearDocuments(): void
            {
                $this->pending = [];
            }

            public function update(): IndexUpdateResult
            {
                return $this->service->write($this->pending);
            }
        };
    }

    /**
     * @param list<IndexSchema2xDocument> $documents
     */
    public function write(array $documents): IndexUpdateResult
    {
        $this->calls[] = 'update:' . count($documents);
        $error = $this->failUpdatesWith;
        if ($error === null) {
            foreach ($documents as $document) {
                $this->documents[(string) $document->id] = $document;
            }
        }
        return new class ($error) implements IndexUpdateResult {
            public function __construct(private readonly ?string $error) {}

            public function isSuccess(): bool
            {
                return $this->error === null;
            }

            public function getErrorMessage(): ?string
            {
                return $this->error;
            }
        };
    }

    public function prepareIndexing(ResourceLanguage $lang, string $source): void
    {
        $this->calls[] = 'prepare:' . $source;
    }

    public function deleteExcludingProcessId(
        ResourceLanguage $lang,
        string $source,
        string $processId,
    ): void {
        $this->calls[] = 'deleteExcludingProcessId:' . $source;
        foreach ($this->documents as $id => $document) {
            if (
                in_array($source, $document->sp_source ?? [], true)
                && $document->crawl_process_id !== $processId
            ) {
                unset($this->documents[$id]);
            }
        }
    }

    public function deleteByIdListForAllLanguages(
        string $source,
        array $idList,
    ): void {
        $this->calls[] = 'deleteByIdList:' . $source . ':' . count($idList);
    }

    public function remove(string $source, array $oparlIds): void
    {
        $this->calls[] = 'remove:' . $source . ':' . count($oparlIds);
        foreach ($oparlIds as $id) {
            if (in_array($source, $this->documents[$id]->sp_source ?? [], true)) {
                unset($this->documents[$id]);
            }
        }
    }

    public function commit(ResourceLanguage $lang): void
    {
        $this->calls[] = 'commit';
    }

    public function commitForAllLanguages(): void
    {
        $this->calls[] = 'commitForAllLanguages';
    }
}
