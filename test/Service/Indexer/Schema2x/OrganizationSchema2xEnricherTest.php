<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer\Schema2x;

use Atoolo\Oparl\Service\Indexer\Schema2x\OrganizationSchema2xEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use SP\OparlClient\V1\Objects\OparlMeeting;
use SP\OparlClient\V1\Objects\OparlOrganization;

#[CoversClass(OrganizationSchema2xEnricher::class)]
class OrganizationSchema2xEnricherTest extends EnricherTestCase
{
    public function testEnrich(): void
    {
        $organization = new OparlOrganization(
            id: self::ORGANIZATION,
            name: 'Ausschuss für Finanzen',
            shortName: 'FA',
            post: ['Vorsitz', 'Mitglied'],
            organizationType: 'Gremium',
            classification: 'Ausschuss',
            startDate: new DateTimeImmutable('2024-11-01'),
            website: 'https://teststadt.de/fa',
        );

        $fields = (new OrganizationSchema2xEnricher())->enrichDocument(
            $organization,
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        )->getFields();

        $this->assertSame('Ausschuss für Finanzen', $fields['title']);
        $this->assertEquals(new DateTime('2024-11-01'), $fields['sp_date_from']);
        $this->assertSame('Ausschuss für Finanzen FA Ausschuss Vorsitz Mitglied', $fields['content']);
        $this->assertSame('FA', $fields['sp_meta_string_oparl_short_name']);
        $this->assertSame('Gremium', $fields['sp_meta_string_oparl_organization_type']);
        $this->assertSame('Ausschuss', $fields['sp_meta_string_oparl_classification']);
        $this->assertSame('https://teststadt.de/fa', $fields['sp_meta_string_oparl_website']);
    }

    public function testMinimalOrganization(): void
    {
        $fields = (new OrganizationSchema2xEnricher())->enrichDocument(
            new OparlOrganization(id: self::ORGANIZATION),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        )->getFields();

        $this->assertArrayNotHasKey('sp_meta_string_oparl_short_name', $fields);
        $this->assertArrayNotHasKey('sp_meta_string_oparl_website', $fields);
    }

    public function testIgnoresOtherTypes(): void
    {
        $doc = (new OrganizationSchema2xEnricher())->enrichDocument(
            new OparlMeeting(id: 'm', name: 'Sitzung'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $this->assertNull($doc->title);
    }
}
