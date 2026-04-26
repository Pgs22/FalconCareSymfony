<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
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
    public function list(Request $request, AppointmentRepository $appointmentRepository): JsonResponse
    {
        $patientId = $this->resolvePatientId($request);
        if ($patientId === null) {
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Provide patientId, patient.id, or patient (IRI).',
            ], Response::HTTP_BAD_REQUEST);
        }

        $appointments = $appointmentRepository->createQueryBuilder('a')
            ->leftJoin('a.patient', 'p')
            ->addSelect('p')
            ->andWhere('p.id = :patientId')
            ->setParameter('patientId', $patientId)
            ->orderBy('a.visitDate', 'DESC')
            ->addOrderBy('a.visitTime', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();

        $items = array_map([$this, 'serializeAppointment'], $appointments);

        if ($request->query->get('format') === 'jsonld') {
            return new JsonResponse([
                '@context' => ['hydra' => 'http://www.w3.org/ns/hydra/core#'],
                '@type' => 'hydra:Collection',
                'hydra:member' => $items,
                'hydra:totalItems' => count($items),
            ], Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
        }

        return $this->json($items, Response::HTTP_OK);
    }

    private function resolvePatientId(Request $request): ?int
    {
        $query = $request->query->all();
        foreach (['patientId', 'patient.id', 'patient_id', 'patient'] as $key) {
            if (array_key_exists($key, $query)) {
                return PatientIriParser::parsePatientId($query[$key]);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAppointment(Appointment $appointment): array
    {
        $patient = $appointment->getPatient();
        $doctor = $appointment->getDoctor();

        return [
            'id' => $appointment->getId(),
            'patientId' => $patient?->getId(),
            'patient' => [
                'id' => $patient?->getId(),
                'name' => $patient ? trim($patient->getFirstName() . ' ' . $patient->getLastName()) : null,
            ],
            'doctorId' => $doctor?->getId(),
            'doctorName' => $doctor ? trim($doctor->getFirstName() . ' ' . $doctor->getLastNames()) : null,
            'reason' => $appointment->getConsultationReason(),
            'consultationReason' => $appointment->getConsultationReason(),
            'observations' => $appointment->getObservations(),
            'status' => $appointment->getStatus(),
            'date' => $appointment->getVisitDate()?->format('Y-m-d'),
            'time' => $appointment->getVisitTime()?->format('H:i'),
            'startTime' => $appointment->getVisitTime()?->format('H:i'),
            'visitDate' => $appointment->getVisitDate()?->format('Y-m-d'),
            'visitTime' => $appointment->getVisitTime()?->format('H:i'),
            'durationMinutes' => $appointment->getDurationMinutes(),
        ];
    }
}
