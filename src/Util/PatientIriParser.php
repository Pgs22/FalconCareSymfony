<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Extrae el id numérico de paciente desde un entero en string o una IRI absoluta/parcial
 * que contenga el segmento /api/patients/{id}.
 */
final class PatientIriParser
{
    public static function parsePatientId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (\is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (\is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            if (preg_match('/^\d+$/', $trimmed) === 1) {
                $id = (int) $trimmed;

                return $id > 0 ? $id : null;
            }

            if (preg_match('~/api/patients/(\d+)(?:\s|[?#]|$)~', $trimmed, $m) === 1) {
                return (int) $m[1];
            }
        }

        return null;
    }
}
