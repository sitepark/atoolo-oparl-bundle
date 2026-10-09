<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Indexer\Schema2x;

use Atoolo\Oparl\Service\Indexer\Schema2x\FileSchema2xEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use SP\OparlClient\V1\Objects\OparlFile;
use SP\OparlClient\V1\Objects\OparlMeeting;

#[CoversClass(FileSchema2xEnricher::class)]
class FileSchema2xEnricherTest extends EnricherTestCase
{
    private static function file(): OparlFile
    {
        return new OparlFile(
            id: 'https://ris.test/oparl/file/1',
            fileName: 'vorlage.pdf',
            mimeType: 'application/pdf',
            date: new DateTimeImmutable('2026-09-30'),
            size: 12345,
            text: 'Volltext der Vorlage',
            accessUrl: 'https://ris.test/files/1.pdf',
            downloadUrl: 'https://ris.test/files/1.pdf?download',
        );
    }

    public function testEnrich(): void
    {
        $doc = new IndexSchema2xDocument();
        $doc->url = 'https://ris.test/oparl/file/1';

        $fields = (new FileSchema2xEnricher())->enrichDocument(
            self::file(),
            self::parameter(),
            $doc,
            'p',
        )->getFields();

        $this->assertSame('https://ris.test/files/1.pdf', $fields['url']);
        $this->assertSame('application/pdf', $fields['contenttype']);
        $this->assertSame('vorlage.pdf', $fields['title']);
        $this->assertEquals(new DateTime('2026-09-30'), $fields['sp_date']);
        $this->assertSame('vorlage.pdf Volltext der Vorlage', $fields['content']);
        $this->assertSame('vorlage.pdf', $fields['sp_meta_string_oparl_file_name']);
        $this->assertSame('application/pdf', $fields['sp_meta_string_oparl_mime_type']);
        $this->assertSame(12345, $fields['sp_meta_long_oparl_size']);
        $this->assertSame('https://ris.test/files/1.pdf', $fields['sp_meta_string_oparl_access_url']);
        $this->assertSame('https://ris.test/files/1.pdf?download', $fields['sp_meta_string_oparl_download_url']);
    }

    public function testUnknownMimeType(): void
    {
        $doc = new IndexSchema2xDocument();
        $doc->contenttype = 'text/html; charset=UTF-8';

        (new FileSchema2xEnricher())->enrichDocument(
            new OparlFile(id: 'f1', accessUrl: 'https://ris.test/files/1'),
            self::parameter(),
            $doc,
            'p',
        );

        $this->assertSame('https://ris.test/files/1', $doc->url);
        $this->assertNull($doc->contenttype);
    }

    public function testMinimalFile(): void
    {
        $fields = (new FileSchema2xEnricher())->enrichDocument(
            new OparlFile(id: 'f1'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        )->getFields();

        $this->assertArrayNotHasKey('title', $fields);
        $this->assertArrayNotHasKey('sp_meta_long_oparl_size', $fields);
    }

    public function testIgnoresOtherTypes(): void
    {
        $doc = (new FileSchema2xEnricher())->enrichDocument(
            new OparlMeeting(id: 'm', name: 'Sitzung'),
            self::parameter(),
            new IndexSchema2xDocument(),
            'p',
        );

        $this->assertNull($doc->title);
    }
}
