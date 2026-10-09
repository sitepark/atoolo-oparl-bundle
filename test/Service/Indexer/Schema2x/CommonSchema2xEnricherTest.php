<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer\Schema2x;

use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\Schema2x\CommonSchema2xEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use SP\OparlClient\V1\Objects\OparlMeeting;

#[CoversClass(CommonSchema2xEnricher::class)]
class CommonSchema2xEnricherTest extends EnricherTestCase
{
    public function testEnrich(): void
    {
        $meeting = new OparlMeeting(
            id: self::MEETING,
            type: 'https://schema.oparl.org/1.1/Meeting',
            web: 'https://ris.test/si0057.php?id=1',
            modified: new DateTimeImmutable('2026-10-01T10:00:00+02:00'),
            keyword: ['haushalt'],
        );
        $parameter = new OparlIndexerParameter(
            'oparl-meeting',
            'Sitzungen',
            self::SYSTEM,
            group: 12,
            groupPath: [1, 12],
            category: [7],
            categoryPath: [3, 7],
        );

        $doc = (new CommonSchema2xEnricher())->enrichDocument(
            $meeting,
            $parameter,
            new IndexSchema2xDocument(),
            'process-1',
        );

        $fields = $doc->getFields();
        $this->assertSame(self::MEETING, $fields['id']);
        $this->assertArrayNotHasKey(
            'sp_id',
            $fields,
            'sp_id is the id of a CMS resource',
        );
        $this->assertSame('https://ris.test/si0057.php?id=1', $fields['url']);
        $this->assertSame('text/html; charset=UTF-8', $fields['contenttype']);
        $this->assertSame(['oparl-meeting'], $fields['sp_source']);
        $this->assertSame('process-1', $fields['crawl_process_id']);
        $this->assertArrayNotHasKey(
            'sp_objecttype',
            $fields,
            'sp_objecttype is the object type of a CMS resource',
        );
        $this->assertSame(['haushalt'], $fields['keywords']);
        $this->assertSame(12, $fields['sp_group']);
        $this->assertSame([1, 12], $fields['sp_group_path']);
        $this->assertSame(['7'], $fields['sp_category']);
        $this->assertSame(['3', '7'], $fields['sp_category_path']);
        $this->assertEquals(new \DateTime('2026-10-01T10:00:00+02:00'), $fields['sp_changed']);
        $this->assertSame(self::MEETING, $fields['sp_meta_string_oparl_id']);
        $this->assertSame('https://schema.oparl.org/1.1/Meeting', $fields['sp_meta_string_oparl_type']);
        $this->assertSame('https://ris.test/si0057.php?id=1', $fields['sp_meta_string_oparl_web']);
    }

    public function testUrlFallsBackToId(): void
    {
        $doc = (new CommonSchema2xEnricher())->enrichDocument(
            new OparlMeeting(id: self::MEETING),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $this->assertSame(self::MEETING, $doc->url);
        $this->assertSame('application/json; charset=UTF-8', $doc->contenttype);
    }

    public function testIgnoresObjectsWithoutId(): void
    {
        $doc = (new CommonSchema2xEnricher())->enrichDocument(
            new OparlMeeting(),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $this->assertNull($doc->id);
    }

    public function testIgnoresOtherDocuments(): void
    {
        $doc = $this->createStub(IndexDocument::class);

        $this->assertSame($doc, (new CommonSchema2xEnricher())->enrichDocument(
            new OparlMeeting(id: self::MEETING),
            self::parameter(),
            $doc,
            'p',
        ));
    }
}
