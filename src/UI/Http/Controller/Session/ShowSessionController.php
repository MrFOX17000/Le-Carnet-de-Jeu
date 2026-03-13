<?php

namespace App\UI\Http\Controller\Session;

use App\Domain\Entry\EntryType;
use App\Entity\User;
use App\Repository\SessionRepository;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ShowSessionController extends AbstractController
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
    ) {
    }

    #[Route('/groups/{groupId}/sessions/{sessionId}', name: 'session_show', methods: ['GET'])]
    public function __invoke(int $groupId, int $sessionId): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Charger la session
        $session = $this->sessionRepository->find($sessionId);

        if (null === $session) {
            throw new NotFoundHttpException('Session not found.');
        }

        // Vérifier qu'elle appartient au bon groupe (sécurité multi-tenant)
        if ($session->getGroup()->getId() !== $groupId) {
            throw new NotFoundHttpException('Session does not belong to this group.');
        }

        $group = $session->getGroup();

        // Vérifier les droits d'accès au groupe
        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        $canManage = $this->isGranted(GroupVoter::MANAGE, $group);

        // Calcul des statistiques de la session
        $entries = $session->getEntries();
        $participantNames = [];
        $matchCount = 0;
        $toursCount = 0;

        foreach ($entries as $entry) {
            if ($entry->getType() === EntryType::MATCH && $entry->getEntryMatch() !== null) {
                ++$matchCount;
                $participantNames[$entry->getEntryMatch()->getHomeName()] = true;
                $participantNames[$entry->getEntryMatch()->getAwayName()] = true;
            } elseif ($entry->getType() === EntryType::SCORE_SIMPLE) {
                ++$toursCount;
                foreach ($entry->getScores() as $score) {
                    $participantNames[$score->getParticipantName()] = true;
                }
            }
        }

        $modeLabels = [
            'ranking'          => 'Classement libre',
            'duel'             => 'Duel 1v1',
            'duel_equipe'      => 'Match d\'équipes',
            'groupe_vs_externe' => 'Groupe vs Externe',
        ];
        $mode = $session->getActivity()->getContextMode()->value;

        $sessionOverview = [
            'entriesCount'      => $entries->count(),
            'participantsCount' => count($participantNames),
            'matchCount'        => $matchCount,
            'toursCount'        => $toursCount,
            'modeLabel'         => $modeLabels[$mode] ?? $mode,
            'isMatchMode'       => in_array($mode, ['duel', 'duel_equipe', 'groupe_vs_externe'], true),
        ];

        return $this->render('session/show.html.twig', [
            'session'         => $session,
            'group'           => $group,
            'canManage'       => $canManage,
            'sessionOverview' => $sessionOverview,
        ]);
    }
}
