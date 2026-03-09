<?php

namespace App\Tests\Application\Invite\AcceptInvite;

use App\Application\Invite\AcceptInvite\AcceptInviteCommand;
use App\Application\Invite\AcceptInvite\AcceptInviteHandler;
use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Invite;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class AcceptInviteHandlerTest extends DbWebTestCase
{
    private AcceptInviteHandler $handler;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->handler = static::getContainer()->get(AcceptInviteHandler::class);
    }

    public function testValidInvitationAddsUserToGroup(): void
    {
        // Créer les utilisateurs
        $creator = new User();
        $creator->setEmail('creator@test.local');
        $creator->setPassword('dummy');
        $creator->setCreatedAt(new \DateTimeImmutable());

        $invitee = new User();
        $invitee->setEmail('invitee@test.local');
        $invitee->setPassword('dummy');
        $invitee->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($creator);
        $this->em->persist($invitee);
        $this->em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($creator);

        $this->em->persist($group);
        $this->em->flush();

        // Créer une invitation
        $invite = new Invite(GroupRole::MEMBER);
        $invite->setEmail('invitee@test.local');
        $invite->setToken(bin2hex(random_bytes(32)));
        $invite->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $invite->setGroup($group);
        $invite->setCreatedBy($creator);

        $this->em->persist($invite);
        $this->em->flush();

        // Accepter l'invitation
        $command = new AcceptInviteCommand(
            token: $invite->getToken(),
            userId: $invitee->getId(),
        );

        $result = $this->handler->handle($command);

        // Vérifier que l'utilisateur est membre du groupe
        $this->em->clear();

        $membership = $this->em->getRepository(GroupMember::class)
            ->findOneBy([
                'group' => $result->getGroupId(),
                'user' => $invitee->getId(),
            ]);

        self::assertNotNull($membership);
        self::assertEquals(GroupRole::MEMBER, $membership->getRole());

        // Vérifier que l'invitation est marquée comme acceptée
        $acceptedInvite = $this->em->getRepository(Invite::class)
            ->find($invite->getId());

        self::assertNotNull($acceptedInvite->getAcceptedAt());
    }

    public function testExpiredInvitationIsRejected(): void
    {
        // Créer les utilisateurs
        $creator = new User();
        $creator->setEmail('creator@test.local');
        $creator->setPassword('dummy');
        $creator->setCreatedAt(new \DateTimeImmutable());

        $invitee = new User();
        $invitee->setEmail('invitee@test.local');
        $invitee->setPassword('dummy');
        $invitee->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($creator);
        $this->em->persist($invitee);
        $this->em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($creator);

        $this->em->persist($group);
        $this->em->flush();

        // Créer une invitation EXPIRÉE
        $invite = new Invite(GroupRole::MEMBER);
        $invite->setEmail('invitee@test.local');
        $invite->setToken(bin2hex(random_bytes(32)));
        $invite->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $invite->setGroup($group);
        $invite->setCreatedBy($creator);

        $this->em->persist($invite);
        $this->em->flush();

        // Essayer d'accepter l'invitation expirée
        $command = new AcceptInviteCommand(
            token: $invite->getToken(),
            userId: $invitee->getId(),
        );

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invitation has expired.');

        $this->handler->handle($command);
    }

    public function testAlreadyAcceptedInvitationIsRejected(): void
    {
        // Créer les utilisateurs
        $creator = new User();
        $creator->setEmail('creator@test.local');
        $creator->setPassword('dummy');
        $creator->setCreatedAt(new \DateTimeImmutable());

        $invitee = new User();
        $invitee->setEmail('invitee@test.local');
        $invitee->setPassword('dummy');
        $invitee->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($creator);
        $this->em->persist($invitee);
        $this->em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($creator);

        $this->em->persist($group);
        $this->em->flush();

        // Créer une invitation DÉJÀ ACCEPTÉE
        $invite = new Invite(GroupRole::MEMBER);
        $invite->setEmail('invitee@test.local');
        $invite->setToken(bin2hex(random_bytes(32)));
        $invite->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $invite->setAcceptedAt(new \DateTimeImmutable());
        $invite->setGroup($group);
        $invite->setCreatedBy($creator);

        $this->em->persist($invite);
        $this->em->flush();

        // Essayer d'accepter l'invitation
        $command = new AcceptInviteCommand(
            token: $invite->getToken(),
            userId: $invitee->getId(),
        );

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invitation has already been accepted.');

        $this->handler->handle($command);
    }

    public function testAlreadyMemberCannotAcceptInvitation(): void
    {
        // Créer les utilisateurs
        $creator = new User();
        $creator->setEmail('creator@test.local');
        $creator->setPassword('dummy');
        $creator->setCreatedAt(new \DateTimeImmutable());

        $member = new User();
        $member->setEmail('member@test.local');
        $member->setPassword('dummy');
        $member->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($creator);
        $this->em->persist($member);
        $this->em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($creator);

        $this->em->persist($group);
        $this->em->flush();

        // Ajouter l'utilisateur comme membre
        $membership = new GroupMember(GroupRole::MEMBER);
        $membership->setUser($member);
        $membership->setGroup($group);

        $this->em->persist($membership);
        $this->em->flush();

        // Créer une invitation
        $invite = new Invite(GroupRole::MEMBER);
        $invite->setEmail('member@test.local');
        $invite->setToken(bin2hex(random_bytes(32)));
        $invite->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $invite->setGroup($group);
        $invite->setCreatedBy($creator);

        $this->em->persist($invite);
        $this->em->flush();

        // Essayer d'accepter l'invitation
        $command = new AcceptInviteCommand(
            token: $invite->getToken(),
            userId: $member->getId(),
        );

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('User is already a member of this group.');

        $this->handler->handle($command);
    }
}
