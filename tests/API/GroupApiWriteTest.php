<?php

namespace App\Tests\API;

use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class GroupApiWriteTest extends DbWebTestCase
{
    public function testAuthenticatedUserCanCreateGroupViaApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('api-write-user@test.local');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request(
            'POST',
            '/api/groups',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Mes potes Rocket League'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame('Mes potes Rocket League', $payload['data']['name'] ?? null);
        self::assertIsInt($payload['data']['id'] ?? null);

        $group = $em->getRepository(GameGroup::class)->find($payload['data']['id']);
        self::assertNotNull($group);
        self::assertSame('Mes potes Rocket League', $group->getName());

        $membership = $em->getRepository(GroupMember::class)->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);

        self::assertNotNull($membership);
        self::assertSame(GroupRole::OWNER, $membership->getRole());
    }

    public function testAnonymousUserGets401OnCreateGroupApi(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/groups',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Anonymous Group'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(401);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('unauthorized', $payload['error']['code'] ?? null);
    }

    public function testMissingNameGets422OnCreateGroupApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('api-write-validation@test.local');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request(
            'POST',
            '/api/groups',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('name_required', $payload['error']['code'] ?? null);
    }

    public function testInvalidJsonGets400OnCreateGroupApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('api-write-invalid-json@test.local');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request(
            'POST',
            '/api/groups',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"name": "broken"'
        );

        self::assertResponseStatusCodeSame(400);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('invalid_json', $payload['error']['code'] ?? null);
    }
}
