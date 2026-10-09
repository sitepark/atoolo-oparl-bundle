<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Http;

use Atoolo\Oparl\Service\Http\CachingPsr18Client;
use Atoolo\Oparl\Test\Fixture\FakeOparlServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(CachingPsr18Client::class)]
class CachingPsr18ClientTest extends TestCase
{
    private const URL = 'https://ris.test/oparl/organization/1';

    private FakeOparlServer $server;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->server = new FakeOparlServer();
        $this->factory = new Psr17Factory();
    }

    public function testCachesSuccessfulGet(): void
    {
        $this->server->json(self::URL, ['id' => self::URL]);
        $client = $this->createClient(60);

        $first = $client->sendRequest($this->factory->createRequest('GET', self::URL));
        $second = $client->sendRequest($this->factory->createRequest('GET', self::URL));

        $this->assertSame(1, $this->server->requestCount(self::URL));
        $this->assertSame((string) $first->getBody(), (string) $second->getBody());
        $this->assertSame('application/json', $second->getHeaderLine('Content-Type'));
        $this->assertSame(200, $second->getStatusCode());
    }

    public function testDoesNotCacheErrors(): void
    {
        $this->server->error(self::URL);
        $client = $this->createClient(60);

        $client->sendRequest($this->factory->createRequest('GET', self::URL));
        $response = $client->sendRequest($this->factory->createRequest('GET', self::URL));

        $this->assertSame(2, $this->server->requestCount(self::URL));
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testDoesNotCacheWithoutTtl(): void
    {
        $this->server->json(self::URL, ['id' => self::URL]);
        $client = $this->createClient(0);

        $client->sendRequest($this->factory->createRequest('GET', self::URL));
        $client->sendRequest($this->factory->createRequest('GET', self::URL));

        $this->assertSame(2, $this->server->requestCount(self::URL));
    }

    public function testDoesNotCacheOtherMethods(): void
    {
        $this->server->json(self::URL, ['id' => self::URL]);
        $client = $this->createClient(60);

        $client->sendRequest($this->factory->createRequest('HEAD', self::URL));
        $client->sendRequest($this->factory->createRequest('HEAD', self::URL));

        $this->assertSame(2, $this->server->requestCount(self::URL));
    }

    private function createClient(int $ttl): CachingPsr18Client
    {
        return new CachingPsr18Client(
            $this->server,
            new ArrayAdapter(),
            $this->factory,
            $this->factory,
            $ttl,
        );
    }
}
