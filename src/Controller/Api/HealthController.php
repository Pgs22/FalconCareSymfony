<?php

namespace App\Controller\Api;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/health')]
#[OA\Tag(name: 'Health')]
final class HealthController extends AbstractController
{
    #[Route('', name: 'api_health', methods: ['GET'])]
    #[OA\Get(
        path: '/api/health',
        summary: 'Health check',
        description: 'Public health endpoint for the frontend.',
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
        ], Response::HTTP_OK);
    }
}

