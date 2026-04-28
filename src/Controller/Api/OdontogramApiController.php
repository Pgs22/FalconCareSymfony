<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\Odontogram;
use App\Entity\OdontogramDetail;
use App\Entity\Patient;
use App\Entity\Pathology;
use App\Entity\PathologyType;
use App\Entity\ToothFace;
use App\Entity\Treatment;
use App\Repository\AppointmentRepository;
use App\Repository\OdontogramDetailRepository;
use App\Repository\OdontogramRepository;
use App\Repository\PathologyRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/odontograms')]
final class OdontogramApiController extends AbstractController
{
    private const STATUS_OPEN = 'Abierto';

    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly OdontogramRepository $odontogramRepository,
        private readonly OdontogramDetailRepository $odontogramDetailRepository,
        private readonly PathologyRepository $pathologyRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/open', name: 'api_odontogram_create_or_get', methods: ['POST'])]
    public function createOrGetOdontogram(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);
        $patientId = $data['patient_id'] ?? null;
        $visitId = $data['visit_id'] ?? null;

        if (!$this->isPositiveInt($patientId) || !$this->isPositiveInt($visitId)) {
            return $this->json([
                'error' => 'patient_id and visit_id are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $patient = $this->entityManager->find(Patient::class, (int) $patientId);
        if (!$patient instanceof Patient) {
            return $this->json([
                'error' => 'Patient not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $visit = $this->appointmentRepository->find((int) $visitId);
        if (!$visit instanceof Appointment) {
            return $this->json([
                'error' => 'Appointment not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($visit->getPatient()?->getId() !== $patient->getId()) {
            return $this->json([
                'error' => 'The appointment does not belong to the provided patient',
            ], Response::HTTP_CONFLICT);
        }

        $created = false;

        try {
            $this->beginTransaction();

            $odontogram = $this->odontogramRepository->findOneBy(['visit' => $visit]);

            if (!$odontogram instanceof Odontogram) {
                $odontogram = new Odontogram();
                $odontogram->setStatus(self::STATUS_OPEN);
                $odontogram->setVisit($visit);
                $this->entityManager->persist($odontogram);
                $this->entityManager->flush();
                $created = true;
            }

            $patient->setLastOdontogramId($odontogram->getId());

            $this->entityManager->flush();
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollBack();

            return $this->json([
                'error' => 'Could not create or recover the odontogram',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'message' => $created ? 'Odontogram created successfully' : 'Open odontogram reused successfully',
            'odontogram' => $this->serializeOdontogram($odontogram),
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/{odontogramId}/pathologies', name: 'api_odontogram_add_pathology_to_tooth', requirements: ['odontogramId' => '\d+'], methods: ['POST'])]
    public function addPathologyToTooth(int $odontogramId, Request $request): JsonResponse
    {
        $odontogram = $this->odontogramRepository->find($odontogramId);
        if (!$odontogram instanceof Odontogram) {
            return $this->json([
                'error' => 'Odontogram not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $this->getRequestData($request);
        $pathologyId = $data['pathology_id'] ?? null;
        $toothNumber = $data['tooth_number'] ?? null;
        $faces = $data['faces'] ?? [];

        if (!$this->isPositiveInt($pathologyId) || !$this->isPositiveInt($toothNumber)) {
            return $this->json([
                'error' => 'pathology_id and tooth_number are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($faces)) {
            return $this->json([
                'error' => 'faces must be an array',
            ], Response::HTTP_BAD_REQUEST);
        }

        $pathology = $this->pathologyRepository->find((int) $pathologyId);
        if (!$pathology instanceof Pathology) {
            return $this->json([
                'error' => 'Pathology not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $cleanFaces = $this->normalizeFaces($faces);

        try {
            $this->beginTransaction();

            $detail = new OdontogramDetail();
            $detail->setOdontogram($odontogram);
            $detail->setPathology($pathology);
            $detail->setToothNumber((int) $toothNumber);
            $this->entityManager->persist($detail);
            $this->entityManager->flush();

            foreach ($cleanFaces as $faceName) {
                $face = new ToothFace();
                $face->setFaceName($faceName);
                $face->setOdontogramDetail($detail);
                $this->entityManager->persist($face);
            }

            $this->entityManager->flush();
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollBack();

            return $this->json([
                'error' => 'Could not create the odontogram detail',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'message' => 'Pathology added to tooth successfully',
            'detail' => $this->serializeDetail($detail),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{odontogramId}/treatments/sync', name: 'api_odontogram_create_treatment_and_sync_visit', requirements: ['odontogramId' => '\d+'], methods: ['POST'])]
    public function createTreatmentAndSyncVisit(int $odontogramId, Request $request): JsonResponse
    {
        $odontogram = $this->odontogramRepository->find($odontogramId);
        if (!$odontogram instanceof Odontogram) {
            return $this->json([
                'error' => 'Odontogram not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $this->getRequestData($request);
        $visitId = $data['visit_id'] ?? null;

        if (!$this->isPositiveInt($visitId)) {
            return $this->json([
                'error' => 'visit_id is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $visit = $this->appointmentRepository->find((int) $visitId);
        if (!$visit instanceof Appointment) {
            return $this->json([
                'error' => 'Appointment not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $odontogramPatientId = $odontogram->getVisit()?->getPatient()?->getId();
        $visitPatientId = $visit->getPatient()?->getId();

        if ($odontogramPatientId !== null && $visitPatientId !== $odontogramPatientId) {
            return $this->json([
                'error' => 'The appointment does not belong to the odontogram patient',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $this->beginTransaction();

            $treatment = new Treatment();
            $treatment->setTreatmentName($this->resolveTreatmentName($data));
            $treatment->setDescription($this->resolveTreatmentDescription($data));

            if ($this->isPositiveInt($data['estimated_duration'] ?? null)) {
                $treatment->setEstimatedDuration((int) $data['estimated_duration']);
            }

            if (isset($data['status']) && is_string($data['status']) && trim($data['status']) !== '') {
                $treatment->setStatus(trim($data['status']));
            }

            if (array_key_exists('scheduling_notes', $data) && ($data['scheduling_notes'] === null || is_string($data['scheduling_notes']))) {
                $notes = $data['scheduling_notes'];
                $treatment->setSchedulingNotes($notes !== null ? trim($notes) : null);
            }

            $this->entityManager->persist($treatment);
            $this->entityManager->flush();

            $odontogram->setTreatment($treatment);
            $odontogram->setVisit($visit);
            $visit->setTreatment($treatment);

            $this->entityManager->flush();
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollBack();

            return $this->json([
                'error' => 'Could not create the treatment and sync the visit',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'message' => 'Treatment created and visit synchronized successfully',
            'treatment' => $this->serializeTreatment($treatment),
            'odontogram' => $this->serializeOdontogram($odontogram),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{odontogramId}/details/sync', name: 'api_odontogram_detail_sync', requirements: ['odontogramId' => '\d+'], methods: ['POST'])]
    public function syncDetails(int $odontogramId, Request $request): JsonResponse
    {
        $odontogram = $this->odontogramRepository->find($odontogramId);
        if (!$odontogram instanceof Odontogram) {
            return $this->json([
                'error' => 'Odontogram not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $this->getRequestData($request);
        $entries = $data['entries'] ?? null;

        if (!is_array($entries)) {
            return $this->json([
                'error' => 'entries must be an array',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $normalizedEntries = $this->normalizeSyncEntries($entries);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->beginTransaction();

            foreach ($odontogram->getOdontogramDetails() as $detail) {
                foreach ($detail->getToothFaces() as $face) {
                    $this->entityManager->remove($face);
                }

                $this->entityManager->remove($detail);
            }

            $this->entityManager->flush();

            foreach ($normalizedEntries as $entry) {
                $pathology = $this->resolvePathologyForSync($odontogram, $entry['pathology_type_id']);

                $detail = new OdontogramDetail();
                $detail->setOdontogram($odontogram);
                $detail->setPathology($pathology);
                $detail->setToothNumber($entry['tooth_number']);
                $this->entityManager->persist($detail);
                $this->entityManager->flush();

                foreach ($entry['faces'] as $faceName) {
                    $face = new ToothFace();
                    $face->setFaceName($faceName);
                    $face->setOdontogramDetail($detail);
                    $this->entityManager->persist($face);
                }
            }

            $this->entityManager->flush();
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollBack();

            return $this->json([
                'error' => 'Could not synchronize the odontogram details',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'message' => 'Odontogram details synchronized successfully',
            'odontogram' => $this->serializeOdontogram($odontogram),
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_odontogram_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function findById(int $id): JsonResponse
    {
        $odontogram = $this->odontogramRepository->find($id);

        if (!$odontogram instanceof Odontogram) {
            return $this->json([
                'message' => 'Odontogram not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeOdontogram($odontogram), Response::HTTP_OK);
    }

    #[Route('/{odontogramId}/details', name: 'api_odontogram_detail_list', requirements: ['odontogramId' => '\d+'], methods: ['GET'])]
    public function listDetails(int $odontogramId): JsonResponse
    {
        $odontogram = $this->odontogramRepository->find($odontogramId);
        if (!$odontogram instanceof Odontogram) {
            return $this->json([
                'message' => 'Odontogram not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $items = [];
        foreach ($odontogram->getOdontogramDetails() as $detail) {
            $items[] = $this->serializeDetail($detail);
        }

        return $this->json($items, Response::HTTP_OK);
    }

    #[Route('/details/{detailId}', name: 'api_odontogram_detail_delete', requirements: ['detailId' => '\d+'], methods: ['DELETE'])]
    public function deleteDetail(int $detailId): JsonResponse
    {
        $detail = $this->odontogramDetailRepository->find($detailId);

        if (!$detail instanceof OdontogramDetail) {
            return $this->json([
                'error' => 'Odontogram detail not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->beginTransaction();

            foreach ($detail->getToothFaces() as $face) {
                $this->entityManager->remove($face);
            }

            $this->entityManager->remove($detail);
            $this->entityManager->flush();
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollBack();

            return $this->json([
                'error' => 'Could not delete the odontogram detail',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'result' => 'deleted',
            'id' => $detailId,
        ], Response::HTTP_OK);
    }

    private function getRequestData(Request $request): array
    {
        if ($request->getContentTypeFormat() === 'json') {
            try {
                return $request->toArray();
            } catch (\Throwable) {
                return [];
            }
        }

        return $request->request->all();
    }

    /**
     * @param array<int|string, mixed> $faces
     * @return list<string>
     */
    private function normalizeFaces(array $faces): array
    {
        $cleanFaces = [];

        foreach ($faces as $faceName) {
            if (!is_string($faceName)) {
                continue;
            }

            $normalizedFace = strtoupper(trim($faceName));
            if ($normalizedFace === '') {
                continue;
            }

            if (in_array($normalizedFace, $cleanFaces, true)) {
                continue;
            }

            $cleanFaces[] = $normalizedFace;
        }

        return $cleanFaces;
    }

    /**
     * @param array<int|string, mixed> $entries
     * @return list<array{tooth_number: int, pathology_type_id: int, faces: list<string>}>
     */
    private function normalizeSyncEntries(array $entries): array
    {
        $normalizedEntries = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                throw new \InvalidArgumentException('Each entry must be an object.');
            }

            $toothNumber = $entry['tooth_number'] ?? null;
            $pathologyTypeId = $entry['pathology_type_id'] ?? null;
            $faces = $entry['faces'] ?? null;

            if (!$this->isPositiveInt($toothNumber) || !$this->isPositiveInt($pathologyTypeId)) {
                throw new \InvalidArgumentException('Each entry requires valid tooth_number and pathology_type_id.');
            }

            if (!is_array($faces)) {
                throw new \InvalidArgumentException('Each entry requires faces as an array.');
            }

            $normalizedFaces = $this->normalizeFaces($faces);
            if ($normalizedFaces === []) {
                throw new \InvalidArgumentException('Each entry requires at least one face.');
            }

            $normalizedEntries[] = [
                'tooth_number' => (int) $toothNumber,
                'pathology_type_id' => (int) $pathologyTypeId,
                'faces' => $normalizedFaces,
            ];
        }

        return $normalizedEntries;
    }

    private function resolvePathologyForSync(Odontogram $odontogram, int $pathologyTypeId): Pathology
    {
        $pathology = $this->pathologyRepository->findOneBy(['pathology_type' => $pathologyTypeId]);
        if ($pathology instanceof Pathology) {
            return $pathology;
        }

        $pathologyType = $this->entityManager->find(PathologyType::class, $pathologyTypeId);
        if (!$pathologyType instanceof PathologyType) {
            throw new \InvalidArgumentException('Pathology type not found.');
        }

        $treatment = $odontogram->getTreatment() ?? $odontogram->getVisit()?->getTreatment();
        if (!$treatment instanceof Treatment) {
            $treatment = new Treatment();
            $treatment->setTreatmentName(sprintf('Protocol %s', $pathologyType->getName() ?? $pathologyTypeId));
            $treatment->setDescription('Automatic treatment created for odontogram synchronization');
            $treatment->setEstimatedDuration($pathologyType->getDefaultDuration() ?? 30);
            $treatment->setStatus('Actiu');
            $this->entityManager->persist($treatment);

            $odontogram->setTreatment($treatment);
            if ($odontogram->getVisit() !== null) {
                $odontogram->getVisit()->setTreatment($treatment);
            }
        }

        $pathology = new Pathology();
        $pathology->setDescription($this->buildPathologyDescription($pathologyType));
        $pathology->setProtocolColor($this->resolveProtocolColor($pathologyType));
        $pathology->setVisualType('Protocol');
        $pathology->setPathologyType($pathologyType);
        $pathology->setTreatment($treatment);
        $this->entityManager->persist($pathology);

        return $pathology;
    }

    private function buildPathologyDescription(PathologyType $pathologyType): string
    {
        $name = trim((string) ($pathologyType->getName() ?? ''));

        return $name !== '' ? sprintf('%s protocol mark', $name) : 'Protocol mark';
    }

    private function resolveProtocolColor(PathologyType $pathologyType): string
    {
        $normalizedName = mb_strtolower(trim((string) ($pathologyType->getName() ?? '')));

        return match ($normalizedName) {
            'càries', 'caries' => '#ff7d72',
            'neteja' => '#62d5e2',
            'endodòncia', 'endodoncia' => '#9b8cff',
            default => '#9aa7b2',
        };
    }

    private function resolveTreatmentName(array $data): string
    {
        $candidates = [
            $data['treatment_name'] ?? null,
            $data['name'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return 'Nuevo tratamiento';
    }

    private function resolveTreatmentDescription(array $data): string
    {
        $candidate = $data['description'] ?? null;

        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }

        return 'Tratamiento generado automaticamente desde odontograma';
    }

    private function isPositiveInt(mixed $value): bool
    {
        return filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    private function beginTransaction(): void
    {
        $connection = $this->entityManager->getConnection();
        if (!$connection->isTransactionActive()) {
            $connection->beginTransaction();
        }
    }

    private function commit(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->commit();
        }
    }

    private function rollBack(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    private function serializeOdontogram(Odontogram $odontogram): array
    {
        $details = [];
        foreach ($odontogram->getOdontogramDetails() as $detail) {
            $details[] = $this->serializeDetail($detail);
        }

        return [
            'id' => $odontogram->getId(),
            'status' => $odontogram->getStatus(),
            'visit_id' => $odontogram->getVisit()?->getId(),
            'patient_id' => $odontogram->getVisit()?->getPatient()?->getId(),
            'treatment_id' => $odontogram->getTreatment()?->getId(),
            'details' => $details,
        ];
    }

    private function serializeDetail(OdontogramDetail $detail): array
    {
        $faces = [];
        foreach ($detail->getToothFaces() as $face) {
            $faces[] = [
                'id' => $face->getId(),
                'face_name' => $face->getFaceName(),
            ];
        }

        return [
            'id' => $detail->getId(),
            'odontogram_id' => $detail->getOdontogram()?->getId(),
            'tooth_number' => $detail->getToothNumber(),
            'pathology' => [
                'id' => $detail->getPathology()?->getId(),
                'description' => $detail->getPathology()?->getDescription(),
                'protocol_color' => $detail->getPathology()?->getProtocolColor(),
                'visual_type' => $detail->getPathology()?->getVisualType(),
                'pathology_type' => [
                    'id' => $detail->getPathology()?->getPathologyType()?->getId(),
                    'name' => $detail->getPathology()?->getPathologyType()?->getName(),
                ],
            ],
            'faces' => $faces,
        ];
    }

    private function serializeTreatment(Treatment $treatment): array
    {
        return [
            'id' => $treatment->getId(),
            'treatment_name' => $treatment->getTreatmentName(),
            'description' => $treatment->getDescription(),
            'estimated_duration' => $treatment->getEstimatedDuration(),
            'status' => $treatment->getStatus(),
            'scheduling_notes' => $treatment->getSchedulingNotes(),
        ];
    }
}
