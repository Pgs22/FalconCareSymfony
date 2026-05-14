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

    /**
     * POST multipart: el campo `patient` debe ser la IRI absoluta exacta `{API_BASE}/api/patients/{id}` (sin query).
     */
    public static function parsePatientIdFromPostPatientAbsoluteIri(string $raw, string $apiBaseUrl): ?int
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $base = rtrim($apiBaseUrl, '/');
        if ($base === '') {
            return null;
        }

        $pattern = '#^' . preg_quote($base, '#') . '/api/patients/(\d+)$#';

        return preg_match($pattern, $trimmed, $m) === 1 ? (int) $m[1] : null;
    }
}
