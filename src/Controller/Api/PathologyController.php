<?php

namespace App\Controller\Api;

use App\Entity\Pathology;
use App\Entity\PathologyType;
use App\Repository\PathologyRepository;
use App\Repository\PathologyTypeRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/pathologies')]
#[OA\Tag(name: 'Pathologies')]
final class PathologyController extends AbstractController
{
    public function __construct(
        private readonly PathologyRepository $pathologyRepository,
        private readonly PathologyTypeRepository $pathologyTypeRepository,
    ) {
    }

    #[Route('', name: 'api_pathology_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/pathologies',
        summary: 'List pathologies for the odontogram',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Pathology list')]
    )]
    public function list(): JsonResponse
    {
        $pathologies = $this->pathologyRepository->findBy([], ['description' => 'ASC']);

        $data = array_map(
            static fn (Pathology $pathology): array => [
                'id' => $pathology->getId(),
                'description' => $pathology->getDescription(),
                'protocolColor' => $pathology->getProtocolColor(),
                'visualType' => $pathology->getVisualType(),
                'pathologyType' => [
                    'id' => $pathology->getPathologyType()?->getId(),
                    'name' => $pathology->getPathologyType()?->getName(),
                ],
            ],
            $pathologies
        );

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/types', name: 'api_pathology_type_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/pathologies/types',
        summary: 'List pathology types for the odontogram protocol',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Pathology type list')]
    )]
    public function listTypes(): JsonResponse
    {
        $pathologyTypes = $this->pathologyTypeRepository->findBy([], ['name' => 'ASC']);

        $data = array_map(
            static fn (PathologyType $pathologyType): array => [
                'id' => $pathologyType->getId(),
                'name' => $pathologyType->getName(),
                'defaultDuration' => $pathologyType->getDefaultDuration(),
            ],
            $pathologyTypes
        );

        return $this->json($data, Response::HTTP_OK);
    }
}
