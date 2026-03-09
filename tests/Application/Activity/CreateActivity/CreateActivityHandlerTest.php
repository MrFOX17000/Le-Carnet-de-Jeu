<?php

namespace App\Tests\Application\Activity\CreateActivity;

use App\Application\Activity\CreateActivity\CreateActivityCommand;
use App\Application\Activity\CreateActivity\CreateActivityHandler;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CreateActivityHandlerTest extends DbWebTestCase
{
    private CreateActivityHandler $handler;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->handler = static::getContainer()->get(CreateActivityHandler::class);
    }

    public function testOwnerCanCreateActivity(): void
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
        $command = new CreateActivityCommand(
            groupId: $group->getId(),
            name: 'Rocket League',
            creatorUserId: $owner->getId(),
        );

        $result = $this->handler->handle($command);

        // Vérifier que l'activité a été créée
        $this->em->clear();

        $activity = $this->em->getRepository(Activity::class)
            ->find($result->getActivityId());

        self::assertNotNull($activity);
        self::assertEquals('Rocket League', $activity->getName());
        self::assertEquals($group->getId(), $activity->getGroup()->getId());
        self::assertEquals($owner->getId(), $activity->getCreatedBy()->getId());
    }

    public function testActivityIsAttachedToCorrectGroup(): void
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

        // Créer une activité dans le groupe 1
        $command = new CreateActivityCommand(
            groupId: $group1->getId(),
            name: 'Skyjo',
            creatorUserId: $owner->getId(),
        );

        $result = $this->handler->handle($command);

        // Vérifier que l'activité est dans le groupe 1, pas le groupe 2
        $this->em->clear();

        $activity = $this->em->getRepository(Activity::class)
            ->find($result->getActivityId());

        self::assertNotNull($activity);
        self::assertEquals($group1->getId(), $activity->getGroup()->getId());
        self::assertNotEquals($group2->getId(), $activity->getGroup()->getId());
    }

    public function testEmptyNameThrowsException(): void
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

        // Essayer de créer une activité avec un nom vide
        $command = new CreateActivityCommand(
            groupId: $group->getId(),
            name: '',
            creatorUserId: $owner->getId(),
        );

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Activity name cannot be empty.');

        $this->handler->handle($command);
    }

    public function testInvalidGroupThrowsException(): void
    {
        // Créer un utilisateur
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($owner);
        $this->em->flush();

        // Essayer de créer une activité avec un groupe inexistant
        $command = new CreateActivityCommand(
            groupId: 99999,
            name: 'Rocket League',
            creatorUserId: $owner->getId(),
        );

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Group with ID 99999 not found.');

        $this->handler->handle($command);
    }
}
