<?php

namespace App\Command;

use App\Domain\Entry\EntryType;
use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\Entry;
use App\Entity\EntryMatch;
use App\Entity\EntryScore;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Invite;
use App\Entity\Session;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-demo',
    description: 'Creates a demo dataset (users, groups, activities, sessions, entries, pending invite).',
)]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $owner = $this->createOrGetUser('demo@local.test', 'demo1234');
        $member = $this->createOrGetUser('demo.member@local.test', 'demo1234');

        $groupA = $this->createOrGetGroup('Demo Group - Board Games', $owner);
        $groupB = $this->createOrGetGroup('Demo Group - Versus Arena', $owner);

        $this->ensureMembership($groupA, $owner, GroupRole::OWNER);
        $this->ensureMembership($groupB, $owner, GroupRole::OWNER);
        $this->ensureMembership($groupA, $member, GroupRole::MEMBER);
        $this->ensureMembership($groupB, $member, GroupRole::MEMBER);

        $activityA1 = $this->createOrGetActivity($groupA, $owner, 'Chess Blitz');
        $activityA2 = $this->createOrGetActivity($groupA, $owner, 'Catan');
        $activityB1 = $this->createOrGetActivity($groupB, $owner, 'Mario Kart');

        $sessionA1 = $this->createOrGetSession(
            $groupA,
            $activityA1,
            $owner,
            'Friday Blitz #1',
            new \DateTimeImmutable('-2 days')
        );
        $sessionA2 = $this->createOrGetSession(
            $groupA,
            $activityA2,
            $owner,
            'Catan Night #1',
            new \DateTimeImmutable('-1 day')
        );
        $sessionB1 = $this->createOrGetSession(
            $groupB,
            $activityB1,
            $owner,
            'Kart Cup #1',
            new \DateTimeImmutable('-3 days')
        );

        $this->createOrGetScoreEntry(
            $groupA,
            $sessionA1,
            $owner,
            'Scoreboard - Round Robin',
            [
                ['name' => 'Demo Owner', 'score' => 12.0, 'user' => $owner],
                ['name' => 'Demo Member', 'score' => 9.0, 'user' => $member],
            ]
        );

        $this->createOrGetScoreEntry(
            $groupA,
            $sessionA2,
            $owner,
            'Scoreboard - Resource Race',
            [
                ['name' => 'Demo Owner', 'score' => 8.0, 'user' => $owner],
                ['name' => 'Demo Member', 'score' => 10.0, 'user' => $member],
            ]
        );

        $this->createOrGetMatchEntry(
            $groupB,
            $sessionB1,
            $owner,
            'Final BO1',
            'Demo Owner',
            'Demo Member',
            3,
            2,
            $owner,
            $member
        );

        $this->createOrGetPendingInvite(
            $groupB,
            $owner,
            'pending.demo@local.test',
            GroupRole::MEMBER
        );

        $this->entityManager->flush();

        $io->success('Demo dataset is ready.');
        $io->writeln('Users: demo@local.test / demo1234, demo.member@local.test / demo1234');
        $io->writeln('Groups: Demo Group - Board Games, Demo Group - Versus Arena');
        $io->writeln('Entries: score_simple + match seeded');
        $io->writeln('Pending invite: pending.demo@local.test');

        return Command::SUCCESS;
    }

    private function createOrGetUser(string $email, string $plainPassword): User
    {
        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if ($existing instanceof User) {
            return $existing;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);

        return $user;
    }

    private function createOrGetGroup(string $name, User $owner): GameGroup
    {
        $repository = $this->entityManager->getRepository(GameGroup::class);
        $existing = $repository->findOneBy([
            'name' => $name,
            'createdBy' => $owner,
        ]);

        if ($existing instanceof GameGroup) {
            return $existing;
        }

        $group = new GameGroup();
        $group->setName($name);
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $this->entityManager->persist($group);

        return $group;
    }

    private function ensureMembership(GameGroup $group, User $user, GroupRole $role): GroupMember
    {
        $repository = $this->entityManager->getRepository(GroupMember::class);
        $existing = $repository->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);

        if ($existing instanceof GroupMember) {
            if ($existing->getRole() !== $role) {
                $existing->setRole($role);
            }

            return $existing;
        }

        $membership = new GroupMember($role);
        $membership->setGroup($group);
        $membership->setUser($user);

        $group->addGroupMember($membership);
        $user->addGroupMember($membership);

        $this->entityManager->persist($membership);

        return $membership;
    }

    private function createOrGetActivity(GameGroup $group, User $creator, string $name): Activity
    {
        $repository = $this->entityManager->getRepository(Activity::class);
        $existing = $repository->findOneBy([
            'group' => $group,
            'name' => $name,
        ]);

        if ($existing instanceof Activity) {
            return $existing;
        }

        $activity = new Activity();
        $activity->setName($name);
        $activity->setGroup($group);
        $activity->setCreatedBy($creator);

        $group->addActivity($activity);

        $this->entityManager->persist($activity);

        return $activity;
    }

    private function createOrGetSession(
        GameGroup $group,
        Activity $activity,
        User $creator,
        string $title,
        \DateTimeImmutable $playedAt,
    ): Session {
        $repository = $this->entityManager->getRepository(Session::class);
        $existing = $repository->findOneBy([
            'group' => $group,
            'title' => $title,
        ]);

        if ($existing instanceof Session) {
            return $existing;
        }

        $session = new Session();
        $session->setGroup($group);
        $session->setActivity($activity);
        $session->setCreatedBy($creator);
        $session->setTitle($title);
        $session->setPlayedAt($playedAt);

        $group->addSession($session);
        $activity->addSession($session);

        $this->entityManager->persist($session);

        return $session;
    }

    /**
     * @param list<array{name:string,score:float,user:?User}> $scores
     */
    private function createOrGetScoreEntry(
        GameGroup $group,
        Session $session,
        User $creator,
        string $label,
        array $scores,
    ): Entry {
        $repository = $this->entityManager->getRepository(Entry::class);
        $existing = $repository->findOneBy([
            'session' => $session,
            'type' => EntryType::SCORE_SIMPLE,
            'label' => $label,
        ]);

        if ($existing instanceof Entry) {
            return $existing;
        }

        $entry = new Entry(EntryType::SCORE_SIMPLE);
        $entry->setGroup($group);
        $entry->setSession($session);
        $entry->setCreatedBy($creator);
        $entry->setLabel($label);

        foreach ($scores as $data) {
            $entryScore = new EntryScore();
            $entryScore->setParticipantName($data['name']);
            $entryScore->setScore($data['score']);
            $entryScore->setUser($data['user']);
            $entry->addScore($entryScore);
        }

        $session->addEntry($entry);

        $this->entityManager->persist($entry);

        return $entry;
    }

    private function createOrGetMatchEntry(
        GameGroup $group,
        Session $session,
        User $creator,
        string $label,
        string $homeName,
        string $awayName,
        int $homeScore,
        int $awayScore,
        ?User $homeUser,
        ?User $awayUser,
    ): Entry {
        $repository = $this->entityManager->getRepository(Entry::class);
        $existing = $repository->findOneBy([
            'session' => $session,
            'type' => EntryType::MATCH,
            'label' => $label,
        ]);

        if ($existing instanceof Entry) {
            return $existing;
        }

        $entry = new Entry(EntryType::MATCH);
        $entry->setGroup($group);
        $entry->setSession($session);
        $entry->setCreatedBy($creator);
        $entry->setLabel($label);

        $entryMatch = new EntryMatch();
        $entryMatch->setHomeName($homeName);
        $entryMatch->setAwayName($awayName);
        $entryMatch->setHomeScore($homeScore);
        $entryMatch->setAwayScore($awayScore);
        $entryMatch->setHomeUser($homeUser);
        $entryMatch->setAwayUser($awayUser);
        $entry->setEntryMatch($entryMatch);

        $session->addEntry($entry);

        $this->entityManager->persist($entry);

        return $entry;
    }

    private function createOrGetPendingInvite(GameGroup $group, User $creator, string $email, GroupRole $role): Invite
    {
        $repository = $this->entityManager->getRepository(Invite::class);
        $existing = $repository->findOneBy([
            'group' => $group,
            'email' => $email,
            'acceptedAt' => null,
        ]);

        if ($existing instanceof Invite && $existing->getExpiresAt() > new \DateTimeImmutable()) {
            return $existing;
        }

        $invite = new Invite($role);
        $invite->setGroup($group);
        $invite->setCreatedBy($creator);
        $invite->setEmail($email);
        $invite->setToken(bin2hex(random_bytes(16)));
        $invite->setExpiresAt(new \DateTimeImmutable('+7 days'));

        $group->addInvite($invite);
        $creator->addInvite($invite);

        $this->entityManager->persist($invite);

        return $invite;
    }
}
