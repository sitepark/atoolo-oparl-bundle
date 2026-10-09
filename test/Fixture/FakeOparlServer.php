<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Fixture;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client answering from a map of URLs to JSON documents. A URL with
 * a query string falls back to the response of the URL without it, so that
 * tests need not repeat the filters the indexer appends.
 */
final class FakeOparlServer implements ClientInterface
{
    /**
     * @var array<string, array{int, string}>
     */
    private array $responses = [];

    /**
     * @var list<string>
     */
    public array $requests = [];

    private readonly Psr17Factory $factory;

    public function __construct()
    {
        $this->factory = new Psr17Factory();
    }

    /**
     * @param array<string, mixed> $json
     */
    public function json(string $url, array $json, int $status = 200): self
    {
        $this->responses[$url] = [
            $status,
            json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ];
        return $this;
    }

    public function error(string $url, int $status = 500): self
    {
        $this->responses[$url] = [$status, '{}'];
        return $this;
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    public function page(
        string $url,
        array $data,
        ?string $next = null,
        ?int $total = null,
    ): self {
        $links = ['self' => $url];
        if ($next !== null) {
            $links['next'] = $next;
        }
        return $this->json($url, [
            'data' => $data,
            'pagination' => ['totalElements' => $total ?? count($data)],
            'links' => $links,
        ]);
    }

    public function requestCount(string $url): int
    {
        return count(array_filter(
            $this->requests,
            static fn(string $request) => $request === $url,
        ));
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();
        $this->requests[] = $url;
        $response = $this->responses[$url]
            ?? $this->responses[strtok($url, '?')]
            ?? [404, '{}'];
        return $this->factory->createResponse($response[0])
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($response[1]));
    }
}
