<?php

namespace App\UI\Http\Controller\Dashboard;

use App\Application\Dashboard\GetDashboardData\GetDashboardDataHandler;
use App\Application\Dashboard\GetDashboardData\GetDashboardDataQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly GetDashboardDataHandler $getDashboardDataHandler,
    ) {}

    #[Route('/dashboard', name: 'dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (null === $user) {
            throw $this->createAccessDeniedException('User not authenticated');
        }

        $query = new GetDashboardDataQuery($user->getId());
        $result = $this->getDashboardDataHandler->handle($query);

        // Build roles map for easier template access
        $groupRoles = [];
        foreach ($result->getGroups() as $group) {
            $role = $result->getRoleForGroup($group->getId());
            if ($role) {
                $groupRoles[$group->getId()] = $role;
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'groups' => $result->getGroups(),
            'groupRoles' => $groupRoles,
            'recentSessions' => $result->getRecentSessions(),
            'pendingInvites' => $result->getPendingInvites(),
            'memberStats' => $result->getMemberStats(),
            'activityStats' => $result->getActivityStats(),
            'dashboardStats' => $result->getDashboardStats(),
        ]);
    }
}


