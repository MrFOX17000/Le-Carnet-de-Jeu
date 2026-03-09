<?php

namespace App\Tests\Application\Session\CreateSession;

use App\Application\Session\CreateSession\CreateSessionCommand;
use App\Application\Session\CreateSession\CreateSessionHandler;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CreateSessionHandlerTest extends DbWebTestCase
{
    private CreateSessionHandler $handler;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->handler = static::getContainer()->get(CreateSessionHandler::class);
    }

    public function testOwnerCanCreateSession(): void
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
        $playedAt = new \DateTimeImmutable('2026-03-06 20:00:00');
        $command = new CreateSessionCommand(
            groupId: $group->getId(),
            activityId: $activity->getId(),
            creatorUserId: $owner->getId(),
            playedAt: $playedAt,
            title: 'Match 1',
        );

        $result = $this->handler->handle($command);

        // Vérifier que la session a été créée
        $this->em->clear();

        $session = $this->em->getRepository(Session::class)
            ->find($result->getSessionId());

        self::assertNotNull($session);
        self::assertEquals('Match 1', $session->getTitle());
        self::assertEquals($group->getId(), $session->getGroup()->getId());
        self::assertEquals($activity->getId(), $session->getActivity()->getId());
        self::assertEquals($owner->getId(), $session->getCreatedBy()->getId());
    }

    public function testSessionIsAttachedToCorrectGroup(): void
    {
        // Créer un utilisateur
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($owner);
        $this->em->flush();

        // Créer deux groupes
        $group1 = new GameGroup();
        $group1->setName('Group 1');
        $group1->setCreatedAt(new \DateTimeImmutable());
        $group1->setCreatedBy($owner);

        $group2 = new GameGroup();
        $group2->setName('Group 2');
        $group2->setCreatedAt(new \DateTimeImmutable());
        $group2->setCreatedBy($owner);

        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->flush();

        // Créer deux activités (une par groupe)
        $activity1 = new Activity();
        $activity1->setName('Rocket League');
        $activity1->setGroup($group1);
        $activity1->setCreatedBy($owner);

        $activity2 = new Activity();
        $activity2->setName('Skyjo');
        $activity2->setGroup($group2);
        $activity2->setCreatedBy($owner);

        $this->em->persist($activity1);
        $this->em->persist($activity2);
        $this->em->flush();

        // Créer une session dans le groupe 1
        $playedAt = new \DateTimeImmutable('2026-03-06 20:00:00');
        $command = new CreateSessionCommand(
            groupId: $group1->getId(),
            activityId: $activity1->getId(),
            creatorUserId: $owner->getId(),
            playedAt: $playedAt,
            title: null,
        );

        $result = $this->handler->handle($command);

        // Vérifier que la session est dans le groupe 1, pas le groupe 2
        $this->em->clear();

        $session = $this->em->getRepository(Session::class)
            ->find($result->getSessionId());

        self::assertNotNull($session);
        self::assertEquals($group1->getId(), $session->getGroup()->getId());
        self::assertNotEquals($group2->getId(), $session->getGroup()->getId());
    }

    public function testActivityFromAnotherGroupThrowsException(): void
    {
        // Créer un utilisateur
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($owner);
        $this->em->flush();

        // Créer deux groupes
        $group1 = new GameGroup();
        $group1->setName('Group 1');
        $group1->setCreatedAt(new \DateTimeImmutable());
        $group1->setCreatedBy($owner);

        $group2 = new GameGroup();
        $group2->setName('Group 2');
        $group2->setCreatedAt(new \DateTimeImmutable());
        $group2->setCreatedBy($owner);

        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->flush();

        // Créer une activité dans le groupe 2
        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group2);
        $activity->setCreatedBy($owner);

        $this->em->persist($activity);
        $this->em->flush();

        // Essayer de créer une session dans le groupe 1 avec l'activité du groupe 2
        $playedAt = new \DateTimeImmutable('2026-03-06 20:00:00');
        $command = new CreateSessionCommand(
            groupId: $group1->getId(),
            activityId: $activity->getId(),
            creatorUserId: $owner->getId(),
            playedAt: $playedAt,
            title: null,
        );

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage(
            sprintf('Activity %d does not belong to group %d.', $activity->getId(), $group1->getId())
        );

        $this->handler->handle($command);
    }

    public function testInvalidActivityThrowsException(): void
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

        // Essayer de créer une session avec une activité inexistante
        $playedAt = new \DateTimeImmutable('2026-03-06 20:00:00');
        $command = new CreateSessionCommand(
            groupId: $group->getId(),
            activityId: 99999,
            creatorUserId: $owner->getId(),
            playedAt: $playedAt,
            title: null,
        );

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Activity with ID 99999 not found.');

        $this->handler->handle($command);
    }
}
