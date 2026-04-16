<?php

declare(strict_types=1);

namespace App\Util;

use App\Entity\Appointment;

/**
 * Builds a single JSON object for appointment history with duplicate keys
 * so Angular clients can read reason/time under several aliases.
 */
final class AppointmentHistoryPayloadBuilder
{
    public static function build(Appointment $a): array
    {
        $patient = $a->getPatient();
        $pid = $patient->getId();
        $visitDate = $a->getVisitDate();
        $visitTime = $a->getVisitTime();

        $start = self::combineLocalDateTime($visitDate, $visitTime);
        $iso = $start->format(\DateTimeInterface::ATOM);
        $endIso = self::computeEndIso($a, $start);

        $reason = $a->getConsultationReason() ?? '';
        $obs = $a->getObservations() ?? '';

        return [
            'id' => $a->getId(),
            'reason' => $reason,
            'motive' => $reason,
            'title' => $reason,
            'type' => $reason,
            'visit_type' => $reason,
            'consultation_reason' => $reason,
            'notes' => $obs,
            'clinical_notes' => $obs,
            'observations' => $obs,
            'description' => $obs,
            'summary' => $obs,
            'startTime' => $iso,
            'start_time' => $iso,
            'scheduledAt' => $iso,
            'scheduled_at' => $iso,
            'begin_at' => $iso,
            'appointment_date' => $visitDate->format('Y-m-d'),
            'date' => $visitDate->format('Y-m-d'),
            'created_at' => $iso,
            'end_time' => $endIso,
            'status' => $a->getStatus(),
            'durationMinutes' => $a->getDurationMinutes(),
            'patient' => [
                '@id' => '/api/patients/'.$pid,
                'id' => $pid,
            ],
            'patientId' => $pid,
        ];
    }

    /**
     * @param list<Appointment> $appointments
     * @return list<array<string, mixed>>
     */
    public static function buildList(array $appointments): array
    {
        return array_map(static fn (Appointment $a) => self::build($a), $appointments);
    }

    private static function combineLocalDateTime(\DateTimeInterface $date, \DateTimeInterface $time): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromInterface($date);
        $t = \DateTimeImmutable::createFromInterface($time);

        return $d->setTime(
            (int) $t->format('H'),
            (int) $t->format('i'),
            (int) $t->format('s')
        );
    }

    private static function computeEndIso(Appointment $a, \DateTimeImmutable $start): ?string
    {
        $minutes = $a->getDurationMinutes();
        if ($minutes === null || $minutes <= 0) {
            return null;
        }

        return $start->modify('+'.$minutes.' minutes')->format(\DateTimeInterface::ATOM);
    }
}
