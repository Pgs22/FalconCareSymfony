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
    private const STATUS_SCHEDULED = 'Programada';
    private const STATUS_MISSING_CONSENT = 'Falta consentiment';
    private const STATUS_IN_PROGRESS = 'En curs';
    private const STATUS_FINISHED = 'Finalitzada';

    private const MANUAL_STATUSES = [
        'Confirmada',
        'Arribada',
        'Cancelada',
    ];

    private const ALLOWED_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_MISSING_CONSENT,
        self::STATUS_IN_PROGRESS,
        self::STATUS_FINISHED,
        'Confirmada',
        'Arribada',
        'Cancelada',
    ];
    private const ALLOWED_CLEANING_MINUTES = [5, 10, 15];
    private const NO_KNOWN_MEDICATION_ALLERGIES = 'Cap coneguda';

    private function normalizeAppointmentStatus(?string $status): string
    {
        $status = trim((string) $status);

        if ($status === '') {
            return self::STATUS_SCHEDULED;
        }

        return match ($status) {
            'Encurs' => self::STATUS_IN_PROGRESS,
            'Falta Consentiment' => self::STATUS_MISSING_CONSENT,
            'Cancel·lada', 'CancelÂ·lada' => 'Cancelada',
            default => $status,
        };
    }

    private function normalizeManualStatus(?string $status): string
    {
        $status = trim((string) $status);

        return match ($status) {
            'Confirmada',
            'Confirmed',
            'Confirmado' => 'Confirmada',
            'Arribada',
            'Llegada',
            'Arrived',
            'Arribat' => 'Arribada',
            'Cancelada',
            'Cancel·lada',
            'CancelÂ·lada',
            'CancelÃ‚Â·lada',
            'Cancelled',
            'Canceled',
            'Cancelado' => 'Cancelada',
            default => $status,
        };
    }

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
            $status = $this->normalizeAppointmentStatus($appointment->getStatus());
            
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
                'date' => $appointment->getVisitDate()->format('Y-m-d'),
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

    private function isDoctorOccupied(AppointmentRepository $repository, Appointment $newApp): bool
    {
        $overlaps = $repository->findOverlappingAppointmentsByDoctor(
            $newApp->getVisitDate(),
            $newApp->getVisitTime(),
            (int)($newApp->getDurationMinutes() ?? 15),
            $newApp->getDoctor()?->getId(),
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
        $patient = $appointment->getPatient();
        if ($patient !== null && $patient->getLastOdontogramId() === null) {
            return self::STATUS_MISSING_CONSENT;
        }

        return self::STATUS_SCHEDULED;
    }

    private function serializeAppointment(Appointment $appointment, ?int $odontogramId = null): array
    {
        $patient = $appointment->getPatient();
        $doctor = $appointment->getDoctor();
        $box = $appointment->getBox();
        $reason = $appointment->getConsultationReason() ?? '';
        $status = $this->normalizeAppointmentStatus($appointment->getStatus());

        $data = [
            'id' => $appointment->getId(),
            'date' => $appointment->getVisitDate()?->format('Y-m-d'),
            'visitDate' => $appointment->getVisitDate()?->format('Y-m-d'),
            'time' => $appointment->getVisitTime()?->format('H:i'),
            'visitTime' => $appointment->getVisitTime()?->format('H:i'),
            'reason' => $reason,
            'consultationReason' => $reason,
            'observations' => $appointment->getObservations() ?? '',
            'status' => $status,
            'duration' => $appointment->getDurationMinutes(),
            'durationMinutes' => $appointment->getDurationMinutes(),
            'cleaningMinutes' => $appointment->getCleaningMinutes(),
            'patient' => [
                'id' => $patient?->getId(),
                'name' => $patient ? trim($patient->getFirstName() . ' ' . $patient->getLastName()) : 'Pacient desconegut',
            ],
            'patientId' => $patient?->getId(),
            'doctor' => [
                'id' => $doctor?->getId(),
                'name' => $doctor ? trim($doctor->getFirstName() . ' ' . $doctor->getLastNames()) : 'Sense doctor',
            ],
            'doctorId' => $doctor?->getId(),
            'box' => $box?->getBoxName() ?? 'Sense box',
            'boxId' => $box?->getId(),
            'treatmentId' => $appointment->getTreatment()?->getId(),
        ];

        if ($odontogramId !== null) {
            $data['odontogramId'] = $odontogramId;
        }

        return $data;
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

    #[Route('/create', name: 'app_appointment_create', methods: ['POST'])]
    public function create(
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

        if (array_key_exists('visitDate', $data) && ($data['visitDate'] === null || trim((string) $data['visitDate']) === '')) {
            return $this->json([
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'error' => [
                    'field' => 'visitDate',
                    'messageKey' => 'appointment.visit_date.required',
                    'received' => $data['visitDate'],
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('visitTime', $data) && ($data['visitTime'] === null || trim((string) $data['visitTime']) === '')) {
            return $this->json([
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'error' => [
                    'field' => 'visitTime',
                    'messageKey' => 'appointment.visit_time.required',
                    'received' => $data['visitTime'],
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        try {
            $form->submit($data, false);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'error' => [
                    'messageKey' => 'appointment.validation.invalid_payload',
                    'details' => $e->getMessage(),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

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
                if ($appointment->getDurationMinutes() === null) {
                    $appointment->setDurationMinutes(30);
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

                if ($this->isDoctorOccupied($repository, $appointment)) {
                    return $this->json([
                        'ok' => false,
                        'code' => 'DOCTOR_OCCUPIED',
                        'error' => [
                            'messageKey' => 'appointment.doctor.occupied',
                            'message' => 'El doctor ya tiene una cita en ese horario.',
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
                    'status' => $this->normalizeAppointmentStatus($appointment->getStatus()),
                    'appointment' => $this->serializeAppointment($appointment),
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
    ): JsonResponse {
        $appointment->setStatus(self::STATUS_IN_PROGRESS);

        $patient = $appointment->getPatient();
        $odontogramId = $patient->getLastOdontogramId();

        if (!$odontogramId) {
            $newOdontogram = new Odontogram();
            $newOdontogram->setVisit($appointment);
            $newOdontogram->setStatus('Actiu');

            $entityManager->persist($newOdontogram);
            $entityManager->flush();

            $patient->setLastOdontogramId($newOdontogram->getId());
            $odontogramId = $newOdontogram->getId();
        }

        $entityManager->flush();

        return $this->json([
            'ok' => true,
            'code' => 'APPOINTMENT_OPENED',
            'messageKey' => 'appointment.opened',
            'id' => $appointment->getId(),
            'status' => $this->normalizeAppointmentStatus($appointment->getStatus()),
            'odontogramId' => $odontogramId,
            'appointment' => $this->serializeAppointment($appointment, $odontogramId),
        ]);
    }

    #[Route('/{id}', name: 'app_appointment_read', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function read(Appointment $appointment): JsonResponse
    {
        return $this->json($this->serializeAppointment($appointment));
    }

    #[Route('/{id}/close', name: 'app_appointment_close', methods: ['POST'])]
    #[Route('/{id}/finish', name: 'app_appointment_finish', requirements: ['id' => '\d+'], methods: ['POST', 'PATCH'])]
    public function close(Appointment $appointment, EntityManagerInterface $em): JsonResponse 
    {
        $appointment->setStatus(self::STATUS_FINISHED);
        $em->flush();
        return $this->json([
            'ok' => true,
            'code' => 'APPOINTMENT_CLOSED',
            'messageKey' => 'appointment.closed',
            'id' => $appointment->getId(),
            'status' => $this->normalizeAppointmentStatus($appointment->getStatus()),
            'appointment' => $this->serializeAppointment($appointment),
        ]);
    }

    #[Route('/{id}/status', name: 'app_appointment_update_status', requirements: ['id' => '\d+'], methods: ['PATCH', 'PUT'])]
    public function updateStatus(
        Request $request,
        Appointment $appointment,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $content = $request->getContent();
        $payload = json_decode($content, true);

        if ($content !== '' && $payload === null && json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'ok' => false,
                'code' => 'INVALID_JSON',
                'error' => [
                    'messageKey' => 'request.body.invalid_json',
                    'details' => json_last_error_msg(),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $newStatus = '';

        if (is_array($payload)) {
            if (isset($payload['status'])) {
                $newStatus = trim((string) $payload['status']);
            } elseif (isset($payload['stateName'])) {
                $newStatus = trim((string) $payload['stateName']);
            }
        } else {
            $newStatus = trim((string) $payload);
        }

        if ($newStatus === '') {
            return $this->json([
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'error' => [
                    'field' => 'status',
                    'messageKey' => 'appointment.status.required_string',
                    'expected' => 'string',
                    'allowedStatuses' => self::MANUAL_STATUSES,
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $newStatus = $this->normalizeManualStatus($newStatus);

        if (!in_array($newStatus, self::MANUAL_STATUSES, true)) {
            return $this->json([
                'ok' => false,
                'code' => 'INVALID_STATUS',
                'error' => [
                    'field' => 'status',
                    'messageKey' => 'appointment.status.invalid',
                    'allowedStatuses' => self::MANUAL_STATUSES,
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
            'status' => $this->normalizeAppointmentStatus($appointment->getStatus()),
            'appointment' => $this->serializeAppointment($appointment),
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

                if ($this->isDoctorOccupied($repository, $appointment)) {
                    return $this->json([
                        'ok' => false,
                        'code' => 'DOCTOR_OCCUPIED',
                        'error' => [
                            'messageKey' => 'appointment.doctor.occupied',
                            'message' => 'El doctor ya tiene una cita en ese horario.',
                        ],
                    ], Response::HTTP_CONFLICT);
                }

                $entityManager->flush();
                
                return $this->json([
                    'ok' => true,
                    'code' => 'APPOINTMENT_UPDATED',
                    'messageKey' => 'appointment.updated',
                    'id' => $appointment->getId(),
                    'status' => $this->normalizeAppointmentStatus($appointment->getStatus()),
                    'appointment' => $this->serializeAppointment($appointment),
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
