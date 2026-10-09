<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer\Schema2x;

use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use DateTime;
use DateTimeImmutable;
use SP\OparlClient\V1\Objects\OparlFile;
use SP\OparlClient\V1\Objects\OparlLocation;

/**
 * Helpers shared by the Schema 2.x enrichers.
 */
final class Schema2xFields
{
    private function __construct() {}

    public static function setTitle(
        IndexSchema2xDocument $doc,
        ?string $title,
        ?string $sortValue = null,
    ): void {
        if ($title === null || trim($title) === '') {
            return;
        }
        $doc->title = $title;
        $doc->sp_name = $title;
        $doc->sp_title = $title;
        $doc->sp_sortvalue = $sortValue ?? $title;
        $doc->sp_startletter = mb_strtoupper(mb_substr(
            ltrim($sortValue ?? $title),
            0,
            1,
        ));
    }

    /**
     * Sets the date range of the document. Without an end the range is the
     * start day.
     */
    public static function setDates(
        IndexSchema2xDocument $doc,
        ?DateTimeImmutable $start,
        ?DateTimeImmutable $end = null,
    ): void {
        $from = self::toDateTime($start);
        if ($from === null) {
            return;
        }
        $doc->sp_date = $from;
        $doc->sp_date_from = $from;
        $doc->sp_date_to = self::toDateTime($end) ?? $from;
        $doc->sp_date_list = [$from];
    }

    /**
     * Lets the document link to the file instead of the page of the
     * object, if the file can be accessed. The content type is the MIME
     * type of the file; it stays empty if the server does not tell it, as
     * the file is not necessarily a PDF.
     */
    public static function linkToFile(
        IndexSchema2xDocument $doc,
        ?OparlFile $file,
    ): void {
        $accessUrl = $file?->getAccessUrl();
        if ($accessUrl === null) {
            return;
        }
        $doc->url = $accessUrl;
        $doc->contenttype = $file?->getMimeType();
    }

    /**
     * Appends to the full text content of the document.
     */
    public static function addContent(
        IndexSchema2xDocument $doc,
        ?string ...$parts,
    ): void {
        $content = self::join(' ', $doc->content, ...$parts);
        $doc->content = $content;
    }

    public static function toDateTime(?DateTimeImmutable $date): ?DateTime
    {
        return $date === null ? null : DateTime::createFromImmutable($date);
    }

    public static function locationText(?OparlLocation $location): ?string
    {
        if ($location === null) {
            return null;
        }
        $address = self::join(
            ' ',
            $location->getPostalCode(),
            $location->getLocality(),
        );
        return self::join(
            ', ',
            $location->getDescription(),
            $location->getRoom(),
            $location->getStreetAddress(),
            $address,
        );
    }

    public static function join(string $separator, ?string ...$parts): ?string
    {
        $parts = array_filter(
            array_map(
                static fn(?string $part) => $part === null
                    ? ''
                    : trim($part),
                $parts,
            ),
            static fn(string $part) => $part !== '',
        );
        return $parts === [] ? null : implode($separator, $parts);
    }

    /**
     * @param iterable<object> $objects objects with a `getName()` method
     * @return list<string>
     */
    public static function names(iterable $objects): array
    {
        $names = [];
        foreach ($objects as $object) {
            if (!method_exists($object, 'getName')) {
                continue;
            }
            $name = $object->getName();
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }
        return $names;
    }
}
