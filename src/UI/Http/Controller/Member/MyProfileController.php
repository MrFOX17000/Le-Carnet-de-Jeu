<?php

namespace App\UI\Http\Controller\Member;

use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\EntryRepository;
use App\Repository\GameGroupRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\InviteRepository;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MyProfileController extends AbstractController
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly InviteRepository $inviteRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly EntryRepository $entryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Route('/profil', name: 'my_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $action = $request->request->getString('profile_action');

            if ($action === 'update_display_name') {
                $this->handleDisplayNameUpdate($request, $user);
            }

            if ($action === 'update_email') {
                $this->handleEmailUpdate($request, $user);
            }

            if ($action === 'update_password') {
                $this->handlePasswordUpdate($request, $user);
            }

            if ($action === 'delete_account') {
                return $this->handleAccountDeletion($request, $user);
            }

            return $this->redirectToRoute('my_profile');
        }

        $groups = $this->groupRepository->findGroupsForUser($user->getId());
        $groupIds = array_map(static fn ($group): int => (int) $group->getId(), $groups);

        $ownedGroups = array_filter(
            $groups,
            static fn ($group): bool => $group->getCreatedBy()?->getId() === $user->getId()
        );

        $recentSessions = [];
        if ([] !== $groupIds) {
            $recentSessions = $this->sessionRepository->findRecentSessionsByGroupIds($groupIds, 8);
        }

        $groupsCreatedCount = $this->groupRepository->count(['createdBy' => $user]);
        $membershipsCount   = $this->groupMemberRepository->count(['user' => $user]);
        $activitiesCount    = $this->activityRepository->count(['createdBy' => $user]);
        $sessionsCount      = $this->sessionRepository->count(['createdBy' => $user]);
        $entriesCount       = $this->entryRepository->count(['createdBy' => $user]);
        $invitesCount       = $this->inviteRepository->count(['createdBy' => $user]);

        $stats = [
            'groupsCount' => count($groups),
            'ownedGroupsCount' => count($ownedGroups),
            'membershipsCount' => $user->getGroupMembers()->count(),
            'activitiesCreatedCount' => $activitiesCount,
            'sessionsCreatedCount' => $sessionsCount,
            'entriesCreatedCount' => $entriesCount,
            'invitesCreatedCount' => $user->getInvites()->count(),
        ];

        $recentActivityThreshold = new \DateTimeImmutable('-30 days');
        $sessionsLast30Days = count(array_filter(
            $recentSessions,
            static fn ($session): bool => $session->getPlayedAt() instanceof \DateTimeImmutable
                ? $session->getPlayedAt() >= $recentActivityThreshold
                : false,
        ));

        $pendingInvitesForUserCount = count($this->inviteRepository->findPendingInvites((string) $user->getEmail()));

        $deletionBlockers = [
            ['label' => 'Aucun groupe créé', 'ok' => $groupsCreatedCount === 0],
            ['label' => 'Pas membre de groupe', 'ok' => $membershipsCount === 0],
            ['label' => 'Aucune activité créée', 'ok' => $activitiesCount === 0],
            ['label' => 'Aucune session créée', 'ok' => $sessionsCount === 0],
            ['label' => 'Aucune entrée créée', 'ok' => $entriesCount === 0],
            ['label' => 'Aucune invitation envoyée', 'ok' => $invitesCount === 0],
        ];
        $canDeleteAccount = !in_array(false, array_column($deletionBlockers, 'ok'), true);

        $deletionChecksCompleted = count(array_filter(
            $deletionBlockers,
            static fn (array $blocker): bool => (bool) $blocker['ok'],
        ));

        $completionSteps = [
            $user->getDisplayName() ? 1 : 0,
            $user->isVerified() ? 1 : 0,
            count($groups) > 0 ? 1 : 0,
            $sessionsCount > 0 ? 1 : 0,
        ];
        $profileCompletionPercent = (int) round(array_sum($completionSteps) / count($completionSteps) * 100);

        $nextStep = null;
        if (count($groups) === 0) {
            $nextStep = [
                'label' => 'Créer votre premier groupe',
                'route' => 'group_create_form',
                'params' => [],
            ];
        } elseif ($sessionsCount === 0) {
            $firstGroup = $groups[0] ?? null;
            if (null !== $firstGroup && null !== $firstGroup->getId()) {
                $nextStep = [
                    'label' => 'Créer votre première session',
                    'route' => 'group_show',
                    'params' => ['id' => $firstGroup->getId()],
                ];
            }
        } elseif ($pendingInvitesForUserCount > 0) {
            $nextStep = [
                'label' => 'Traiter vos invitations',
                'route' => 'dashboard',
                'params' => [],
            ];
        }

        $profileInsights = [
            'pendingInvitesForUserCount' => $pendingInvitesForUserCount,
            'sessionsLast30Days' => $sessionsLast30Days,
            'profileCompletionPercent' => $profileCompletionPercent,
            'deletionChecksCompleted' => $deletionChecksCompleted,
            'deletionChecksTotal' => count($deletionBlockers),
            'nextStep' => $nextStep,
        ];

        return $this->render('member/my_profile.html.twig', [
            'userAccount' => $user,
            'stats' => $stats,
            'groups' => $groups,
            'recentSessions' => $recentSessions,
            'deletionBlockers' => $deletionBlockers,
            'canDeleteAccount' => $canDeleteAccount,
            'profileInsights' => $profileInsights,
        ]);
    }

    private function handleDisplayNameUpdate(Request $request, User $user): void
    {
        $submittedToken = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('profile-display-update', $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide pour la modification du nom affiché.');

            return;
        }

        $displayName = trim($request->request->getString('display_name'));

        if ($displayName !== '' && mb_strlen($displayName) < 2) {
            $this->addFlash('error', 'Le nom affiché doit contenir au moins 2 caractères.');

            return;
        }

        if (mb_strlen($displayName) > 80) {
            $this->addFlash('error', 'Le nom affiché ne peut pas dépasser 80 caractères.');

            return;
        }

        $user->setDisplayName($displayName === '' ? null : $displayName);
        $this->entityManager->flush();
        $this->addFlash('success', 'Votre nom affiché a été mis à jour.');
    }

    private function handleEmailUpdate(Request $request, User $user): void
    {
        $submittedToken = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('profile-email-update', $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide pour la modification de l\'email.');

            return;
        }

        $newEmail = trim($request->request->getString('new_email'));
        $currentPassword = $request->request->getString('current_password_for_email');

        if ($newEmail === '') {
            $this->addFlash('error', 'Le nouvel email est obligatoire.');

            return;
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Le format de l\'email est invalide.');

            return;
        }

        if (strtolower($newEmail) === strtolower((string) $user->getEmail())) {
            $this->addFlash('error', 'Le nouvel email est identique à l\'email actuel.');

            return;
        }

        $canSkipCurrentPassword = null !== $user->getOauthProvider();
        if (!$canSkipCurrentPassword && !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Mot de passe actuel incorrect.');

            return;
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $newEmail]);
        if ($existingUser instanceof User) {
            $this->addFlash('error', 'Cet email est déjà utilisé.');

            return;
        }

        $user->setEmail($newEmail);
        $this->entityManager->flush();
        $this->addFlash('success', 'Votre email a été mis à jour.');
    }

    private function handlePasswordUpdate(Request $request, User $user): void
    {
        $submittedToken = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('profile-password-update', $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide pour la modification du mot de passe.');

            return;
        }

        $currentPassword = $request->request->getString('current_password');
        $newPassword = $request->request->getString('new_password');
        $confirmPassword = $request->request->getString('confirm_password');

        $canSkipCurrentPassword = null !== $user->getOauthProvider();
        if (!$canSkipCurrentPassword && !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Mot de passe actuel incorrect.');

            return;
        }

        if (strlen($newPassword) < 8) {
            $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');

            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'La confirmation du mot de passe ne correspond pas.');

            return;
        }

        $hashed = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashed);
        $this->entityManager->flush();
        $this->addFlash('success', 'Votre mot de passe a été mis à jour.');
    }

    private function handleAccountDeletion(Request $request, User $user): Response
    {
        $submittedToken = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('profile-account-delete', $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide pour la suppression du compte.');

            return $this->redirectToRoute('my_profile');
        }

        $confirmation = trim($request->request->getString('delete_confirmation'));
        if ($confirmation !== 'SUPPRIMER') {
            $this->addFlash('error', 'Tapez SUPPRIMER pour confirmer la suppression de compte.');

            return $this->redirectToRoute('my_profile');
        }

        $currentPassword = $request->request->getString('current_password_for_delete');
        $canSkipCurrentPassword = null !== $user->getOauthProvider();
        if (!$canSkipCurrentPassword && !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Mot de passe actuel incorrect.');

            return $this->redirectToRoute('my_profile');
        }

        $blocking = [];

        if ($this->groupRepository->count(['createdBy' => $user]) > 0) {
            $blocking[] = 'vous êtes créateur d\'au moins un groupe';
        }

        if ($this->groupMemberRepository->count(['user' => $user]) > 0) {
            $blocking[] = 'vous appartenez encore à des groupes';
        }

        if ($this->activityRepository->count(['createdBy' => $user]) > 0) {
            $blocking[] = 'vous avez créé des activités';
        }

        if ($this->sessionRepository->count(['createdBy' => $user]) > 0) {
            $blocking[] = 'vous avez créé des sessions';
        }

        if ($this->entryRepository->count(['createdBy' => $user]) > 0) {
            $blocking[] = 'vous avez créé des entrées';
        }

        if ($this->inviteRepository->count(['createdBy' => $user]) > 0) {
            $blocking[] = 'vous avez envoyé des invitations';
        }

        if ($blocking !== []) {
            $this->addFlash('error', 'Suppression refusée: ' . implode(', ', $blocking) . '. Nettoyez vos données avant suppression.');

            return $this->redirectToRoute('my_profile');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        return $this->redirectToRoute('app_home');
    }
}
