<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Appointment;

/**
 * Lista de citas en JSON (misma forma que {@see \App\Controller\Api\AppointmentController} index/weekly)
 * para reutilizar en subrecursos (p. ej. historial por paciente).
 */
final class AppointmentListSerializer
{
    private const STATUS_SCHEDULED = 'Programada';
    private const STATUS_MISSING_CONSENT = 'Falta consentiment';
    private const STATUS_IN_PROGRESS = 'En curs';
    private const STATUS_FINISHED = 'Finalitzada';

    public function normalizeStatus(?string $status): string
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

    /**
     * @param list<Appointment> $appointments
     * @return list<array<string, mixed>>
     */
    public function serializeList(array $appointments): array
    {
        $result = [];
        foreach ($appointments as $appointment) {
            $reason = $appointment->getConsultationReason() ?? '';
            $status = $this->normalizeStatus($appointment->getStatus());

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

            $patient = $appointment->getPatient();

            $result[] = [
                'id' => $appointment->getId(),
                'date' => $appointment->getVisitDate()->format('Y-m-d'),
                'time' => $appointment->getVisitTime() ? $appointment->getVisitTime()->format('H:i') : '--:--',
                'duration' => $appointment->getDurationMinutes() ?? 30,
                'cleaningTime' => $appointment->getCleaningMinutes(),
                'cleaning_time' => $appointment->getCleaningMinutes(),
                'cleaningMinutes' => $appointment->getCleaningMinutes(),
                'totalBlockTime' => $appointment->getTotalDurationWithCleaning(),
                'patientId' => $patient ? $patient->getId() : null,
                'patientName' => $patient
                    ? $patient->getFirstName().' '.$patient->getLastName()
                    : 'Sense Pacient',
                'doctorName' => $appointment->getDoctor()
                    ? trim($appointment->getDoctor()->getFirstName().' '.$appointment->getDoctor()->getLastNames())
                    : 'Sense Doctor',
                'doctorId' => $appointment->getDoctor() ? $appointment->getDoctor()->getId() : null,
                'boxId' => $appointment->getBox() ? $appointment->getBox()->getId() : null,
                'box' => $appointment->getBox() ? $appointment->getBox()->getBoxName() : 'Sense Box',
                'reason' => $reason,
                'status' => $status,
                'color' => $color,
                'isUrgency' => $isUrgency,
                'isFirstVisit' => $isFirstVisit,
            ];
        }

        return $result;
    }
}
