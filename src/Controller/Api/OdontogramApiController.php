<?php

namespace App\Controller\Api;

use App\Entity\Odontogram;
use App\Entity\OdontogramaDetail;
use App\Entity\ToothFace;
use App\Repository\AppointmentRepository;
use App\Repository\OdontogramRepository;
use App\Repository\OdontogramaDetailRepository;
use App\Repository\PathologyRepository;
use App\Repository\TreatmentRepository;
use App\Repository\ToothFaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/odontograms')]
final class OdontogramApiController extends AbstractController
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly OdontogramRepository $odontogramRepository,
        private readonly OdontogramaDetailRepository $odontogramaDetailRepository,
        private readonly TreatmentRepository $treatmentRepository,
        private readonly PathologyRepository $pathologyRepository,
        private readonly ToothFaceRepository $toothFaceRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_odontogram_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $visitId = $data['visit_id'] ?? null;
        $treatmentId = $data['treatment_id'] ?? null;

        if (!$visitId || !$treatmentId) {
            return $this->json([
                'error' => 'visit_id and treatment_id are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $appointment = $this->appointmentRepository->find($visitId);
        if (!$appointment) {
            return $this->json(['error' => 'Appointment not found'], Response::HTTP_NOT_FOUND);
        }

        $treatment = $this->treatmentRepository->find($treatmentId);
        if (!$treatment) {
            return $this->json(['error' => 'Treatment not found'], Response::HTTP_NOT_FOUND);
        }

        $existingOdontogram = $this->odontogramRepository->findOneBy([
            'visit' => $appointment,
            'treatment' => $treatment,
        ]);

        if ($existingOdontogram) {
            return $this->json([
                'message' => 'Odontogram already exists for this visit and treatment',
                'id' => $existingOdontogram->getId(),
                'status' => $existingOdontogram->getStatus(),
            ], Response::HTTP_OK);
        }

        $odontogram = new Odontogram();
        $odontogram->setVisit($appointment);
        $odontogram->setTreatment($treatment);
        $odontogram->setStatus('Pendiente');

        $this->entityManager->persist($odontogram);
        $this->entityManager->flush();

        $treatment->setLastOdontogramId($odontogram->getId());
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Odontogram created successfully',
            'id' => $odontogram->getId(),
            'status' => $odontogram->getStatus(),
            'visit_id' => $appointment->getId(),
            'treatment_id' => $treatment->getId(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_odontogram_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function findById(int $id): JsonResponse
    {
        $odontogram = $this->odontogramRepository->find($id);

        if (!$odontogram) {
            return $this->json(['message' => 'Odontogram not found'], Response::HTTP_NOT_FOUND);
        }

        $details = [];
        foreach ($odontogram->getOdontogramaDetails() as $detail) {
            $faces = [];
            foreach ($detail->getToothFaces() as $face) {
                $faces[] = [
                    'id' => $face->getId(),
                    'face_name' => $face->getFaceName(),
                ];
            }

            $details[] = [
                'id' => $detail->getId(),
                'tooth_number' => $detail->getToothNumber(),
                'pathology' => [
                    'id' => $detail->getPathology()?->getId(),
                    'description' => $detail->getPathology()?->getDescription(),
                    'protocol_color' => $detail->getPathology()?->getProtocolColor(),
                    'visual_type' => $detail->getPathology()?->getVisualType(),
                ],
                'faces' => $faces,
            ];
        }

        return $this->json([
            'id' => $odontogram->getId(),
            'status' => $odontogram->getStatus(),
            'visit_id' => $odontogram->getVisit()?->getId(),
            'treatment_id' => $odontogram->getTreatment()?->getId(),
            'details' => $details,
        ], Response::HTTP_OK);
    }

    #[Route('/by-visit/{visitId}/treatment/{treatmentId}', name: 'api_odontogram_by_visit_and_treatment', requirements: ['visitId' => '\d+', 'treatmentId' => '\d+'], methods: ['GET'])]
    public function findByVisitAndTreatment(int $visitId, int $treatmentId): JsonResponse
    {
        $appointment = $this->appointmentRepository->find($visitId);
        if (!$appointment) {
            return $this->json(['message' => 'Appointment not found'], Response::HTTP_NOT_FOUND);
        }

        $treatment = $this->treatmentRepository->find($treatmentId);
        if (!$treatment) {
            return $this->json(['message' => 'Treatment not found'], Response::HTTP_NOT_FOUND);
        }

        $odontogram = $this->odontogramRepository->findOneBy([
            'visit' => $appointment,
            'treatment' => $treatment,
        ]);

        if (!$odontogram) {
            return $this->json(['message' => 'Odontogram not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $odontogram->getId(),
            'status' => $odontogram->getStatus(),
            'visit_id' => $appointment->getId(),
            'treatment_id' => $treatment->getId(),
        ], Response::HTTP_OK);
    }

    #[Route('/{odontogramId}/details', name: 'api_odontogram_detail_create', requirements: ['odontogramId' => '\d+'], methods: ['POST'])]
    public function createDetail(int $odontogramId, Request $request): JsonResponse
    {
        $odontogram = $this->odontogramRepository->find($odontogramId);
        if (!$odontogram) {
            return $this->json(['error' => 'Odontogram not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $toothNumber = $data['tooth_number'] ?? null;
        $pathologyId = $data['pathology_id'] ?? null;
        $faces = $data['faces'] ?? [];

        if (!$toothNumber || !$pathologyId) {
            return $this->json([
                'error' => 'tooth_number and pathology_id are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $pathology = $this->pathologyRepository->find($pathologyId);
        if (!$pathology) {
            return $this->json(['error' => 'Pathology not found'], Response::HTTP_NOT_FOUND);
        }

        $detail = new OdontogramaDetail();
        $detail->setOdontograma($odontogram);
        $detail->setToothNumber((int) $toothNumber);
        $detail->setPathology($pathology);

        $this->entityManager->persist($detail);

        foreach ($faces as $faceName) {
            $face = new ToothFace();
            $face->setFaceName($faceName);
            $face->setOdontogramaDetail($detail);
            $this->entityManager->persist($face);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Odontogram detail created successfully',
            'id' => $detail->getId(),
            'odontogram_id' => $odontogram->getId(),
            'tooth_number' => $detail->getToothNumber(),
            'pathology_id' => $pathology->getId(),
            'faces' => $faces,
        ], Response::HTTP_CREATED);
    }

    #[Route('/{odontogramId}/details', name: 'api_odontogram_detail_list', requirements: ['odontogramId' => '\d+'], methods: ['GET'])]
    public function listDetails(int $odontogramId): JsonResponse
    {
        $odontogram = $this->odontogramRepository->find($odontogramId);
        if (!$odontogram) {
            return $this->json(['message' => 'Odontogram not found'], Response::HTTP_NOT_FOUND);
        }

        $items = [];
        foreach ($odontogram->getOdontogramaDetails() as $detail) {
            $faces = [];
            foreach ($detail->getToothFaces() as $face) {
                $faces[] = [
                    'id' => $face->getId(),
                    'face_name' => $face->getFaceName(),
                ];
            }

            $items[] = [
                'id' => $detail->getId(),
                'tooth_number' => $detail->getToothNumber(),
                'pathology' => [
                    'id' => $detail->getPathology()?->getId(),
                    'description' => $detail->getPathology()?->getDescription(),
                    'protocol_color' => $detail->getPathology()?->getProtocolColor(),
                    'visual_type' => $detail->getPathology()?->getVisualType(),
                ],
                'faces' => $faces,
            ];
        }

        return $this->json($items, Response::HTTP_OK);
    }

    #[Route('/details/{detailId}', name: 'api_odontogram_detail_delete', requirements: ['detailId' => '\d+'], methods: ['DELETE'])]
    public function deleteDetail(int $detailId): JsonResponse
    {
        $detail = $this->odontogramaDetailRepository->find($detailId);

        if (!$detail) {
            return $this->json(['error' => 'Odontogram detail not found'], Response::HTTP_NOT_FOUND);
        }

        foreach ($detail->getToothFaces() as $face) {
            $this->entityManager->remove($face);
        }

        $this->entityManager->remove($detail);
        $this->entityManager->flush();

        return $this->json([
            'result' => 'deleted',
            'id' => $detailId,
        ], Response::HTTP_OK);
    }

    #[Route('/details/{detailId}/faces', name: 'api_tooth_face_create', requirements: ['detailId' => '\d+'], methods: ['POST'])]
    public function createFace(int $detailId, Request $request): JsonResponse
    {
        $detail = $this->odontogramaDetailRepository->find($detailId);

        if (!$detail) {
            return $this->json(['error' => 'Odontogram detail not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $faceName = $data['face_name'] ?? null;

        if (!$faceName) {
            return $this->json(['error' => 'face_name is required'], Response::HTTP_BAD_REQUEST);
        }

        $face = new ToothFace();
        $face->setFaceName($faceName);
        $face->setOdontogramaDetail($detail);

        $this->entityManager->persist($face);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Tooth face created successfully',
            'id' => $face->getId(),
            'detail_id' => $detail->getId(),
            'face_name' => $face->getFaceName(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/details/{detailId}/faces', name: 'api_tooth_face_list', requirements: ['detailId' => '\d+'], methods: ['GET'])]
    public function listFaces(int $detailId): JsonResponse
    {
        $detail = $this->odontogramaDetailRepository->find($detailId);

        if (!$detail) {
            return $this->json(['error' => 'Odontogram detail not found'], Response::HTTP_NOT_FOUND);
        }

        $items = [];
        foreach ($detail->getToothFaces() as $face) {
            $items[] = [
                'id' => $face->getId(),
                'face_name' => $face->getFaceName(),
            ];
        }

        return $this->json($items, Response::HTTP_OK);
    }
}