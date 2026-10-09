<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer\Schema2x;

use Atoolo\Oparl\Service\Indexer\Schema2x\PersonSchema2xEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\V1\Objects\OparlMeeting;
use SP\OparlClient\V1\Objects\OparlMembership;
use SP\OparlClient\V1\Objects\OparlPerson;

#[CoversClass(PersonSchema2xEnricher::class)]
class PersonSchema2xEnricherTest extends EnricherTestCase
{
    public function testEnrich(): void
    {
        $person = new OparlPerson(
            id: 'https://ris.test/oparl/person/1',
            familyName: 'Muster',
            givenName: 'Erika',
            formOfAddress: 'Frau',
            title: ['Dr.'],
            phone: ['0123 456'],
            email: ['erika@example.org'],
            status: ['Ratsmitglied'],
            membership: [
                new OparlMembership(organization: new OparlReference(self::ORGANIZATION)),
                new OparlMembership(
                    organization: new OparlReference(self::SYSTEM . '/organization/old'),
                    endDate: new DateTimeImmutable('2020-01-01'),
                ),
            ],
        );

        $fields = (new PersonSchema2xEnricher())->enrichDocument(
            $person,
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        )->getFields();

        $this->assertSame('Dr. Erika Muster', $fields['title']);
        $this->assertSame('Muster Erika', $fields['sp_sortvalue']);
        $this->assertSame('M', $fields['sp_startletter']);
        $this->assertSame('Muster', $fields['sp_meta_string_oparl_family_name']);
        $this->assertSame('Erika', $fields['sp_meta_string_oparl_given_name']);
        $this->assertSame('Frau', $fields['sp_meta_string_oparl_form_of_address']);
        $this->assertSame(['Dr.'], $fields['sp_meta_string_oparl_title']);
        $this->assertSame(['Ratsmitglied'], $fields['sp_meta_string_oparl_status']);
        $this->assertStringNotContainsString(
            'erika@example.org',
            json_encode($fields, JSON_THROW_ON_ERROR),
            'contact data must not be indexed',
        );
        $this->assertSame(
            [],
            $this->server->requests,
            'references should not be resolved',
        );
    }

    public function testPrefersName(): void
    {
        $fields = (new PersonSchema2xEnricher())->enrichDocument(
            new OparlPerson(id: 'p1', name: 'Erika Muster', familyName: 'Muster'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        )->getFields();

        $this->assertSame('Erika Muster', $fields['title']);
        $this->assertSame('Muster', $fields['sp_sortvalue']);
    }

    public function testMinimalPerson(): void
    {
        $fields = (new PersonSchema2xEnricher())->enrichDocument(
            new OparlPerson(id: 'p1'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        )->getFields();

        $this->assertArrayNotHasKey('title', $fields);
    }

    public function testIgnoresOtherTypes(): void
    {
        $doc = (new PersonSchema2xEnricher())->enrichDocument(
            new OparlMeeting(id: 'm', name: 'Sitzung'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $this->assertNull($doc->title);
    }
}
