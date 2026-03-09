<?php

namespace App\Tests\Application\Session\EnableSessionShare;

use App\Application\Session\EnableSessionShare\EnableSessionShareCommand;
use App\Application\Session\EnableSessionShare\EnableSessionShareHandler;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class EnableSessionShareHandlerTest extends DbWebTestCase
{
    private EnableSessionShareHandler $handler;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->handler = static::getContainer()->get(EnableSessionShareHandler::class);
    }

    public function testOwnerCanEnableSessionShare(): void
    {
        // Créer un utilisateur
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($owner);
        $this->em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $this->em->persist($group);
        $this->em->flush();

        // Créer une activité
        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);

        $this->em->persist($activity);
        $this->em->flush();

        // Créer une session
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06 20:00:00'));

        $this->em->persist($session);
        $this->em->flush();

        // Activer le partage
        $command = new EnableSessionShareCommand(
            sessionId: $session->getId(),
            groupId: $group->getId(),
            userIdRequestingShare: $owner->getId(),
        );

        $result = $this->handler->handle($command);

        // Vérifier que le token a été généré
        $this->em->clear();

        $updatedSession = $this->em->getRepository(Session::class)
            ->find($result->getSessionId());

        self::assertNotNull($updatedSession);
        self::assertNotNull($updatedSession->getUnlistedToken());
        self::assertEquals($result->getUnlistedToken(), $updatedSession->getUnlistedToken());
        self::assertTrue(strlen($updatedSession->getUnlistedToken()) > 0);
    }

    public function testReuseExistingToken(): void
    {
        // Créer un utilisateur
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($owner);
        $this->em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $this->em->persist($group);
        $this->em->flush();

        // Créer une activité
        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);

        $this->em->persist($activity);
        $this->em->flush();

        // Créer une session avec un token existant
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06 20:00:00'));
        $session->setUnlistedToken('existing_token_12345');

        $this->em->persist($session);
        $this->em->flush();

        // Activer le partage (réutiliser le token existant)
        $command = new EnableSessionShareCommand(
            sessionId: $session->getId(),
            groupId: $group->getId(),
            userIdRequestingShare: $owner->getId(),
        );

        $result = $this->handler->handle($command);

        // Vérifier que le token n'a pas changé
        self::assertEquals('existing_token_12345', $result->getUnlistedToken());
    }

    public function testSessionBelongingToAnotherGroupThrowsError(): void
    {
        // Créer deux utilisateurs
        $owner1 = new User();
        $owner1->setEmail('owner1@test.local');
        $owner1->setPassword('dummy');
        $owner1->setCreatedAt(new \DateTimeImmutable());

        $owner2 = new User();
        $owner2->setEmail('owner2@test.local');
        $owner2->setPassword('dummy');
        $owner2->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($owner1);
        $this->em->persist($owner2);
        $this->em->flush();

        // Créer deux groupes
        $group1 = new GameGroup();
        $group1->setName('Group 1');
        $group1->setCreatedAt(new \DateTimeImmutable());
        $group1->setCreatedBy($owner1);

        $group2 = new GameGroup();
        $group2->setName('Group 2');
        $group2->setCreatedAt(new \DateTimeImmutable());
        $group2->setCreatedBy($owner2);

        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->flush();

        // Créer une activité dans group1
        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group1);
        $activity->setCreatedBy($owner1);

        $this->em->persist($activity);
        $this->em->flush();

        // Créer une session dans group1
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group1);
        $session->setCreatedBy($owner1);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06 20:00:00'));

        $this->em->persist($session);
        $this->em->flush();

        // Essayer d'activer le partage avec group2 (erreur)
        $command = new EnableSessionShareCommand(
            sessionId: $session->getId(),
            groupId: $group2->getId(),
            userIdRequestingShare: $owner1->getId(),
        );

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('does not belong to group');

        $this->handler->handle($command);
    }
}
