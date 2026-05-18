<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Patient;
use App\Util\AppointmentHistoryPayloadBuilder;

/**
 * Serializa citas para historial de paciente y listados filtrados por patientId.
 */
final class AppointmentListSerializer
{
    /**
     * @param list<Appointment> $appointments
     *
     * @return list<array<string, mixed>>
     */
    public function serializeList(array $appointments): array
    {
        return AppointmentHistoryPayloadBuilder::buildList($appointments);
    }

    /**
     * Bloques de agenda (misma forma que GET /api/appointment/index).
     *
     * @param list<Appointment> $appointments
     *
     * @return list<array<string, mixed>>
     */
    public function serializeAgendaBlocks(array $appointments): array
    {
        $result = [];

        foreach ($appointments as $appointment) {
            $patient = $appointment->getPatient();
            $reason = $appointment->getConsultationReason() ?? '';
            $status = $this->normalizeAppointmentStatus($appointment->getStatus());

            $isUrgency = $appointment->isUrgency()
                || str_contains(mb_strtolower($reason), 'urgència')
                || str_contains(mb_strtolower($reason), 'urgencia');
            $isFirstVisit = $appointment->isFirstVisit()
                || str_contains(mb_strtolower($reason), 'primera visita');

            if ($status === 'Finalitzada') {
                $color = '#9e9e9e';
            } elseif ($isUrgency) {
                $color = '#e91e63';
            } elseif ($isFirstVisit) {
                $color = '#9c27b0';
            } else {
                $color = '#00bcd4';
            }

            $visitDate = $appointment->getVisitDate();

            $result[] = [
                'id' => $appointment->getId(),
                'date' => $visitDate?->format('Y-m-d'),
                'visitDate' => $visitDate?->format('Y-m-d'),
                'time' => $appointment->getVisitTime() ? $appointment->getVisitTime()->format('H:i') : '--:--',
                'duration' => $appointment->getDurationMinutes() ?? 30,
                'cleaningTime' => $appointment->getCleaningMinutes(),
                'cleaning_time' => $appointment->getCleaningMinutes(),
                'cleaningMinutes' => $appointment->getCleaningMinutes(),
                'totalBlockTime' => $appointment->getTotalDurationWithCleaning(),
                'patientName' => $patient
                    ? trim($patient->getFirstName() . ' ' . $patient->getLastName())
                    : 'Sense Pacient',
                'patientId' => $patient?->getId(),
                'doctorName' => $appointment->getDoctor()
                    ? trim($appointment->getDoctor()->getFirstName() . ' ' . $appointment->getDoctor()->getLastNames())
                    : 'Sense Doctor',
                'doctorId' => $appointment->getDoctor()?->getId(),
                'boxId' => $appointment->getBox()?->getId(),
                'box' => $appointment->getBox() ? $appointment->getBox()->getBoxName() : 'Sense Box',
                'reason' => $reason,
                'status' => $status,
                'color' => $color,
                'isUrgency' => $isUrgency,
                'isFirstVisit' => $isFirstVisit,
                'allergyLabels' => $this->buildAllergyLabels($patient),
            ];
        }

        return $result;
    }

    private function normalizeAppointmentStatus(?string $status): string
    {
        $status = trim((string) $status);

        if ($status === '') {
            return 'Programada';
        }

        return match ($status) {
            'Encurs' => 'En curs',
            'Falta Consentiment' => 'Falta consentiment',
            'Cancel·lada', 'CancelÂ·lada' => 'Cancelada',
            default => $status,
        };
    }

    /**
     * @return list<string>
     */
    private function buildAllergyLabels(?Patient $patient): array
    {
        if ($patient === null) {
            return [];
        }

        $labels = [];
        foreach (Patient::getAllergyCatalog() as $flag => $label) {
            if ($patient->hasAllergy($flag)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }
}
