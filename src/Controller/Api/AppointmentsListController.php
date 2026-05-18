<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\AppointmentRepository;
use App\Repository\PatientRepository;
use App\Service\AppointmentListSerializer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Alias plural usado por el front como fallback: GET /api/appointments?patientId=
 */
#[Route('/api/appointments')]
#[OA\Tag(name: 'Appointments')]
final class AppointmentsListController extends AbstractController
{
    public function __construct(
        private readonly AppointmentListSerializer $appointmentListSerializer,
    ) {
    }

    #[Route('', name: 'api_appointments_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/appointments',
        summary: 'List appointments (filter by patientId)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'patientId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Appointment list')]
    )]
    public function list(
        Request $request,
        AppointmentRepository $appointmentRepository,
        PatientRepository $patientRepository,
    ): JsonResponse {
        $patientIdRaw = $request->query->get('patientId');
        if ($patientIdRaw === null || trim((string) $patientIdRaw) === '') {
            return $this->json([], Response::HTTP_OK);
        }

        $patientId = filter_var($patientIdRaw, \FILTER_VALIDATE_INT);
        if ($patientId === false || $patientId <= 0) {
            return $this->json([
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'error' => ['field' => 'patientId', 'message' => 'patientId must be a positive integer'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $patient = $patientRepository->findById($patientId);
        if ($patient === null) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $dateStr = trim((string) $request->query->get('date', ''));
        if ($dateStr !== '') {
            try {
                $date = new \DateTime($dateStr);
                $appointments = $appointmentRepository->findByPatientAndDate($patient, $date);
            } catch (\Throwable) {
                return $this->json([
                    'ok' => false,
                    'code' => 'INVALID_DATE',
                    'error' => ['field' => 'date', 'message' => 'Invalid date format'],
                ], Response::HTTP_BAD_REQUEST);
            }
        } else {
            $appointments = $appointmentRepository->findByPatient($patient);
        }

        return $this->json($this->appointmentListSerializer->serializeAgendaBlocks($appointments));
    }
}
