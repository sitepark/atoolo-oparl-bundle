<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service\Http;

use Atoolo\Oparl\Service\Http\OparlClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\V1\Objects\OparlOrganization;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(OparlClientFactory::class)]
class OparlClientFactoryTest extends TestCase
{
    private const URL = 'https://ris.test/oparl/organization/1';

    public function testCreateRetries(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
            self::organizationResponse(),
        ]);

        $client = OparlClientFactory::create($httpClient, 1);
        $organization = $client->get(self::URL, OparlOrganization::class);

        $this->assertSame('Rat', $organization->getName());
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testCreateWithoutRetries(): void
    {
        $httpClient = new MockHttpClient([self::organizationResponse()]);

        $client = OparlClientFactory::create($httpClient, 0);

        $this->assertSame(
            'Rat',
            $client->get(self::URL, OparlOrganization::class)->getName(),
        );
    }

    public function testCreateCaching(): void
    {
        $httpClient = new MockHttpClient([self::organizationResponse()]);

        $client = OparlClientFactory::createCaching(
            $httpClient,
            new ArrayAdapter(),
            60,
        );
        $client->get(self::URL, OparlOrganization::class);
        $organization = $client->get(self::URL, OparlOrganization::class);

        $this->assertSame('Rat', $organization->getName());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    private static function organizationResponse(): MockResponse
    {
        return new MockResponse(
            json_encode([
                'id' => self::URL,
                'type' => 'https://schema.oparl.org/1.1/Organization',
                'name' => 'Rat',
            ], JSON_THROW_ON_ERROR),
            ['response_headers' => ['Content-Type' => 'application/json']],
        );
    }
}
