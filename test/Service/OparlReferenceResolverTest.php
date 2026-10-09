<?php

declare(strict_types=1);

namespace Atoolo\Oparl\Test\Service;

use Atoolo\Oparl\Service\OparlReferenceResolver;
use Atoolo\Oparl\Test\Fixture\FakeOparlServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlReference;
use SP\OparlClient\OparlClient;
use SP\OparlClient\V1\Objects\OparlOrganization;
use SP\OparlClient\V1\Objects\OparlPerson;

#[CoversClass(OparlReferenceResolver::class)]
class OparlReferenceResolverTest extends TestCase
{
    private const ORGANIZATION = 'https://ris.test/oparl/organization/1';
    private const MISSING = 'https://ris.test/oparl/organization/404';

    private FakeOparlServer $server;

    private OparlReferenceResolver $resolver;

    protected function setUp(): void
    {
        $this->server = new FakeOparlServer();
        $this->server->json(self::ORGANIZATION, [
            'id' => self::ORGANIZATION,
            'type' => 'https://schema.oparl.org/1.1/Organization',
            'name' => 'Rat',
        ]);
        $this->resolver = $this->createResolver(2000);
    }

    private function createResolver(int $maxMemoryEntries): OparlReferenceResolver
    {
        return new OparlReferenceResolver(
            new OparlClient(
                httpClient: $this->server,
                requestFactory: new Psr17Factory(),
            ),
            $maxMemoryEntries,
        );
    }

    public function testResolve(): void
    {
        $organization = $this->resolver->resolve(
            new OparlReference(self::ORGANIZATION),
            OparlOrganization::class,
        );

        $this->assertSame('Rat', $organization?->getName());
    }

    public function testResolveNull(): void
    {
        $this->assertNull(
            $this->resolver->resolve(null, OparlOrganization::class),
        );
    }

    public function testResolvesEachReferenceOnce(): void
    {
        $reference = new OparlReference(self::ORGANIZATION);
        $this->resolver->resolve($reference, OparlOrganization::class);
        $this->resolver->resolve($reference, OparlOrganization::class);

        $this->assertSame(1, $this->server->requestCount(self::ORGANIZATION));
    }

    public function testReset(): void
    {
        $reference = new OparlReference(self::ORGANIZATION);
        $this->resolver->resolve($reference, OparlOrganization::class);
        $this->resolver->reset();
        $this->resolver->resolve($reference, OparlOrganization::class);

        $this->assertSame(2, $this->server->requestCount(self::ORGANIZATION));
    }

    public function testEmptiesMemoryWhenFull(): void
    {
        $other = 'https://ris.test/oparl/organization/2';
        $this->server->json($other, [
            'id' => $other,
            'type' => 'https://schema.oparl.org/1.1/Organization',
        ]);
        $resolver = $this->createResolver(1);

        $resolver->resolve(new OparlReference(self::ORGANIZATION), OparlOrganization::class);
        $resolver->resolve(new OparlReference($other), OparlOrganization::class);
        $resolver->resolve(new OparlReference(self::ORGANIZATION), OparlOrganization::class);

        $this->assertSame(2, $this->server->requestCount(self::ORGANIZATION));
    }

    public function testWithoutMemory(): void
    {
        $resolver = $this->createResolver(0);
        $reference = new OparlReference(self::ORGANIZATION);

        $resolver->resolve($reference, OparlOrganization::class);
        $organization = $resolver->resolve($reference, OparlOrganization::class);

        $this->assertSame('Rat', $organization?->getName());
        $this->assertSame(2, $this->server->requestCount(self::ORGANIZATION));
    }

    public function testUnresolvableReference(): void
    {
        $this->assertNull($this->resolver->resolve(
            new OparlReference(self::MISSING),
            OparlOrganization::class,
        ));
    }

    public function testWrongType(): void
    {
        $this->resolver->resolve(
            new OparlReference(self::ORGANIZATION),
            OparlOrganization::class,
        );

        $this->assertNull($this->resolver->resolve(
            new OparlReference(self::ORGANIZATION),
            OparlPerson::class,
        ));
    }

    public function testResolveAll(): void
    {
        $organizations = $this->resolver->resolveAll(
            [new OparlReference(self::ORGANIZATION), new OparlReference(self::MISSING)],
            OparlOrganization::class,
        );

        $this->assertCount(1, $organizations);
        $this->assertSame([], $this->resolver->resolveAll(null, OparlOrganization::class));
    }
}
