<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer\Schema2x;

use Atoolo\Oparl\Service\Indexer\Schema2x\PaperSchema2xEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\V1\Objects\OparlFile;
use SP\OparlClient\V1\Objects\OparlMeeting;
use SP\OparlClient\V1\Objects\OparlPaper;

#[CoversClass(PaperSchema2xEnricher::class)]
class PaperSchema2xEnricherTest extends EnricherTestCase
{
    public function testEnrich(): void
    {
        $paper = new OparlPaper(
            id: 'https://ris.test/oparl/paper/1',
            name: 'Haushaltssatzung 2027',
            reference: 'V/2026/0815',
            date: new DateTimeImmutable('2026-09-30'),
            paperType: 'Beschlussvorlage',
            mainFile: new OparlFile(
                name: 'Vorlage',
                mimeType: 'application/pdf',
                accessUrl: 'https://ris.test/files/1.pdf',
            ),
            underDirectionOf: [new OparlReference(self::ORGANIZATION)],
            originatorOrganization: [new OparlReference(self::ORGANIZATION)],
        );

        $doc = (new PaperSchema2xEnricher())->enrichDocument(
            $paper,
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $fields = $doc->getFields();
        $this->assertSame('https://ris.test/files/1.pdf', $fields['url']);
        $this->assertSame('application/pdf', $fields['contenttype']);
        $this->assertArrayNotHasKey(
            'description',
            $fields,
            'OParl has no teaser text, the description is left to projects',
        );
        $this->assertSame('Haushaltssatzung 2027', $fields['title']);
        $this->assertEquals(new DateTime('2026-09-30'), $fields['sp_date']);
        $this->assertSame(
            'Haushaltssatzung 2027 V/2026/0815 Beschlussvorlage Vorlage',
            $fields['content'],
        );
        $this->assertSame('V/2026/0815', $fields['sp_meta_string_oparl_reference']);
        $this->assertSame('Beschlussvorlage', $fields['sp_meta_string_oparl_paper_type']);
        $this->assertSame('Beschlussvorlage', $fields['sp_meta_string_kicker']);
        $this->assertSame(
            [],
            $this->server->requests,
            'references should not be resolved',
        );
        $this->assertSame('https://ris.test/files/1.pdf', $fields['sp_meta_string_oparl_main_file_url']);
    }

    public function testMainFileWithoutMimeType(): void
    {
        $doc = self::documentLinkingToWebPage();

        (new PaperSchema2xEnricher())->enrichDocument(
            new OparlPaper(
                id: 'https://ris.test/oparl/paper/1',
                mainFile: new OparlFile(accessUrl: 'https://ris.test/files/1'),
            ),
            self::parameter(),
            $doc,
            'p',
        );

        $this->assertSame('https://ris.test/files/1', $doc->url);
        $this->assertNull(
            $doc->contenttype,
            'the type of the file is unknown, it is not necessarily a PDF',
        );
    }

    public function testWithoutMainFileKeepsWebPage(): void
    {
        $doc = self::documentLinkingToWebPage();

        (new PaperSchema2xEnricher())->enrichDocument(
            new OparlPaper(id: 'https://ris.test/oparl/paper/1'),
            self::parameter(),
            $doc,
            'p',
        );

        $this->assertSame('https://ris.test/vo0050.php?id=1', $doc->url);
        $this->assertSame('text/html; charset=UTF-8', $doc->contenttype);
    }

    private static function documentLinkingToWebPage(): IndexSchema2xDocument
    {
        $doc = new IndexSchema2xDocument();
        $doc->url = 'https://ris.test/vo0050.php?id=1';
        $doc->contenttype = 'text/html; charset=UTF-8';
        return $doc;
    }

    public function testMinimalPaper(): void
    {
        $fields = (new PaperSchema2xEnricher())->enrichDocument(
            new OparlPaper(id: 'https://ris.test/oparl/paper/1'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        )->getFields();

        $this->assertArrayNotHasKey('sp_meta_string_oparl_reference', $fields);
        $this->assertArrayNotHasKey('sp_meta_string_oparl_paper_type', $fields);
        $this->assertArrayNotHasKey('sp_meta_string_oparl_main_file_url', $fields);
    }

    public function testIgnoresOtherTypes(): void
    {
        $doc = (new PaperSchema2xEnricher())->enrichDocument(
            new OparlMeeting(id: 'm', name: 'Sitzung'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $this->assertNull($doc->title);
    }
}
