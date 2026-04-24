<?php

namespace App\Controller\Api;

use App\Repository\AppointmentRepository;
use App\Util\AppointmentHistoryPayloadBuilder;
use App\Util\PatientIriParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/appointments')]
final class AppointmentsApiController extends AbstractController
{
    #[Route('', name: 'api_appointments_list', methods: ['GET'])]
    public function index(Request $request, AppointmentRepository $appointmentRepository): JsonResponse
    {
        $patientId = $this->resolvePatientIdFromQuery($request);
        if ($patientId === null) {
            return $this->json([
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'error' => [
                    'field' => 'patientId',
                    'message' => 'Query parameter patientId is required.',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $appointments = $appointmentRepository->findByPatientId($patientId);
        $rows = AppointmentHistoryPayloadBuilder::buildList($appointments);

        if (strtolower((string) $request->query->get('format', '')) === 'jsonld') {
            return $this->json([
                '@context' => '/api/contexts/Appointment',
                '@id' => '/api/appointments',
                '@type' => 'hydra:Collection',
                'hydra:member' => $rows,
                'hydra:totalItems' => count($rows),
            ], Response::HTTP_OK);
        }

        return $this->json($rows, Response::HTTP_OK);
    }

    private function resolvePatientIdFromQuery(Request $request): ?int
    {
        $q = $request->query->all();
        foreach (['patient.id', 'patientId', 'patient'] as $key) {
            if (array_key_exists($key, $q)) {
                return PatientIriParser::parsePatientId($q[$key]);
            }
        }

        return null;
    }
}
