<?php

namespace App\UI\Http\Controller\Entry;

use App\Repository\EntryRepository;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShowEntryController extends AbstractController
{
    #[Route('/groups/{groupId}/sessions/{sessionId}/entries/{entryId}', name: 'entry_show', methods: ['GET'])]
    public function __invoke(
        int $groupId,
        int $sessionId,
        int $entryId,
        EntryRepository $entryRepository,
    ): Response {
        $entry = $entryRepository->find($entryId);
        if (null === $entry) {
            throw $this->createNotFoundException('Entry not found');
        }

        // Multi-tenant validation: verify entry belongs to the correct group and session
        if ($entry->getGroup()->getId() !== $groupId || $entry->getSession()->getId() !== $sessionId) {
            throw $this->createAccessDeniedException('Entry not found');
        }

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $entry->getGroup());

        return $this->render('entry/show.html.twig', [
            'entry' => $entry,
            'group' => $entry->getGroup(),
            'session' => $entry->getSession(),
        ]);
    }
}
