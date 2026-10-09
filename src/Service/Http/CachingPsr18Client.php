<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Service\Http;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Caches successful GET responses for a fixed time, regardless of the cache
 * headers of the server - OParl servers rarely send useful ones.
 *
 * Only meant for objects that are referenced over and over again during a
 * run, such as organizations and persons. The lists that are indexed must
 * never go through this client: a cached page would hide modifications.
 */
class CachingPsr18Client implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CacheItemPoolInterface $cache,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly int $ttl,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() !== 'GET' || $this->ttl <= 0) {
            return $this->client->sendRequest($request);
        }

        $item = $this->cache->getItem(
            'http.' . sha1((string) $request->getUri()),
        );
        $cached = $item->isHit() ? $item->get() : null;
        if (
            is_array($cached)
            && is_string($cached['body'] ?? null)
            && is_string($cached['contentType'] ?? null)
        ) {
            return $this->responseFactory->createResponse(200)
                ->withHeader('Content-Type', $cached['contentType'])
                ->withBody(
                    $this->streamFactory->createStream($cached['body']),
                );
        }

        $response = $this->client->sendRequest($request);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $body = (string) $response->getBody();
        $item->set([
            'contentType' => $response->getHeaderLine('Content-Type'),
            'body' => $body,
        ]);
        $item->expiresAfter($this->ttl);
        $this->cache->save($item);

        return $response->withBody($this->streamFactory->createStream($body));
    }
}
