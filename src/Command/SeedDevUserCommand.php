<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-dev-user',
    description: 'Creates a dev user if it does not exist (dev@local.test)',
)]
class SeedDevUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = 'dev@local.test';
        $plainPassword = 'devpass';

        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if ($existing) {
            $io->success(sprintf('Dev user already exists: %s (id=%d)', $email, $existing->getId()));
            return Command::SUCCESS;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Dev user created: %s (id=%d, password=%s)', $email, $user->getId(), $plainPassword));

        return Command::SUCCESS;
    }
}
