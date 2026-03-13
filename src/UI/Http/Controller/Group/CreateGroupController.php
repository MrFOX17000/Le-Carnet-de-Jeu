<?php

namespace App\UI\Http\Controller\Group;


use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Application\Group\CreateGroup\CreateGroupHandler;
use App\Application\Group\CreateGroup\CreateGroupCommand;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/groups/create')]
final class CreateGroupController extends AbstractController
{
    public function __construct(private readonly CreateGroupHandler $createGroupHandler) {}

    #[Route('', name: 'group_create_form', methods: ['GET'])]
    public function show(): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('group/create.html.twig');
    }

    #[Route('', name: 'group_create_submit', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $user = $this->getUser();
        $creatorUserId = $user instanceof User
            ? (int) $user->getId()
            : (int) $request->request->get('creatorUserId', 0);

        if ($creatorUserId <= 0) {
            return $this->redirectToRoute('app_login');
        }

        $name = trim((string) $request->request->get('name', ''));

        if ($name === '') {
            return $this->render('group/create.html.twig', [
                'error' => 'Le nom du groupe est obligatoire.',
                'name' => '',
            ]);
        }

        try {
            $command = new CreateGroupCommand(name: $name, creatorUserId: $creatorUserId);
            $result = $this->createGroupHandler->handle($command);
        } catch (\Throwable) {
            return $this->render('group/create.html.twig', [
                'error' => 'Impossible de creer le groupe pour le moment.',
                'name' => $name,
            ]);
        }

        return $this->redirectToRoute('group_show', ['id' => $result->getGroupId()]);
    }
}