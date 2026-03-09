<?php

namespace App\Tests\UI\Session;

use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class PublicSharedSessionControllerTest extends DbWebTestCase
{
    public function testPublicSessionCanBeAccessedWithToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer un utilisateur propriétaire
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('hashed_password');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $owner->setIsVerified(true);

        $em->persist($owner);
        $em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $em->persist($group);
        $em->flush();

        // Créer une activité
        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);

        $em->persist($activity);
        $em->flush();

        // Créer une session avec un token
        $token = 'test_token_123456789';
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06 20:00:00'));
        $session->setUnlistedToken($token);

        $em->persist($session);
        $em->flush();

        // Accéder à la page publique SANS authentication
        $client->request('GET', sprintf('/s/%s', $token));

        // Vérifier que l'accès est autorisé (200)
        self::assertResponseIsSuccessful();

        // Vérifier que le contenu de la session est visible
        self::assertStringContainsString('Rocket League', $client->getResponse()->getContent());
    }

    public function testUnknownTokenReturns404(): void
    {
        $client = static::createClient();

        // Accéder à la page publique avec un token invalide
        $client->request('GET', '/s/unknown_token_that_does_not_exist');

        // Vérifier que l'accès est refusé (404)
        self::assertResponseStatusCodeSame(404);
    }

    public function testPublicSessionDoesNotRequireAuthentication(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer un utilisateur propriétaire
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('hashed_password');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $owner->setIsVerified(true);

        $em->persist($owner);
        $em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $em->persist($group);
        $em->flush();

        // Créer une activité
        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);

        $em->persist($activity);
        $em->flush();

        // Créer une session avec un token
        $token = 'public_test_token_123456789';
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06 20:00:00'));
        $session->setUnlistedToken($token);

        $em->persist($session);
        $em->flush();

        // Vérifier que le client n'est pas authentifié
        self::assertNull($client->getContainer()->get('security.token_storage')->getToken());

        // Accéder à la page publique SANS authentication
        $client->request('GET', sprintf('/s/%s', $token));

        // Vérifier que l'accès est autorisé (200)
        self::assertResponseIsSuccessful();
    }
}
