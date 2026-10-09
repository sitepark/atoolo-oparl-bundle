<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer;

use Atoolo\Index\Dto\Indexer\IndexerStatus;
use Atoolo\Index\Service\AbstractIndexer;
use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Index\Service\Indexer\IndexerConfigurationLoader;
use Atoolo\Index\Service\Indexer\IndexerProgressHandler;
use Atoolo\Index\Service\Indexer\IndexingAborter;
use Atoolo\Index\Service\Indexer\IndexService;
use Atoolo\Index\Service\Indexer\IndexUpdater;
use Atoolo\Index\Service\Indexer\PhpLimitIncreaser;
use Atoolo\Index\Service\IndexName;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerState;
use Atoolo\Resource\ResourceLanguage;
use DateInterval;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SP\OparlClient\Core\OparlList;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Throwable;

/**
 * Indexes the objects of one OParl type - meetings, papers, ... -
 * of all configured bodies. One instance is registered per type, each with
 * a source of its own (`oparl-meeting`, `oparl-paper`, ...), so that every
 * type has its own status, schedule and cleanup.
 *
 * A run is either
 *
 * - a **full run**, fetching every object. Documents of the source that
 *   were not written by the run are deleted afterwards, provided at least
 *   `cleanupThreshold` documents were written - a server that returns an
 *   empty list by mistake must not empty the index.
 * - a **delta run**, fetching only what was modified since the last
 *   successful run (`modified_since`). Objects the server reports as
 *   deleted are removed from the index. Nothing else is deleted, as the
 *   untouched documents are still current.
 *
 * A full run is done when there was none for `fullSyncInterval` hours.
 * The time of a run is only remembered if the run completed and every
 * update succeeded; after an error or an abortion the next run starts over
 * from the same point, and a full run with a failed update cleans nothing
 * up.
 *
 * The indexer is target agnostic: documents are created by the
 * {@see IndexService} of the target and filled by the enrichers of that
 * target.
 */
class OparlIndexer extends AbstractIndexer
{
    private readonly LockFactory $lockFactory;

    /**
     * @param iterable<OparlDocumentEnricher<IndexDocument>> $documentEnricherList
     */
    public function __construct(
        private readonly OparlType $oparlType,
        private readonly iterable $documentEnricherList,
        private readonly OparlListFetcher $fetcher,
        private readonly IndexService $indexService,
        private readonly OparlDocumentRemover $documentRemover,
        private readonly OparlObjectFilter $objectFilter,
        private readonly OparlIndexerStateStore $stateStore,
        IndexerProgressHandler $progressHandler,
        IndexingAborter $aborter,
        IndexerConfigurationLoader $configLoader,
        IndexName $indexName,
        string $source,
        private readonly ?PhpLimitIncreaser $limitIncreaser = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        ?LockFactory $lockFactory = null,
    ) {
        parent::__construct(
            $indexName,
            $progressHandler,
            $aborter,
            $configLoader,
            $source,
        );
        $this->lockFactory = $lockFactory
            ?? new LockFactory(new SemaphoreStore());
    }

    public function getOparlType(): OparlType
    {
        return $this->oparlType;
    }

    /**
     * @param string[] $idList OParl ids (URLs)
     */
    public function remove(array $idList): void
    {
        if ($idList === []) {
            return;
        }
        $this->documentRemover->remove($this->source, $idList);
        $this->indexService->commitForAllLanguages();
    }

    public function index(): IndexerStatus
    {
        // the scheduler of the index bundle runs every scheduled source, also
        // in installations whose CMS does not configure it
        if (!$this->enabled()) {
            $this->logger->info('Indexer is not configured, skipped', [
                'index' => $this->getKey(),
            ]);
            return $this->progressHandler->getStatus();
        }

        // no expiry: a run may take longer than any fixed time to live
        $lock = $this->lockFactory->createLock(
            'indexer.' . $this->getKey(),
            null,
        );
        if (!$lock->acquire()) {
            $this->logger->notice('Indexer is already running', [
                'index' => $this->getKey(),
            ]);
            return $this->progressHandler->getStatus();
        }

        $startedAt = $this->now();
        $lang = ResourceLanguage::default();
        $written = false;
        // before the try block: reset() in finally needs the saved limits
        $this->limitIncreaser?->increase();

        try {
            // loaded on every run, the indexer lives as long as the worker
            $parameter = OparlIndexerParameter::fromConfiguration(
                $this->configLoader->load($this->source),
            );
            $state = $this->stateStore->load($this->getKey());
            $fullRun = $state->isFullRunDue(
                $startedAt,
                $parameter->fullSyncInterval,
            );
            $modifiedSince = $fullRun
                ? null
                : $this->modifiedSince($state, $parameter);

            $this->logger->info('Start indexing', [
                'index' => $this->getKey(),
                'type' => $this->oparlType->value,
                'fullRun' => $fullRun,
                'modifiedSince' => $modifiedSince?->format(DATE_ATOM),
            ]);
            $this->progressHandler->prepare(
                'Fetch OParl ' . $this->oparlType->value . ' lists',
            );

            $lists = $this->fetcher->openLists(
                $parameter,
                $this->oparlType,
                $modifiedSince,
            );

            // IndexService::prepareIndexing() is not called: for Solr it
            // deletes the error protocol of the whole index, which OParl
            // documents are not part of
            if ($fullRun) {
                $this->progressHandler->start($this->countTotal($lists));
            } else {
                $this->progressHandler->startUpdate(
                    $this->countTotal($lists),
                );
            }

            $processId = uniqid('', true);
            $written = true;
            $result = $this->indexLists($parameter, $lists, $lang, $processId);
            if ($result === null) {
                $this->indexService->commit($lang);
                return $this->progressHandler->getStatus();
            }
            [$successCount, $deletedIds, $complete] = $result;

            // a failed update must neither remove the previous versions of
            // its documents nor let the next delta run skip them
            $cleanup = $complete
                && $fullRun
                && $parameter->cleanupThreshold > 0
                && $successCount >= $parameter->cleanupThreshold;
            if ($cleanup) {
                $this->indexService->deleteExcludingProcessId(
                    $lang,
                    $this->source,
                    $processId,
                );
            } elseif ($deletedIds !== []) {
                // the cleanup removes them otherwise
                $this->documentRemover->remove($this->source, $deletedIds);
            }
            $this->indexService->commit($lang);

            if ($complete) {
                $this->stateStore->save($this->getKey(), new OparlIndexerState(
                    $startedAt,
                    $fullRun ? $startedAt : $state->lastFullRun,
                ));
            }
        } catch (Throwable $e) {
            $this->handleError($e);
            if ($written) {
                $this->commitQuietly($lang);
            }
        } finally {
            gc_collect_cycles();
            $this->limitIncreaser?->reset();
            $lock->release();
            $this->progressHandler->finish();
        }

        return $this->progressHandler->getStatus();
    }

    protected function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    /**
     * @param list<OparlList<OparlObjectV1>> $lists
     * @return array{int, list<string>, bool}|null
     *    The number of documents written, the OParl ids of the objects
     *    reported as deleted or rejected by the {@see OparlObjectFilter} and
     *    whether all updates succeeded; `null` if the run was aborted.
     */
    private function indexLists(
        OparlIndexerParameter $parameter,
        array $lists,
        ResourceLanguage $lang,
        string $processId,
    ): ?array {
        $updater = $this->indexService->updater($lang);
        $pending = 0;
        $successCount = 0;
        $complete = true;
        $deletedIds = [];
        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($lists as $list) {
            foreach ($list->all() as $object) {
                if ($this->isAbortionRequested()) {
                    $this->aborter->resetAbortionRequest($this->getKey());
                    if ($pending > 0) {
                        $this->flush($updater);
                    }
                    $this->progressHandler->abort();
                    return null;
                }

                $id = $object->getId();
                // lists may contain an object twice when the server
                // shifts its pages during a run
                if ($id === null || isset($seen[$id])) {
                    $this->progressHandler->skip(1);
                    continue;
                }
                $seen[$id] = true;

                // a rejected object may have been indexed before
                if (
                    $object->isDeleted()
                    || !$this->objectFilter->accept($object, $parameter)
                ) {
                    $deletedIds[] = $id;
                    $this->progressHandler->skip(1);
                    continue;
                }

                if ($this->addDocument($updater, $object, $parameter, $processId)) {
                    $pending++;
                }
                $this->progressHandler->advance(1);

                if ($pending >= $parameter->chunkSize) {
                    if ($this->flush($updater)) {
                        $successCount += $pending;
                    } else {
                        $complete = false;
                    }
                    $updater = $this->indexService->updater($lang);
                    $pending = 0;
                    gc_collect_cycles();
                }
            }
        }

        if ($pending > 0) {
            if ($this->flush($updater)) {
                $successCount += $pending;
            } else {
                $complete = false;
            }
        }

        // a request that came too late for this run, e.g. for a run without
        // any object, must not abort the next one
        if ($this->isAbortionRequested()) {
            $this->aborter->resetAbortionRequest($this->getKey());
        }

        return [$successCount, $deletedIds, $complete];
    }

    private function addDocument(
        IndexUpdater $updater,
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
        string $processId,
    ): bool {
        try {
            $doc = $updater->createDocument();
            foreach ($this->documentEnricherList as $enricher) {
                $doc = $enricher->enrichDocument(
                    $object,
                    $parameter,
                    $doc,
                    $processId,
                );
            }
            $updater->addDocument($doc);
            return true;
        } catch (Throwable $e) {
            $this->handleError(new Exception(
                'Unable to index ' . ($object->getId() ?? '?')
                . ': ' . $e->getMessage(),
                0,
                $e,
            ));
            return false;
        }
    }

    private function flush(IndexUpdater $updater): bool
    {
        $result = $updater->update();
        if (!$result->isSuccess()) {
            $this->handleError(
                $result->getErrorMessage() ?? 'Unknown index error',
            );
            return false;
        }
        return true;
    }

    /**
     * @param list<OparlList<OparlObjectV1>> $lists
     */
    private function countTotal(array $lists): int
    {
        $total = 0;
        foreach ($lists as $list) {
            $total += $list->getPagination()->getTotalElements()
                ?? count($list->getData());
        }
        return $total;
    }

    private function modifiedSince(
        OparlIndexerState $state,
        OparlIndexerParameter $parameter,
    ): ?DateTimeImmutable {
        return $state->lastRun?->sub(new DateInterval(
            'PT' . max(0, $parameter->modifiedSinceOverlap) . 'S',
        ));
    }

    private function commitQuietly(ResourceLanguage $lang): void
    {
        try {
            $this->indexService->commit($lang);
        } catch (Throwable $e) {
            $this->logger->error('Commit failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    private function handleError(Throwable|string $error): void
    {
        if (is_string($error)) {
            $error = new Exception($error);
        }
        $this->progressHandler->error($error);
        $this->logger->error($error->getMessage(), [
            'index' => $this->getKey(),
            'exception' => $error,
        ]);
    }
}
