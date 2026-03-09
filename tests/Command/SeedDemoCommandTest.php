<?php

namespace App\Tests\Command;

use App\Domain\Entry\EntryType;
use App\Entity\Entry;
use App\Entity\GameGroup;
use App\Entity\Invite;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedDemoCommandTest extends DbWebTestCase
{
    public function testSeedDemoCommandCreatesDatasetAndIsIdempotent(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        $command = $application->find('app:seed-demo');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);
        self::assertSame(Command::SUCCESS, $exitCode);

        $em = static::getContainer()->get(EntityManagerInterface::class);

        self::assertSame(1, $em->getRepository(User::class)->count(['email' => 'demo@local.test']));
        self::assertSame(1, $em->getRepository(User::class)->count(['email' => 'demo.member@local.test']));

        self::assertSame(1, $em->getRepository(GameGroup::class)->count(['name' => 'Demo Group - Board Games']));
        self::assertSame(1, $em->getRepository(GameGroup::class)->count(['name' => 'Demo Group - Versus Arena']));

        self::assertSame(1, $em->getRepository(Session::class)->count(['title' => 'Friday Blitz #1']));
        self::assertSame(1, $em->getRepository(Session::class)->count(['title' => 'Catan Night #1']));
        self::assertSame(1, $em->getRepository(Session::class)->count(['title' => 'Kart Cup #1']));

        self::assertSame(1, $em->getRepository(Entry::class)->count([
            'type' => EntryType::SCORE_SIMPLE,
            'label' => 'Scoreboard - Round Robin',
        ]));
        self::assertSame(1, $em->getRepository(Entry::class)->count([
            'type' => EntryType::MATCH,
            'label' => 'Final BO1',
        ]));

        $group = $em->getRepository(GameGroup::class)->findOneBy(['name' => 'Demo Group - Versus Arena']);
        self::assertNotNull($group);

        $invite = $em->getRepository(Invite::class)->findOneBy([
            'group' => $group,
            'email' => 'pending.demo@local.test',
            'acceptedAt' => null,
        ]);
        self::assertNotNull($invite);

        $secondRunExitCode = $tester->execute([]);
        self::assertSame(Command::SUCCESS, $secondRunExitCode);

        self::assertSame(1, $em->getRepository(User::class)->count(['email' => 'demo@local.test']));
        self::assertSame(1, $em->getRepository(User::class)->count(['email' => 'demo.member@local.test']));
        self::assertSame(1, $em->getRepository(GameGroup::class)->count(['name' => 'Demo Group - Board Games']));
        self::assertSame(1, $em->getRepository(GameGroup::class)->count(['name' => 'Demo Group - Versus Arena']));
    }
}
