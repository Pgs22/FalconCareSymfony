<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OdontogramController extends AbstractController
{
    #[Route('/odontogram', name: 'app_odontogram')]
    public function index(): Response
    {
        return $this->render('odontogram/index.html.twig', [
            'controller_name' => 'OdontogramController',
        ]);
    }
}
