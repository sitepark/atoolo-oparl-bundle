<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Http;

use Http\Discovery\Psr17Factory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SP\OparlClient\OparlClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Creates the {@see OparlClient} instances of the bundle on top of a Symfony
 * HTTP client. Timeouts, proxy and TLS settings belong to that HTTP client;
 * the factory only adds retries and, for the resolving client, the cache.
 */
final class OparlClientFactory
{
    private function __construct() {}

    /**
     * Client for the lists that are indexed. Never cached.
     */
    public static function create(
        HttpClientInterface $httpClient,
        int $maxRetries = 3,
        LoggerInterface $logger = new NullLogger(),
    ): OparlClient {
        $psr17 = new Psr17Factory();
        return new OparlClient(
            httpClient: new Psr18Client(
                self::retrying($httpClient, $maxRetries, $logger),
                $psr17,
                $psr17,
            ),
            requestFactory: $psr17,
            logger: $logger,
        );
    }

    /**
     * Client for resolving referenced objects, whose responses are cached
     * for `$ttl` seconds.
     */
    public static function createCaching(
        HttpClientInterface $httpClient,
        CacheItemPoolInterface $cache,
        int $ttl,
        int $maxRetries = 3,
        LoggerInterface $logger = new NullLogger(),
    ): OparlClient {
        $psr17 = new Psr17Factory();
        return new OparlClient(
            httpClient: new CachingPsr18Client(
                new Psr18Client(
                    self::retrying($httpClient, $maxRetries, $logger),
                    $psr17,
                    $psr17,
                ),
                $cache,
                $psr17,
                $psr17,
                $ttl,
            ),
            requestFactory: $psr17,
            logger: $logger,
        );
    }

    private static function retrying(
        HttpClientInterface $httpClient,
        int $maxRetries,
        LoggerInterface $logger,
    ): HttpClientInterface {
        if ($maxRetries <= 0) {
            return $httpClient;
        }
        return new RetryableHttpClient(
            $httpClient,
            null,
            $maxRetries,
            $logger,
        );
    }
}
