<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer\Schema2x;

use Atoolo\Oparl\Service\Indexer\Schema2x\MeetingSchema2xEnricher;
use Atoolo\Oparl\Service\Indexer\Schema2x\Schema2xFields;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\V1\Objects\OparlAgendaItem;
use SP\OparlClient\V1\Objects\OparlLocation;
use SP\OparlClient\V1\Objects\OparlMeeting;
use SP\OparlClient\V1\Objects\OparlPaper;

#[CoversClass(MeetingSchema2xEnricher::class)]
#[CoversClass(Schema2xFields::class)]
class MeetingSchema2xEnricherTest extends EnricherTestCase
{
    public function testEnrich(): void
    {
        $meeting = new OparlMeeting(
            id: self::MEETING,
            name: '12. Sitzung des Rates',
            meetingState: 'terminiert',
            cancelled: false,
            start: new DateTimeImmutable('2026-10-08T17:00:00+02:00'),
            end: new DateTimeImmutable('2026-10-08T20:00:00+02:00'),
            location: new OparlLocation(
                description: 'Rathaus',
                room: 'Ratssaal',
                streetAddress: 'Markt 1',
                postalCode: '12345',
                locality: 'Teststadt',
            ),
            organization: [new OparlReference(self::ORGANIZATION)],
            agendaItem: [new OparlAgendaItem(name: 'Haushalt 2027')],
        );

        $doc = (new MeetingSchema2xEnricher())->enrichDocument(
            $meeting,
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $fields = $doc->getFields();
        $location = 'Rathaus, Ratssaal, Markt 1, 12345 Teststadt';
        $this->assertSame('12. Sitzung des Rates', $fields['title']);
        $this->assertSame('12. Sitzung des Rates', $fields['sp_sortvalue']);
        $this->assertSame('1', $fields['sp_startletter']);
        $this->assertEquals(new DateTime('2026-10-08T17:00:00+02:00'), $fields['sp_date_from']);
        $this->assertEquals(new DateTime('2026-10-08T20:00:00+02:00'), $fields['sp_date_to']);
        $this->assertSame(
            '12. Sitzung des Rates ' . $location . ' Haushalt 2027',
            $fields['content'],
        );
        $this->assertSame(
            [],
            $this->server->requests,
            'references should not be resolved',
        );
        $this->assertSame($location, $fields['sp_meta_string_oparl_location']);
        $this->assertSame('terminiert', $fields['sp_meta_string_oparl_meeting_state']);
        $this->assertFalse($fields['sp_meta_bool_oparl_cancelled']);
    }

    public function testEndBeforeStart(): void
    {
        $doc = (new MeetingSchema2xEnricher())->enrichDocument(
            new OparlMeeting(
                id: self::MEETING,
                start: new DateTimeImmutable('2027-11-23T16:00:00+01:00'),
                end: new DateTimeImmutable('2027-11-23T00:00:00+01:00'),
            ),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $this->assertEquals(
            new DateTime('2027-11-23T16:00:00+01:00'),
            $doc->sp_date_to,
            'an end before the start should not be indexed',
        );
    }

    public function testMinimalMeeting(): void
    {
        $doc = (new MeetingSchema2xEnricher())->enrichDocument(
            new OparlMeeting(id: self::MEETING),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $fields = $doc->getFields();
        $this->assertArrayNotHasKey('title', $fields);
        $this->assertArrayNotHasKey('sp_date', $fields);
        $this->assertArrayNotHasKey('description', $fields);
        $this->assertArrayNotHasKey('content', $fields);
    }

    public function testIgnoresOtherTypes(): void
    {
        $doc = (new MeetingSchema2xEnricher())->enrichDocument(
            new OparlPaper(id: 'p', name: 'Vorlage'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $this->assertNull($doc->title);
    }
}
