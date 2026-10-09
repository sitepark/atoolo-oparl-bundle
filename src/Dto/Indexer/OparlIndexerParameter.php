<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Dto\Indexer;

use Atoolo\Index\Dto\Indexer\IndexerConfiguration;
use Atoolo\Resource\DataBag;
use InvalidArgumentException;

/**
 * Parameters of an OParl indexer run, read from the CMS side indexer
 * configuration `configs/indexer/<source>.php`.
 *
 * ```php
 * return [
 *     'name' => 'Ratsinformationssystem - Sitzungen',
 *     'data' => [
 *         'systemUrl' => 'https://ris.example.org/oparl/v1.1',
 *         // optional, all bodies of the system are indexed otherwise
 *         'bodyUrls' => ['https://ris.example.org/oparl/v1.1/body/1'],
 *         'cleanupThreshold' => 10,
 *         'chunkSize' => 500,
 *         // hours between two full runs, 0 disables delta runs
 *         'fullSyncInterval' => 24,
 *         // seconds subtracted from the last run for `modified_since`
 *         'modifiedSinceOverlap' => 300,
 *         // let the server leave out embedded objects (e.g. the agenda
 *         // items of a meeting), smaller responses but less content
 *         'omitInternal' => false,
 *         'group' => 1234,
 *         'groupPath' => [1, 12, 1234],
 *         'category' => [5678],
 *         'categoryPath' => [56, 5678],
 *     ],
 * ];
 * ```
 *
 * Project specific enrichers read further options from `$data`.
 */
final class OparlIndexerParameter
{
    /**
     * @param list<string> $bodyUrls
     * @param list<int> $groupPath
     * @param list<int> $category
     * @param list<int> $categoryPath
     */
    public function __construct(
        public readonly string $source,
        public readonly string $name,
        public readonly string $systemUrl,
        public readonly array $bodyUrls = [],
        public readonly int $cleanupThreshold = 10,
        public readonly int $chunkSize = 500,
        public readonly int $fullSyncInterval = 24,
        public readonly int $modifiedSinceOverlap = 300,
        public readonly bool $omitInternal = false,
        public readonly ?int $group = null,
        public readonly array $groupPath = [],
        public readonly array $category = [],
        public readonly array $categoryPath = [],
        public readonly DataBag $data = new DataBag([]),
    ) {
        if ($this->systemUrl === '' && $this->bodyUrls === []) {
            throw new InvalidArgumentException(
                'OParl indexer "' . $source
                . '": either systemUrl or bodyUrls must be configured',
            );
        }
        if ($this->chunkSize < 10) {
            throw new InvalidArgumentException(
                'chunk size must be greater than 9',
            );
        }
    }

    public static function fromConfiguration(
        IndexerConfiguration $config,
    ): self {
        $data = $config->data;
        return new self(
            source: $config->source,
            name: $config->name,
            systemUrl: $data->getString('systemUrl'),
            bodyUrls: self::stringList($data->getArray('bodyUrls')),
            cleanupThreshold: $data->getInt('cleanupThreshold', 10),
            chunkSize: $data->getInt('chunkSize', 500),
            fullSyncInterval: $data->getInt('fullSyncInterval', 24),
            modifiedSinceOverlap: $data->getInt('modifiedSinceOverlap', 300),
            omitInternal: $data->getBool('omitInternal'),
            group: $data->has('group') ? $data->getInt('group') : null,
            groupPath: self::intList($data->getArray('groupPath')),
            category: self::intList($data->getArray('category')),
            categoryPath: self::intList($data->getArray('categoryPath')),
            data: $data,
        );
    }

    public function isDeltaEnabled(): bool
    {
        return $this->fullSyncInterval > 0;
    }

    /**
     * Object ids, as numbers or numeric strings.
     *
     * @param array<mixed> $values
     * @return list<int>
     */
    private static function intList(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $list[] = (int) $value;
            }
        }
        return $list;
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    private static function stringList(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $list[] = (string) $value;
            }
        }
        return $list;
    }
}
