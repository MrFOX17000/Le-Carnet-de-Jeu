<?php

namespace App\Tests\Security;

use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class EditActivityTest extends DbWebTestCase
{
    public function testOwnerCanEditActivity(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $activity] = $this->createGroupWithActivity($em);

        $client->loginUser($owner);
        $client->request('POST', '/groups/'.$group->getId().'/activities/'.$activity->getId().'/edit', [
            'name' => 'Skyjo tournoi',
        ]);

        self::assertResponseRedirects('/groups/'.$group->getId().'/activities');

        $em->clear();
        $updatedActivity = $em->getRepository(Activity::class)->find($activity->getId());

        self::assertSame('Skyjo tournoi', $updatedActivity?->getName());
    }

    /**
     * @return array{0: User, 1: GameGroup, 2: Activity}
     */
    private function createGroupWithActivity(EntityManagerInterface $em): array
    {
        $owner = new User();
        $owner->setEmail('edit-activity-owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        $group = new GameGroup();
        $group->setName('Edit Activity Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);
        $em->persist($group);

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);
        $em->persist($membership);

        $activity = new Activity();
        $activity->setName('Skyjo');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $group->addActivity($activity);
        $em->persist($activity);
        $em->flush();

        return [$owner, $group, $activity];
    }
}