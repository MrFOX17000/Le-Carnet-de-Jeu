<?php

namespace App\Tests\UI\Entry;

use App\Domain\Entry\EntryType;
use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\Entry;
use App\Entity\EntryScore;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteEntryControllerTest extends DbWebTestCase
{
    public function testOwnerCanDeleteEntry(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $session, $entry] = $this->createGroupSessionAndEntry($em, 'entry-delete-owner@test.local');

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/groups/'.$group->getId().'/sessions/'.$session->getId().'/entries/'.$entry->getId());
        $token = $crawler->filter('form[action="/groups/'.$group->getId().'/sessions/'.$session->getId().'/entries/'.$entry->getId().'/delete"] input[name="_token"]')->attr('value');

        $client->request('POST', '/groups/'.$group->getId().'/sessions/'.$session->getId().'/entries/'.$entry->getId().'/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/groups/'.$group->getId().'/sessions/'.$session->getId());
        self::assertNull($em->getRepository(Entry::class)->find($entry->getId()));
    }

    public function testNonMemberCannotDeleteEntry(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $session, $entry] = $this->createGroupSessionAndEntry($em, 'entry-delete-protected@test.local');

        $nonMember = new User();
        $nonMember->setEmail('entry-delete-outsider@test.local');
        $nonMember->setPassword('hashed');
        $nonMember->setCreatedAt(new \DateTimeImmutable());
        $em->persist($nonMember);
        $em->flush();

        $client->loginUser($nonMember);
        $client->request('POST', '/groups/'.$group->getId().'/sessions/'.$session->getId().'/entries/'.$entry->getId().'/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($em->getRepository(Entry::class)->find($entry->getId()));
    }

    /**
     * @return array{0: User, 1: GameGroup, 2: Session, 3: Entry}
     */
    private function createGroupSessionAndEntry(EntityManagerInterface $em, string $ownerEmail): array
    {
        $owner = new User();
        $owner->setEmail($ownerEmail);
        $owner->setPassword('hashed');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        $group = new GameGroup();
        $group->setName('Delete Entry Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());
        $em->persist($group);

        $membership = new GroupMember(GroupRole::OWNER);
        $membership->setGroup($group);
        $membership->setUser($owner);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);
        $em->persist($membership);

        $activity = new Activity();
        $activity->setName('Delete Entry Activity');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $em->persist($activity);

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-10 20:30:00'));
        $session->setCreatedBy($owner);
        $group->addSession($session);
        $activity->addSession($session);
        $em->persist($session);

        $entry = new Entry(EntryType::SCORE_SIMPLE);
        $entry->setSession($session);
        $entry->setGroup($group);
        $entry->setCreatedBy($owner);
        $entry->setLabel('Manche à supprimer');
        $session->addEntry($entry);

        $score = new EntryScore();
        $score->setParticipantName('Alice');
        $score->setScore(12.0);
        $entry->addScore($score);

        $em->persist($entry);
        $em->flush();

        return [$owner, $group, $session, $entry];
    }
}