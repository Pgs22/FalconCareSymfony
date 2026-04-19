<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\Patient;
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use App\Entity\Odontogram;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;


#[Route('/api/appointment')]
final class AppointmentController extends AbstractController
{
    private const ALLOWED_STATUSES = ['Confirmada', 'En curs', 'Cancel·lada'];
    private const ALLOWED_CLEANING_MINUTES = [5, 10, 15];
    private const NO_KNOWN_MEDICATION_ALLERGIES = 'Cap coneguda';

    #[Route('/index', name: 'app_appointment_index', methods: ['GET'])]
    public function index(Request $request, AppointmentRepository $repo): JsonResponse 
    {
        $fechaStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        $fecha = new \DateTime($fechaStr);

        $appointments = $repo->findByDate($fecha);

        return $this->json($this->serializeAppointments($appointments));
    }

    private function serializeAppointments(array $appointments): array
        {
        $result = [];
        foreach ($appointments as $appointment) {
            $reason = $appointment->getConsultationReason() ?? '';
            $status = $appointment->getStatus() ?? 'Programada';
            
            $isUrgency = $appointment->isUrgency() || str_contains(strtolower($reason), 'urgència') || str_contains(strtolower($reason), 'urgencia');
            $isFirstVisit = $appointment->isFirstVisit() || str_contains(strtolower($reason), 'primera visita');

            if ($status === 'Finalitzada') {
                $color = '#9e9e9e';
            } elseif ($isUrgency) {
                $color = '#e91e63';
            } elseif ($isFirstVisit) {
                $color = '#9c27b0';
            } else {
                $color = '#00bcd4';
            }

            $result[] = [
                'id' => $appointment->getId(),
                'time' => $appointment->getVisitTime() ? $appointment->getVisitTime()->format('H:i') : '--:--',
                'duration' => $appointment->getDurationMinutes() ?? 30,
                'cleaningTime' => $appointment->getCleaningMinutes(),
                'cleaning_time' => $appointment->getCleaningMinutes(),
                'cleaningMinutes' => $appointment->getCleaningMinutes(),
                'totalBlockTime' => $appointment->getTotalDurationWithCleaning(),
                'patientName' => $appointment->getPatient() 
                    ? $appointment->getPatient()->getFirstName() . ' ' . $appointment->getPatient()->getLastName() 
                    : 'Sense Pacient',
                'doctorName' => $appointment->getDoctor() 
                    ? $appointment->getDoctor()->getFirstName() 
                    : 'Sense Doctor',
                'boxId' => $appointment->getBox() ? $appointment->getBox()->getId() : null,
                'box' => $appointment->getBox() ? $appointment->getBox()->getBoxName() : 'Sense Box',
                'reason' => $reason,
                'status' => $status,
                'color' => $color,
                'isUrgency' => $isUrgency,
                'isFirstVisit' => $isFirstVisit
            ];
        }
        return $result;
    }

    #[Route('/weekly', name: 'app_appointment_weekly', methods: ['GET'])]
    public function weekly(Request $request, AppointmentRepository $repo): JsonResponse 
    {
        $fechaStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        try {
            $fecha = new \DateTime($fechaStr);
            $appointments = $repo->findByWeek($fecha);
        } catch (\Exception $e) {
            return $this->json([
                'ok' => false,
                'code' => 'INVALID_DATE',
                'error' => [
                    'field' => 'date',
                    'messageKey' => 'appointment.date.invalid',
                    'received' => $fechaStr,
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serializeAppointments($appointments));
    }

    private function isBoxOccupied(AppointmentRepository $repository, Appointment $newApp): bool 
    {
        $overlaps = $repository->findOverlappingAppointments(
            $newApp->getVisitDate(),
            $newApp->getVisitTime(),
            (int)($newApp->getDurationMinutes() ?? 15),
            $newApp->getBox()?->getId(),
            $newApp->getId(),
            $newApp->getCleaningMinutes()
        );
        return count($overlaps) > 0;
    }

    private function resolveDurationMinutes(array $data): ?int
    {
        if (isset($data['durationMinutes'])) {
            return (int) $data['durationMinutes'];
        }

        if (isset($data['duration'])) {
            return (int) $data['duration'];
        }

        return null;
    }

    private function resolveCleaningMinutes(array $data): ?int
    {
        if (isset($data['cleaningMinutes'])) {
            return (int) $data['cleaningMinutes'];
        }

        if (isset($data['cleaningTime'])) {
            return (int) $data['cleaningTime'];
        }

        if (isset($data['cleaning_time'])) {
            return (int) $data['cleaning_time'];
        }

        return null;
    }

    private function isAllowedCleaningMinutes(int $cleaningMinutes): bool
    {
        return in_array($cleaningMinutes, self::ALLOWED_CLEANING_MINUTES, true);
    }

    private function resolveInitialAppointmentStatus(Appointment $appointment): string
    {
        $status = trim((string) $appointment->getStatus());
        if ($status !== '') {
            return $status;
        }

        $patient = $appointment->getPatient();
        if ($patient !== null && $patient->getLastOdontogramId() === null) {
            return 'Falta Consentiment';
        }

        return 'Programada';
    }

    private function buildMedicationAllergyAlert(Patient $patient): ?array
    {
        $allergies = trim((string) ($patient->getMedicationAllergies() ?? ''));

        if ($allergies === '' || mb_strtolower($allergies) === mb_strtolower(self::NO_KNOWN_MEDICATION_ALLERGIES)) {
            return null;
        }

        return [
            'type' => 'warning',
            'code' => 'PATIENT_MEDICATION_ALLERGIES',
            'messageKey' => 'patient.medication_allergies.warning',
            'patientId' => $patient->getId(),
            'patientName' => trim(($patient->getFirstName() ?? '') . ' ' . ($patient->getLastName() ?? '')),
            'medicationAllergies' => $allergies,
        ];
    }

    #[Route('/new', name: 'app_appointment_new', methods: ['POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager, 
        AppointmentRepository $repository
    ): JsonResponse {
        $appointment = new Appointment();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'ok' => false,
                'code' => 'INVALID_JSON',
                'error' => [
                    'messageKey' => 'request.body.invalid_json',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->submit($data, false); 

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $appointment->setStatus($this->resolveInitialAppointmentStatus($appointment));

                if ($appointment->getObservations() === null) {
                    $appointment->setObservations('');
                }

                if (!$appointment->getConsultationReason()) {
                    $appointment->setConsultationReason('Consulta general');
                }

                $durationMinutes = $this->resolveDurationMinutes($data);
                if ($durationMinutes !== null) {
                    $appointment->setDurationMinutes($durationMinutes);
                }

                $cleaningMinutes = $this->resolveCleaningMinutes($data);
                if ($cleaningMinutes !== null) {
                    if (!$this->isAllowedCleaningMinutes($cleaningMinutes)) {
                        return $this->json([
                            'ok' => false,
                            'code' => 'VALIDATION_ERROR',
                            'error' => [
                                'field' => 'cleaningMinutes',
                                'messageKey' => 'appointment.cleaning_minutes.invalid',
                                'allowedValues' => self::ALLOWED_CLEANING_MINUTES,
                                'received' => $cleaningMinutes,
                            ],
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    $appointment->setCleaningMinutes($cleaningMinutes);
                }

                if ($this->isBoxOccupied($repository, $appointment)) {
                    return $this->json([
                        'ok' => false,
                        'code' => 'BOX_OCCUPIED',
                        'error' => [
                            'messageKey' => 'appointment.box.occupied',
                        ],
                    ], Response::HTTP_CONFLICT);
                }

                $entityManager->persist($appointment);
                $entityManager->flush();

                $response = [
                    'ok' => true,
                    'code' => 'APPOINTMENT_CREATED',
                    'messageKey' => 'appointment.created',
                    'id' => $appointment->getId(),
                ];

                $patient = $appointment->getPatient();
                if ($patient !== null) {
                    $allergyAlert = $this->buildMedicationAllergyAlert($patient);
                    if ($allergyAlert !== null) {
                        $response['alerts'] = [$allergyAlert];
                    }
                }

                return $this->json($response, Response::HTTP_CREATED);
            } catch (\Exception $e) {
                return $this->json([
                    'ok' => false,
                    'code' => 'DATABASE_ERROR',
                    'error' => [
                        'messageKey' => 'common.database_error',
                        'details' => $e->getMessage(),
                    ],
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $origin = $error->getOrigin();
            $fieldName = $origin ? $origin->getName() : 'form';
            $errors[] = $fieldName . ': ' . $error->getMessage();
        }

        return $this->json([
            'ok' => false,
            'code' => 'VALIDATION_ERROR',
            'error' => [
                'messageKey' => 'appointment.validation.failed',
                'details' => $errors,
                'received' => $data,
            ],
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{id}/open', name: 'app_appointment_open', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function open(
        Appointment $appointment, 
        EntityManagerInterface $entityManager
    ): Response {
        $patient = $appointment->getPatient();
        $odontogramId = $patient->getLastOdontogramId();

        if (!$odontogramId) {
            $newOdontogram = new Odontogram();
            $newOdontogram->setVisit($appointment);
            $newOdontogram->setStatus('Actiu');

            $entityManager->persist($newOdontogram);
            $entityManager->flush();

            $patient->setLastOdontogramId($newOdontogram->getId());
            $entityManager->flush();
            
            $odontogramId = $newOdontogram->getId();
        }

        return $this->redirectToRoute('app_odontogram_view', ['id' => $odontogramId]);
    }

    #[Route('/{id}', name: 'app_appointment_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Appointment $appointment): JsonResponse
    {
        return $this->json([
            'id' => $appointment->getId(),
            'date' => $appointment->getVisitDate() ? $appointment->getVisitDate()->format('Y-m-d') : null,
            'time' => $appointment->getVisitTime() ? $appointment->getVisitTime()->format('H:i') : null,
            'reason' => $appointment->getConsultationReason() ?? '',
            'observations' => $appointment->getObservations() ?? '',
            'status' => $appointment->getStatus() ?? 'Programada',
            'duration' => $appointment->getDurationMinutes(),
            'patient' => [
                'id' => $appointment->getPatient()?->getId(),
                'name' => $appointment->getPatient() ? ($appointment->getPatient()->getFirstName() . ' ' . $appointment->getPatient()->getLastName()) : 'Pacient desconegut',
            ],
            'doctor' => [
                'id' => $appointment->getDoctor()?->getId(),
                'name' => $appointment->getDoctor() ? ($appointment->getDoctor()->getFirstName() . ' ' . $appointment->getDoctor()->getLastNames()) : 'Sense doctor',
            ],
            'box' => $appointment->getBox()?->getBoxName() ?? 'Sense box',
            'treatmentId' => $appointment->getTreatment()?->getId(),
        ]);
    }

    #[Route('/{id}/close', name: 'app_appointment_close', methods: ['POST'])]
    public function close(Appointment $appointment, EntityManagerInterface $em): JsonResponse 
    {
        $appointment->setStatus('Finalitzada');
        $em->flush();
        return $this->json([
            'ok' => true,
            'code' => 'APPOINTMENT_CLOSED',
            'messageKey' => 'appointment.closed',
            'id' => $appointment->getId(),
            'status' => $appointment->getStatus(),
        ]);
    }

    #[Route('/{id}/status', name: 'app_appointment_update_status', requirements: ['id' => '\d+'], methods: ['PATCH', 'PUT'])]
    public function updateStatus(
        Request $request,
        Appointment $appointment,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $newStatus = '';

        if (is_string($payload)) {
            $newStatus = trim($payload);
        } elseif (is_array($payload)) {
            if (isset($payload['status'])) {
                $newStatus = trim((string) $payload['status']);
            } elseif (isset($payload['stateName'])) {
                $newStatus = trim((string) $payload['stateName']);
            }
        }

        if ($newStatus === '') {
            return $this->json([
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'error' => [
                    'field' => 'status',
                    'messageKey' => 'appointment.status.required_string',
                    'expected' => 'string',
                    'allowedStatuses' => self::ALLOWED_STATUSES,
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            return $this->json([
                'ok' => false,
                'code' => 'INVALID_STATUS',
                'error' => [
                    'field' => 'status',
                    'messageKey' => 'appointment.status.invalid',
                    'allowedStatuses' => self::ALLOWED_STATUSES,
                    'received' => $newStatus,
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $appointment->setStatus($newStatus);
        $entityManager->flush();

        return $this->json([
            'ok' => true,
            'code' => 'APPOINTMENT_STATUS_UPDATED',
            'messageKey' => 'appointment.status.updated',
            'id' => $appointment->getId(),
            'status' => $appointment->getStatus(),
        ]);
    }

    #[Route('/{id}/update', name: 'app_appointment_update', requirements: ['id' => '\d+'], methods: ['POST', 'PUT'])]
    public function update(
        Request $request, 
        Appointment $appointment, 
        EntityManagerInterface $entityManager,
        AppointmentRepository $repository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json([
                'ok' => false,
                'code' => 'INVALID_JSON',
                'error' => [
                    'messageKey' => 'request.body.invalid_json',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->submit($data, false);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $durationMinutes = $this->resolveDurationMinutes($data);
                if ($durationMinutes !== null) {
                    $appointment->setDurationMinutes($durationMinutes);
                }

                $cleaningMinutes = $this->resolveCleaningMinutes($data);
                if ($cleaningMinutes !== null) {
                    if (!$this->isAllowedCleaningMinutes($cleaningMinutes)) {
                        return $this->json([
                            'ok' => false,
                            'code' => 'VALIDATION_ERROR',
                            'error' => [
                                'field' => 'cleaningMinutes',
                                'messageKey' => 'appointment.cleaning_minutes.invalid',
                                'allowedValues' => self::ALLOWED_CLEANING_MINUTES,
                                'received' => $cleaningMinutes,
                            ],
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    $appointment->setCleaningMinutes($cleaningMinutes);
                }

                if ($this->isBoxOccupied($repository, $appointment)) {
                    return $this->json([
                        'ok' => false,
                        'code' => 'BOX_OCCUPIED',
                        'error' => [
                            'messageKey' => 'appointment.box.occupied',
                        ],
                    ], Response::HTTP_CONFLICT);
                }

                $entityManager->flush();
                
                return $this->json([
                    'ok' => true,
                    'code' => 'APPOINTMENT_UPDATED',
                    'messageKey' => 'appointment.updated',
                    'id' => $appointment->getId(),
                ]);
            } catch (\Exception $e) {
                return $this->json([
                    'ok' => false,
                    'code' => 'DATABASE_ERROR',
                    'error' => [
                        'messageKey' => 'common.database_error',
                        'details' => $e->getMessage(),
                    ],
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return $this->json([
            'ok' => false,
            'code' => 'VALIDATION_ERROR',
            'error' => [
                'messageKey' => 'appointment.validation.failed',
            ],
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{id}', name: 'app_appointment_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[Route('/{id}/delete', name: 'app_appointment_delete_legacy', requirements: ['id' => '\d+'], methods: ['DELETE', 'POST'])]
    public function delete(Appointment $appointment, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($appointment);
        $entityManager->flush();

        return $this->json([
            'ok' => true,
            'code' => 'APPOINTMENT_DELETED',
            'messageKey' => 'appointment.deleted',
            'id' => $appointment->getId(),
        ]);
    }
}