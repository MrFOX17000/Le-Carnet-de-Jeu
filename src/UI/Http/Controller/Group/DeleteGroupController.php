<?php

namespace App\UI\Http\Controller\Group;

use App\Entity\GameGroup;
use App\Entity\User;
use App\Security\Voter\GroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteGroupController extends AbstractController
{
    #[Route('/groups/{id}/delete', name: 'group_delete', methods: ['POST'])]
    public function __invoke(Request $request, GameGroup $group, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted(GroupVoter::MANAGE, $group);

        if (!$this->isCsrfTokenValid('delete-group-'.$group->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide pour la suppression du groupe.');

            return $this->redirectToRoute('group_show', [
                'id' => $group->getId(),
            ]);
        }

        $groupName = $group->getName() ?? 'ce groupe';

        $entityManager->remove($group);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Le groupe %s a été supprimé.', $groupName));

        return $this->redirectToRoute('group_index');
    }
}