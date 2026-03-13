<?php

namespace App\UI\Http\Controller\Entry;

use App\Repository\EntryRepository;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

final class DeleteEntryController extends AbstractController
{
    #[Route('/groups/{groupId}/sessions/{sessionId}/entries/{entryId}/delete', name: 'entry_delete', methods: ['POST'])]
    public function __invoke(
        Request $request,
        int $groupId,
        int $sessionId,
        int $entryId,
        EntryRepository $entryRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $entry = $entryRepository->find($entryId);
        if (null === $entry) {
            throw new NotFoundHttpException('Entry not found.');
        }

        if ($entry->getGroup()->getId() !== $groupId || $entry->getSession()->getId() !== $sessionId) {
            throw new NotFoundHttpException('Entry does not belong to this session.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::MANAGE, $entry->getGroup());

        if (!$this->isCsrfTokenValid('delete-entry-'.$entry->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide pour la suppression de l\'entrée.');

            return $this->redirectToRoute('entry_show', [
                'groupId' => $groupId,
                'sessionId' => $sessionId,
                'entryId' => $entryId,
            ]);
        }

        $entryLabel = $entry->getLabel() ?: 'cette entrée';

        $entityManager->remove($entry);
        $entityManager->flush();

        $this->addFlash('success', sprintf('L\'entrée %s a été supprimée.', $entryLabel));

        return $this->redirectToRoute('session_show', [
            'groupId' => $groupId,
            'sessionId' => $sessionId,
        ]);
    }
}