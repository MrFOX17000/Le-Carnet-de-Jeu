<?php

namespace App\UI\Http\Controller\Group;


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
        return $this->render('group/create.html.twig');
    }

    #[Route('', name: 'group_create_submit', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $creatorUserId = (int) $request->request->get('creatorUserId', 1);
        $name = trim((string) $request->request->get('name', ''));

        if ($name === '') {
            return $this->render('group/create.html.twig', [
                'error' => 'Le nom du groupe est obligatoire.',
                'name' => '',
            ]);
        }

        $command = new CreateGroupCommand(name: $name, creatorUserId: $creatorUserId);
        $result = $this->createGroupHandler->handle($command);

        return $this->redirectToRoute('group_show', ['id' => $result->getGroupId()]);
    }
}