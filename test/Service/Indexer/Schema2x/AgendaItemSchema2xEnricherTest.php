<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer\Schema2x;

use Atoolo\Oparl\Service\Indexer\Schema2x\AgendaItemSchema2xEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\V1\Objects\OparlAgendaItem;
use SP\OparlClient\V1\Objects\OparlMeeting;

#[CoversClass(AgendaItemSchema2xEnricher::class)]
class AgendaItemSchema2xEnricherTest extends EnricherTestCase
{
    public function testEnrich(): void
    {
        $agendaItem = new OparlAgendaItem(
            id: 'https://ris.test/oparl/agendaItem/1',
            meeting: new OparlReference(self::MEETING),
            number: 'Ö 3.1',
            name: 'Haushalt 2027',
            public: true,
            result: 'beschlossen',
            resolutionText: 'Der Rat beschließt den Haushalt.',
        );

        $fields = (new AgendaItemSchema2xEnricher())->enrichDocument(
            $agendaItem,
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        )->getFields();

        $this->assertSame('Ö 3.1 Haushalt 2027', $fields['title']);
        $this->assertArrayNotHasKey(
            'sp_date',
            $fields,
            'the date of the meeting is not resolved',
        );
        $this->assertSame(
            'Haushalt 2027 beschlossen Der Rat beschließt den Haushalt.',
            $fields['content'],
        );
        $this->assertSame('Ö 3.1', $fields['sp_meta_string_oparl_number']);
        $this->assertSame('beschlossen', $fields['sp_meta_string_oparl_result']);
        $this->assertTrue($fields['sp_meta_bool_oparl_public']);
        $this->assertSame(
            [],
            $this->server->requests,
            'references should not be resolved',
        );
        $this->assertSame(self::MEETING, $fields['sp_meta_string_oparl_meeting_id']);
    }

    public function testMinimalAgendaItem(): void
    {
        $fields = (new AgendaItemSchema2xEnricher())->enrichDocument(
            new OparlAgendaItem(id: 'a1'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        )->getFields();

        $this->assertArrayNotHasKey('sp_date', $fields);
        $this->assertArrayNotHasKey('sp_meta_bool_oparl_public', $fields);
    }

    public function testIgnoresOtherTypes(): void
    {
        $doc = (new AgendaItemSchema2xEnricher())->enrichDocument(
            new OparlMeeting(id: 'm', name: 'Sitzung'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $this->assertNull($doc->title);
    }
}
