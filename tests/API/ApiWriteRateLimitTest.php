<?php

namespace App\Tests\API;

use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ApiWriteRateLimitTest extends DbWebTestCase
{
    public function testApiWriteReturns429WhenLimitIsExceeded(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('api-rate-limit@test.local');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        $testKey = 'api-write-limit-suite-' . bin2hex(random_bytes(4));

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RATELIMIT_TEST_KEY' => $testKey,
        ];

        for ($i = 1; $i <= 20; ++$i) {
            $client->request(
                'POST',
                '/api/groups',
                server: $headers,
                content: json_encode(['name' => sprintf('Rate limited group %d', $i)], JSON_THROW_ON_ERROR)
            );

            self::assertResponseStatusCodeSame(201);
        }

        $client->request(
            'POST',
            '/api/groups',
            server: $headers,
            content: json_encode(['name' => 'Rate limited group overflow'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(429);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame('too_many_requests', $payload['error']['code'] ?? null);
        self::assertNotEmpty($client->getResponse()->headers->get('Retry-After'));
    }
}
