<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HelloController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/hello', name: 'app_hello')]
    public function index(Request $request): Response
    {
        $variant = $request->query->get('variant', 'classic');
        $allowedVariants = ['classic', 'gaming'];
        if (!in_array($variant, $allowedVariants, true)) {
            $variant = 'classic';
        }

        return $this->render('hello/index.html.twig', [
            'controller_name' => 'HelloController',
            'variant' => $variant,
        ]);
    }
}
