![phpstan](https://img.shields.io/badge/PHPStan-level%209-brightgreen)
![php](https://img.shields.io/badge/PHP-8.2-blue)
![php](https://img.shields.io/badge/PHP-8.3-blue)
![php](https://img.shields.io/badge/PHP-8.4-blue)
![php](https://img.shields.io/badge/PHP-8.5-blue)

# Atoolo OParl bundle

Indexes the data of an [OParl](https://oparl.org) API - meetings, papers,
organizations, persons, agenda items and files - into the search index.

The bundle provides one indexer per OParl type on top of
[atoolo/index-bundle](https://github.com/sitepark/atoolo-index-bundle). The
data is fetched with [sitepark/oparl-client](https://github.com/sitepark/oparl-php-client)
and written to Solr through [atoolo/search-bundle](https://github.com/sitepark/atoolo-search-bundle).

## Installation

```bash
composer require atoolo/oparl-bundle
```

Register the bundles in `config/bundles.php` - the index bundle as well, if it
is not registered yet:

```php
Atoolo\Index\AtooloIndexBundle::class => ['all' => true],
Atoolo\Oparl\AtooloOparlBundle::class => ['all' => true],
```

## Indexers

| Source               | OParl type   | OParl version |
|----------------------|--------------|---------------|
| `oparl-meeting`      | Meeting      | 1.0, 1.1      |
| `oparl-paper`        | Paper        | 1.0, 1.1      |
| `oparl-organization` | Organization | 1.0, 1.1      |
| `oparl-person`       | Person       | 1.0, 1.1      |
| `oparl-agendaItem`   | AgendaItem   | 1.1           |
| `oparl-file`         | File         | 1.1           |

Each indexer has its own status, schedule and cleanup. An indexer is only
active if the CMS provides its configuration `configs/indexer/<source>.php`,
so a project only switches on the types it needs.

### Configuration

```php
<?php
// configs/indexer/oparl-meeting.php
return [
    'name' => 'Ratsinformationssystem - Sitzungen',
    'data' => [
        // entry point of the OParl API (System object)
        'systemUrl' => 'https://ris.example.org/oparl/v1.1',
        // optional: only these bodies instead of all bodies of the system
        'bodyUrls' => ['https://ris.example.org/oparl/v1.1/body/1'],
        // a full run only deletes stale documents if at least this many
        // documents were written (default 10)
        'cleanupThreshold' => 10,
        // documents per update request (default 500)
        'chunkSize' => 500,
        // hours between two full runs, 0 = every run is a full run (default 24)
        'fullSyncInterval' => 24,
        // seconds subtracted from the last run for modified_since (default 300)
        'modifiedSinceOverlap' => 300,
        // let the server leave out embedded objects (default false)
        'omitInternal' => false,
        'group' => 1234,
        'groupPath' => [1, 12, 1234],
        'category' => [5678],
        'categoryPath' => [56, 5678],
    ],
];
```

### Scheduling

The indexers are scheduled through the index bundle:

```yaml
# config/packages/atoolo_oparl.yaml
parameters:
    atoolo_index.indexer.schedules:
        oparl-meeting: '*/30 * * * *'
        oparl-paper: '15 * * * *'
        oparl-organization: '0 3 * * *'
```

`atoolo_index.indexer.schedules` is one parameter for all indexers of the
project, including those of other bundles such as `internal`. Symfony does
not merge parameters - set it in one place with all sources, otherwise one
definition replaces the other.

A worker has to consume the schedule: `bin/console messenger:consume scheduler_atoolo_index`.
An indexer can also be started manually: `bin/console index:indexer --source=oparl-meeting`.

## Full and delta runs

Fetching an OParl API completely can take long, the lists are paginated and
can have hundreds of pages. Therefore:

- A **full run** fetches all objects. Afterwards it deletes every document of
  the source that it did not write, if it wrote at least `cleanupThreshold`
  documents.
- A **delta run** only fetches the objects modified since the last successful
  run (`modified_since`) and deletes the objects the server reports as
  deleted. Everything else stays untouched.

A full run is done if the last one is older than `fullSyncInterval` hours.
Only a run that completed and whose updates all succeeded is remembered;
after an error, a failed update or an abortion the next run starts over from
the same point, and a full run deletes nothing then.

## Filtering

Which objects are indexed is decided by the service
`atoolo_oparl.indexer.object_filter`, an `Atoolo\Oparl\Service\Indexer\OparlObjectFilter`.
By default every object is accepted. A project replaces the service to leave
objects out; an object that is not accepted is also removed from the index,
should it have been indexed before. The filter is shared by all OParl
indexers, `$parameter->source` tells which one calls it.

```php
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\OparlObjectFilter;
use SP\OparlClient\V1\Objects\OparlFile;
use SP\OparlClient\V1\Objects\OparlObjectV1;

/**
 * Only objects with a page to link to.
 */
class WebPageFilter implements OparlObjectFilter
{
    public function accept(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
    ): bool {
        if ($object instanceof OparlFile) {
            return $object->getAccessUrl() !== null;
        }
        return $object->getWeb() !== null;
    }
}
```

```yaml
services:
    atoolo_oparl.indexer.object_filter:
        class: App\Indexer\WebPageFilter
```

## Caching

The state of the delta runs and the responses for objects resolved with the
`OparlReferenceResolver` (cached for `atoolo_oparl.reference_cache_ttl`
seconds, see [Mapping](#mapping)) are kept in the cache pool
`atoolo_oparl.cache`. By default this is
a filesystem cache in `var/oparl`, outside of the kernel cache directory so
that `cache:clear` keeps it. Losing it is harmless, the next run is a full run;
`bin/console cache:pool:clear atoolo_oparl.cache` forces one.

The pool is configured like any other cache pool. To share it between
several servers, e.g. via Redis:

```yaml
# config/packages/cache.yaml
parameters:
    redis_host: '%env(REDIS_HOST)%'
    env(REDIS_HOST): localhost

framework:
    cache:
        default_redis_provider: 'redis://%redis_host%'
        pools:
            atoolo_oparl.cache:
                adapter: cache.adapter.redis
```

## HTTP

All requests go through the Symfony HTTP client `atoolo_oparl.http_client`.
Its options are set with the parameter `atoolo_oparl.http_client.options`;
proxies are taken from the environment (`HTTPS_PROXY`, `NO_PROXY`). Failed
requests are retried `atoolo_oparl.http_client.max_retries` times.

```yaml
parameters:
    atoolo_oparl.http_client.options:
        timeout: 60
        max_duration: 600
        proxy: 'http://proxy.example.org:3128'
```

## Mapping

The documents are filled by enrichers tagged
`atoolo_oparl.indexer.document_enricher.schema2x`. The bundle maps the
common fields (`id`, `url`, `sp_source`, group, category, ...) with
priority 100 and the fields of each type with priority 50. Type specific
fields are written as `sp_meta_string_oparl_*`.

The bundle does not set `description`. The field is used as teaser text,
but OParl offers no such text for most objects - a meeting, a paper or an
organization only has a name and a few properties, `resolutionText` and
the `text` of a file are far too long. What there is goes into `content`
and the meta fields instead. A project that wants a teaser text sets it in
an enricher of its own.

A project adds or overwrites fields with an enricher of its own and a lower
priority:

```php
use Atoolo\Index\Service\Indexer\IndexDocument;
use Atoolo\Oparl\Dto\Indexer\OparlIndexerParameter;
use Atoolo\Oparl\Service\Indexer\OparlDocumentEnricher;
use Atoolo\Search\Service\Indexer\IndexSchema2xDocument;
use SP\OparlClient\V1\Objects\OparlObjectV1;
use SP\OparlClient\V1\Objects\OparlPaper;

/**
 * @implements OparlDocumentEnricher<IndexSchema2xDocument>
 */
class PaperKickerEnricher implements OparlDocumentEnricher
{
    public function enrichDocument(
        OparlObjectV1 $object,
        OparlIndexerParameter $parameter,
        IndexDocument $doc,
        string $processId,
    ): IndexDocument {
        if ($object instanceof OparlPaper && $doc instanceof IndexSchema2xDocument) {
            // options of the CMS configuration are available in $parameter->data
            $doc->setMetaString('kicker', 'Drucksache ' . $object->getReference());
        }
        return $doc;
    }
}
```

```yaml
services:
    App\Indexer\PaperKickerEnricher:
        tags:
            - { name: 'atoolo_oparl.indexer.document_enricher.schema2x', priority: 10 }
```

The enrichers of the bundle use only what the object itself contains and
resolve no references - names of organizations or the meeting of an agenda
item would cost a request each. An enricher of a project that needs them
resolves them with `Atoolo\Oparl\Service\OparlReferenceResolver`
(service `atoolo_oparl.reference_resolver`), which caches the objects.
