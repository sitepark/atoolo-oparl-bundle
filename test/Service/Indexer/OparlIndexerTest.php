<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer;

use Atoolo\Index\Dto\Indexer\IndexerConfiguration;
use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Index\Service\Indexer\IndexerConfigurationLoader;
use Atoolo\Index\Service\Indexer\IndexingAborter;
use Atoolo\Index\Service\Indexer\PhpLimitIncreaser;
use Atoolo\Index\Service\IndexName;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerState;
use Atoolo\Oparl\Service\Indexer\AcceptAllOparlObjectFilter;
use Atoolo\Oparl\Service\Indexer\OparlDocumentEnricher;
use Atoolo\Oparl\Service\Indexer\OparlObjectFilter;
use Atoolo\Oparl\Service\Indexer\OparlIndexer;
use Atoolo\Oparl\Service\Indexer\OparlIndexerStateStore;
use Atoolo\Oparl\Service\Indexer\OparlListFetcher;
use Atoolo\Oparl\Service\Indexer\OparlObjectType;
use Atoolo\Oparl\Service\Indexer\Schema2x\CommonSchema2xEnricher;
use Atoolo\Oparl\Test\Fixture\FakeOparlServer;
use Atoolo\Oparl\Test\Fixture\InMemoryIndexService;
use Atoolo\Oparl\Test\Fixture\RecordingProgressHandler;
use Atoolo\Resource\DataBag;
use Atoolo\Resource\ResourceLanguage;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SP\OparlClient\OparlClient;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(OparlIndexer::class)]
class OparlIndexerTest extends TestCase
{
    private const SYSTEM = 'https://ris.test/oparl';
    private const BODIES = self::SYSTEM . '/bodies';
    private const BODY = self::SYSTEM . '/body/1';
    private const MEETINGS = self::SYSTEM . '/body/1/meetings';
    private const MEETINGS_PAGE_2 = self::MEETINGS . '?page=2';
    private const KEY = 'test-oparl-meeting';

    private FakeOparlServer $server;

    private InMemoryIndexService $index;

    private RecordingProgressHandler $progress;

    private OparlIndexerStateStore $stateStore;

    private LockFactory $lockFactory;

    private IndexingAborter $aborter;

    private string $workdir;

    protected function setUp(): void
    {
        $this->server = new FakeOparlServer();
        $this->server
            ->json(self::SYSTEM, [
                'id' => self::SYSTEM,
                'type' => 'https://schema.oparl.org/1.1/System',
                'body' => self::BODIES,
            ])
            ->page(self::BODIES, [[
                'id' => self::BODY,
                'type' => 'https://schema.oparl.org/1.1/Body',
                'name' => 'Stadt Teststadt',
                'meeting' => self::MEETINGS,
            ]]);
        $this->index = new InMemoryIndexService();
        $this->progress = new RecordingProgressHandler();
        $this->stateStore = new OparlIndexerStateStore(new ArrayAdapter());
        $this->lockFactory = new LockFactory(new InMemoryStore());
        $this->workdir = sys_get_temp_dir() . '/oparl-indexer-test-'
            . uniqid();
        mkdir($this->workdir);
        $this->aborter = new IndexingAborter($this->workdir, 'indexing');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->workdir . '/*') ?: []);
        rmdir($this->workdir);
    }

    public function testFullRunIndexesAllPages(): void
    {
        $this->server
            ->page(self::MEETINGS, [self::meeting(1), self::meeting(2)], self::MEETINGS_PAGE_2, 3)
            ->page(self::MEETINGS_PAGE_2, [self::meeting(3)], null, 3);

        $this->createIndexer()->index();

        $this->assertSame(
            [
                self::SYSTEM . '/meeting/1',
                self::SYSTEM . '/meeting/2',
                self::SYSTEM . '/meeting/3',
            ],
            array_keys($this->index->documents),
            'all meetings of all pages should be indexed',
        );
        $this->assertSame(
            ['update:3', 'deleteExcludingProcessId:oparl-meeting', 'commit'],
            $this->index->calls,
        );
        $this->assertSame(['prepare', 'start:3', 'finish'], $this->progress->events);
        $this->assertSame([], $this->progress->errors);
    }

    public function testFullRunRemovesDocumentsNotFetchedAnymore(): void
    {
        $this->index->documents['stale'] = self::document('stale', 'old-process');
        $this->server->page(self::MEETINGS, [self::meeting(1)]);

        $this->createIndexer()->index();

        $this->assertSame(
            [self::SYSTEM . '/meeting/1'],
            array_keys($this->index->documents),
        );
    }

    public function testFullRunKeepsDocumentsBelowCleanupThreshold(): void
    {
        $this->index->documents['stale'] = self::document('stale', 'old-process');
        $this->server->page(self::MEETINGS, [self::meeting(1)]);

        $this->createIndexer(['cleanupThreshold' => 2])->index();

        $this->assertArrayHasKey('stale', $this->index->documents);
        $this->assertNotContains(
            'deleteExcludingProcessId:oparl-meeting',
            $this->index->calls,
        );
    }

    public function testFullRunRemembersState(): void
    {
        $this->server->page(self::MEETINGS, [self::meeting(1)]);
        $before = new DateTimeImmutable();

        $this->createIndexer()->index();

        $state = $this->stateStore->load(self::KEY);
        $this->assertNotNull($state->lastRun);
        $this->assertGreaterThanOrEqual(
            $before->getTimestamp(),
            $state->lastRun->getTimestamp(),
        );
        $this->assertEquals($state->lastRun, $state->lastFullRun);
    }

    public function testDeltaRunFetchesModifiedObjectsOnly(): void
    {
        $lastRun = new DateTimeImmutable('-1 hour');
        $this->stateStore->save(self::KEY, new OparlIndexerState($lastRun, $lastRun));
        $this->index->documents['unchanged'] = self::document('unchanged', 'old-process');
        $this->server->page(self::MEETINGS, [self::meeting(1)]);

        $this->createIndexer()->index();

        $meetingRequest = $this->meetingRequest();
        $this->assertStringContainsString('modified_since=', $meetingRequest);
        $this->assertArrayHasKey(
            'unchanged',
            $this->index->documents,
            'a delta run must not delete untouched documents',
        );
        $this->assertArrayHasKey(self::SYSTEM . '/meeting/1', $this->index->documents);
        $this->assertSame(['update:1', 'commit'], $this->index->calls);
        $this->assertSame(['prepare', 'startUpdate:1', 'finish'], $this->progress->events);

        $state = $this->stateStore->load(self::KEY);
        $this->assertSame(
            $lastRun->getTimestamp(),
            $state->lastFullRun?->getTimestamp(),
        );
        $this->assertGreaterThan($lastRun, $state->lastRun);
    }

    public function testDeltaRunSubtractsOverlapFromLastRun(): void
    {
        $lastRun = new DateTimeImmutable('2026-10-08T12:00:00+00:00');
        $this->stateStore->save(
            self::KEY,
            new OparlIndexerState($lastRun, new DateTimeImmutable('-1 hour')),
        );
        $this->server->page(self::MEETINGS, []);

        $this->createIndexer(['modifiedSinceOverlap' => 600])->index();

        $this->assertStringContainsString(
            'modified_since=2026-10-08T11:50:00%2B00:00',
            $this->meetingRequest(),
        );
    }

    public function testRemovesObjectsReportedAsDeleted(): void
    {
        $lastRun = new DateTimeImmutable('-1 hour');
        $this->stateStore->save(self::KEY, new OparlIndexerState($lastRun, $lastRun));
        $deletedId = self::SYSTEM . '/meeting/2';
        $this->index->documents[$deletedId] = self::document($deletedId, 'old-process');
        $this->server->page(self::MEETINGS, [
            self::meeting(1),
            ['id' => $deletedId, 'type' => 'https://schema.oparl.org/1.1/Meeting', 'deleted' => true],
        ]);

        $this->createIndexer()->index();

        $this->assertArrayNotHasKey($deletedId, $this->index->documents);
        $this->assertContains('remove:oparl-meeting:1', $this->index->calls);
        $this->assertSame(1, $this->progress->skipped);
    }

    public function testSkipsRejectedObjects(): void
    {
        $this->server->page(self::MEETINGS, [
            self::meeting(1),
            self::meeting(2) + ['web' => 'https://ris.test/si0057.php?id=2'],
        ]);

        $this->createIndexer([], [], self::webPageFilter())->index();

        $this->assertSame(
            [self::SYSTEM . '/meeting/2'],
            array_keys($this->index->documents),
        );
        $this->assertSame(1, $this->progress->skipped);
    }

    public function testDeltaRunRemovesObjectsRejectedNow(): void
    {
        $lastRun = new DateTimeImmutable('-1 hour');
        $this->stateStore->save(self::KEY, new OparlIndexerState($lastRun, $lastRun));
        $rejectedId = self::SYSTEM . '/meeting/1';
        $this->index->documents[$rejectedId] = self::document($rejectedId, 'old-process');
        $this->server->page(self::MEETINGS, [self::meeting(1)]);

        $this->createIndexer([], [], self::webPageFilter())->index();

        $this->assertArrayNotHasKey($rejectedId, $this->index->documents);
        $this->assertContains('remove:oparl-meeting:1', $this->index->calls);
    }

    public function testFullRunIsDueAfterInterval(): void
    {
        $this->stateStore->save(self::KEY, new OparlIndexerState(
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('-25 hours'),
        ));
        $this->server->page(self::MEETINGS, [self::meeting(1)]);

        $this->createIndexer()->index();

        $this->assertStringNotContainsString('modified_since', $this->meetingRequest());
        $this->assertContains('deleteExcludingProcessId:oparl-meeting', $this->index->calls);
    }

    public function testEveryRunIsFullRunWithoutInterval(): void
    {
        $lastRun = new DateTimeImmutable('-1 hour');
        $this->stateStore->save(self::KEY, new OparlIndexerState($lastRun, $lastRun));
        $this->server->page(self::MEETINGS, [self::meeting(1)]);

        $this->createIndexer(['fullSyncInterval' => 0])->index();

        $this->assertStringNotContainsString('modified_since', $this->meetingRequest());
    }

    public function testSkipsDuplicates(): void
    {
        $this->server
            ->page(self::MEETINGS, [self::meeting(1), self::meeting(2)], self::MEETINGS_PAGE_2)
            ->page(self::MEETINGS_PAGE_2, [self::meeting(2)]);

        $this->createIndexer()->index();

        $this->assertCount(2, $this->index->documents);
        $this->assertSame(1, $this->progress->skipped);
    }

    public function testWritesInChunks(): void
    {
        $this->server->page(
            self::MEETINGS,
            array_map(self::meeting(...), range(1, 25)),
        );

        $this->createIndexer(['chunkSize' => 10])->index();

        $this->assertSame(
            ['update:10', 'update:10', 'update:5', 'deleteExcludingProcessId:oparl-meeting', 'commit'],
            $this->index->calls,
        );
    }

    public function testFetchErrorKeepsStateAndIndex(): void
    {
        $this->index->documents['existing'] = self::document('existing', 'old-process');
        $this->server
            ->page(self::MEETINGS, [self::meeting(1)], self::MEETINGS_PAGE_2)
            ->error(self::MEETINGS_PAGE_2);

        $this->createIndexer()->index();

        $this->assertCount(1, $this->progress->errors);
        $this->assertArrayHasKey(
            'existing',
            $this->index->documents,
            'an incomplete run must not clean up',
        );
        $this->assertNull(
            $this->stateStore->load(self::KEY)->lastRun,
            'an incomplete run must not be remembered',
        );
        $this->assertSame('finish', end($this->progress->events));
    }

    public function testFailedUpdateIsReportedAndRunNotCleanedUp(): void
    {
        $this->index->documents['existing'] = self::document('existing', 'old-process');
        $this->index->failUpdatesWith = 'solr down';
        $this->server->page(self::MEETINGS, [self::meeting(1)]);

        $this->createIndexer()->index();

        $this->assertSame('solr down', $this->progress->errors[0]->getMessage());
        $this->assertArrayHasKey('existing', $this->index->documents);
        $this->assertNull(
            $this->stateStore->load(self::KEY)->lastRun,
            'the next run should fetch the documents again',
        );
    }

    public function testAbortionRequestedBetweenRuns(): void
    {
        $this->server->page(self::MEETINGS, [self::meeting(1)]);
        $indexer = $this->createIndexer();
        $indexer->abort();

        $indexer->index();
        $this->assertSame([], $this->index->documents);

        $indexer->index();
        $this->assertCount(
            1,
            $this->index->documents,
            'a small run should not leave the abortion request behind',
        );
    }

    public function testRunWithoutObjectsResetsAbortionRequest(): void
    {
        $this->server->page(self::MEETINGS, []);
        $indexer = $this->createIndexer();
        $indexer->abort();

        $indexer->index();

        $this->assertNotContains('abort', $this->progress->events);
        $this->assertFileDoesNotExist(
            $this->workdir . '/indexing-' . self::KEY . '.abort',
        );
    }

    public function testInvalidConfigurationWithLimitIncreaser(): void
    {
        $this->createIndexer(
            ['systemUrl' => ''],
            limitIncreaser: new PhpLimitIncreaser(0, '-1'),
        )->index();

        $this->assertCount(1, $this->progress->errors);
        $this->assertTrue(
            $this->lockFactory->createLock('indexer.' . self::KEY)->acquire(),
            'the lock should be released',
        );
    }

    public function testDoesNotPrepareIndex(): void
    {
        $this->server->page(self::MEETINGS, [self::meeting(1)]);

        $this->createIndexer()->index();

        $this->assertSame(
            [],
            preg_grep('/^prepare:/', $this->index->calls),
            'preparing a Solr index deletes the error protocol of all sources',
        );
    }

    public function testFullRunLeavesDeletedObjectsToCleanup(): void
    {
        $this->server->page(self::MEETINGS, [
            self::meeting(1),
            ['id' => self::SYSTEM . '/meeting/2', 'type' => 'https://schema.oparl.org/1.1/Meeting', 'deleted' => true],
        ]);

        $this->createIndexer()->index();

        $this->assertSame(
            ['update:1', 'deleteExcludingProcessId:oparl-meeting', 'commit'],
            $this->index->calls,
        );
    }

    public function testFullRunBelowThresholdRemovesDeletedObjects(): void
    {
        $deletedId = self::SYSTEM . '/meeting/2';
        $this->index->documents[$deletedId] = self::document($deletedId, 'old-process');
        $this->server->page(self::MEETINGS, [
            self::meeting(1),
            ['id' => $deletedId, 'type' => 'https://schema.oparl.org/1.1/Meeting', 'deleted' => true],
        ]);

        $this->createIndexer(['cleanupThreshold' => 5])->index();

        $this->assertArrayNotHasKey($deletedId, $this->index->documents);
        $this->assertContains('remove:oparl-meeting:1', $this->index->calls);
    }

    public function testLoadsConfigurationOnEveryRun(): void
    {
        $this->server->page(self::MEETINGS, [self::meeting(1)]);
        $configLoader = $this->createMock(IndexerConfigurationLoader::class);
        $configLoader->expects($this->exactly(2))
            ->method('load')
            ->willReturn(new IndexerConfiguration(
                'oparl-meeting',
                'Sitzungen',
                new DataBag(['systemUrl' => self::SYSTEM]),
            ));
        $indexer = $this->createIndexer(configLoader: $configLoader);

        $indexer->index();
        $indexer->index();
    }

    public function testAbortion(): void
    {
        $this->server->page(
            self::MEETINGS,
            array_map(self::meeting(...), range(1, 25)),
        );
        $indexer = null;
        $abortingEnricher = new class (function () use (&$indexer): void {
            $indexer?->abort();
        }) implements OparlDocumentEnricher {
            private int $count = 0;

            public function __construct(private readonly \Closure $abort) {}

            public function enrichDocument(
                OparlObjectV1 $object,
                OparlIndexerParameter $parameter,
                IndexDocument $doc,
                string $processId,
            ): IndexDocument {
                if (++$this->count === 5) {
                    ($this->abort)();
                }
                return $doc;
            }
        };
        $indexer = $this->createIndexer(
            ['chunkSize' => 10],
            [new CommonSchema2xEnricher(), $abortingEnricher],
        );

        $indexer->index();

        $this->assertCount(
            5,
            $this->index->documents,
            'the documents before the abortion should be written',
        );
        $this->assertContains('abort', $this->progress->events);
        $this->assertNull($this->stateStore->load(self::KEY)->lastRun);
        $this->assertFileDoesNotExist(
            $this->workdir . '/indexing-' . self::KEY . '.abort',
            'the abortion request should be reset',
        );
    }

    public function testFailingEnricherSkipsOnlyThatObject(): void
    {
        $this->server->page(self::MEETINGS, [self::meeting(1), self::meeting(2)]);
        $failing = new class implements OparlDocumentEnricher {
            public function enrichDocument(
                OparlObjectV1 $object,
                OparlIndexerParameter $parameter,
                IndexDocument $doc,
                string $processId,
            ): IndexDocument {
                if (str_ends_with((string) $object->getId(), '/1')) {
                    throw new RuntimeException('broken');
                }
                return $doc;
            }
        };

        $this->createIndexer([], [new CommonSchema2xEnricher(), $failing])->index();

        $this->assertSame([self::SYSTEM . '/meeting/2'], array_keys($this->index->documents));
        $this->assertCount(1, $this->progress->errors);
        $this->assertStringContainsString('broken', $this->progress->errors[0]->getMessage());
        $this->assertNotNull(
            $this->stateStore->load(self::KEY)->lastRun,
            'a single broken object must not fail the run',
        );
    }

    public function testCountsPageWithoutPagination(): void
    {
        $this->server->json(self::MEETINGS, [
            'data' => [self::meeting(1), self::meeting(2)],
            'links' => [],
        ]);

        $this->createIndexer()->index();

        $this->assertSame('start:2', $this->progress->events[1]);
    }

    public function testFailingCommitAfterErrorIsLogged(): void
    {
        $index = new class extends InMemoryIndexService {
            public function commit(ResourceLanguage $lang): void
            {
                throw new RuntimeException('commit failed');
            }
        };
        $this->index = $index;
        $this->server->page(self::MEETINGS, [self::meeting(1)]);

        $this->createIndexer()->index();

        $this->assertSame(
            ['commit failed'],
            array_map(fn($e) => $e->getMessage(), $this->progress->errors),
        );
        $this->assertSame('finish', end($this->progress->events));
    }

    public function testDoesNotRunTwiceAtTheSameTime(): void
    {
        $lock = $this->lockFactory->createLock('indexer.' . self::KEY);
        $lock->acquire();

        $this->createIndexer()->index();

        $this->assertSame([], $this->server->requests);
        $lock->release();
    }

    public function testLockDoesNotExpire(): void
    {
        $this->server->page(self::MEETINGS, []);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->with('indexer.' . self::KEY, null)
            ->willReturn((new LockFactory(new InMemoryStore()))->createLock('test'));

        $this->createIndexer()->index();
    }

    public function testInvalidConfigurationIsReported(): void
    {
        $this->createIndexer(['systemUrl' => ''])->index();

        $this->assertCount(1, $this->progress->errors);
        $this->assertSame([], $this->server->requests);
    }

    public function testRemove(): void
    {
        $id = self::SYSTEM . '/meeting/1';
        $this->index->documents[$id] = self::document($id, 'process');

        $this->createIndexer()->remove([$id]);

        $this->assertSame([], $this->index->documents);
        $this->assertSame(
            ['remove:oparl-meeting:1', 'commitForAllLanguages'],
            $this->index->calls,
        );
    }

    public function testRemoveNothing(): void
    {
        $this->createIndexer()->remove([]);

        $this->assertSame([], $this->index->calls);
    }

    public function testGetObjectType(): void
    {
        $this->assertSame(
            OparlObjectType::MEETING,
            $this->createIndexer()->getObjectType(),
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param list<OparlDocumentEnricher<IndexDocument>> $enrichers
     */
    private function createIndexer(
        array $config = [],
        array $enrichers = [],
        OparlObjectFilter $filter = new AcceptAllOparlObjectFilter(),
        ?IndexerConfigurationLoader $configLoader = null,
        ?PhpLimitIncreaser $limitIncreaser = null,
    ): OparlIndexer {
        $client = new OparlClient(
            httpClient: $this->server,
            requestFactory: new Psr17Factory(),
        );
        if ($configLoader === null) {
            $configLoader = $this->createStub(IndexerConfigurationLoader::class);
            $configLoader->method('load')->willReturn(new IndexerConfiguration(
                'oparl-meeting',
                'Sitzungen',
                new DataBag($config + [
                    'systemUrl' => self::SYSTEM,
                    'cleanupThreshold' => 1,
                ]),
            ));
        }
        $indexName = $this->createStub(IndexName::class);
        $indexName->method('name')->willReturn('test');

        return new OparlIndexer(
            OparlObjectType::MEETING,
            $enrichers ?: [new CommonSchema2xEnricher()],
            new OparlListFetcher($client),
            $this->index,
            $this->index,
            $filter,
            $this->stateStore,
            $this->progress,
            $this->aborter,
            $configLoader,
            $indexName,
            'oparl-meeting',
            $limitIncreaser,
            lockFactory: $this->lockFactory,
        );
    }

    private static function webPageFilter(): OparlObjectFilter
    {
        return new class implements OparlObjectFilter {
            public function accept(
                OparlObjectV1 $object,
                OparlIndexerParameter $parameter,
            ): bool {
                return $object->getWeb() !== null;
            }
        };
    }

    private function meetingRequest(): string
    {
        foreach ($this->server->requests as $request) {
            if (str_starts_with($request, self::MEETINGS)) {
                return $request;
            }
        }
        $this->fail('meetings were not requested');
    }

    /**
     * @return array<string, mixed>
     */
    private static function meeting(int $number): array
    {
        return [
            'id' => self::SYSTEM . '/meeting/' . $number,
            'type' => 'https://schema.oparl.org/1.1/Meeting',
            'name' => 'Sitzung ' . $number,
            'start' => '2026-10-08T17:00:00+02:00',
        ];
    }

    private static function document(string $id, string $processId): IndexSchema2xDocument
    {
        $doc = new IndexSchema2xDocument();
        $doc->id = $id;
        $doc->sp_source = ['oparl-meeting'];
        $doc->crawl_process_id = $processId;
        return $doc;
    }
}
