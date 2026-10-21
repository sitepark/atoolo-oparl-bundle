<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Indexer;

/**
 * The OParl types that can be indexed. Each type is fetched through
 * the body wide list of the same name; one indexer is registered per type.
 *
 * The lists for agenda items and files only exist since OParl 1.1. A
 * server that only speaks 1.0 does not provide them, see
 * {@see OparlListFetcher::listOf()}.
 */
enum OparlType: string
{
    case MEETING = 'meeting';
    case PAPER = 'paper';
    case ORGANIZATION = 'organization';
    case PERSON = 'person';
    case AGENDA_ITEM = 'agendaItem';
    case FILE = 'file';
}
