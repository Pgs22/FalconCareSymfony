<?php

namespace App\Util;

/**
 * Normalizes JSON keys medicationAllergies (camelCase) and medication_allergies (snake_case)
 * to a single string for the Patient entity (Doctrine column medication_allergies).
 *
 * Preferred response key for GET payloads: medicationAllergies (canonical); medication_allergies is mirrored for compatibility.
 */
final class PatientMedicationAllergiesResolver
{
    /**
     * For POST: requires at least one key; values must be non-empty after trim; both keys must match if present.
     *
     * @return array{ok: true, value: string}|array{ok: false, messageKey: string}
     */
    public static function resolveForCreate(array $data): array
    {
        $hasCamel = \array_key_exists('medicationAllergies', $data);
        $hasSnake = \array_key_exists('medication_allergies', $data);

        if (!$hasCamel && !$hasSnake) {
            return ['ok' => false, 'messageKey' => 'PATIENT_ALLERGIES_REQUIRED'];
        }

        $r = self::mergeTwoKeys($data, $hasCamel, $hasSnake);
        if ($r['errorKey'] !== null) {
            return ['ok' => false, 'messageKey' => $r['errorKey']];
        }

        if ($r['value'] === '') {
            return ['ok' => false, 'messageKey' => 'PATIENT_ALLERGIES_REQUIRED'];
        }

        return ['ok' => true, 'value' => $r['value']];
    }

    /**
     * For PUT partial update: returns null if neither key sent; otherwise resolved value or error if mismatch.
     *
     * @return array{apply: false}|array{apply: true, value: string}|array{apply: true, errorKey: string}
     */
    public static function resolveForPartialUpdate(array $data): array
    {
        $hasCamel = \array_key_exists('medicationAllergies', $data);
        $hasSnake = \array_key_exists('medication_allergies', $data);

        if (!$hasCamel && !$hasSnake) {
            return ['apply' => false];
        }

        $r = self::mergeTwoKeys($data, $hasCamel, $hasSnake);
        if ($r['errorKey'] !== null) {
            return ['apply' => true, 'errorKey' => $r['errorKey']];
        }

        return ['apply' => true, 'value' => $r['value']];
    }

    /**
     * @return array{value: string, errorKey: ?string}
     */
    private static function mergeTwoKeys(array $data, bool $hasCamel, bool $hasSnake): array
    {
        if ($hasCamel && $hasSnake) {
            $v1 = self::normalizeString($data['medicationAllergies'] ?? null);
            $v2 = self::normalizeString($data['medication_allergies'] ?? null);
            if ($v1 !== $v2) {
                return ['value' => '', 'errorKey' => 'PATIENT_ALLERGIES_KEYS_MISMATCH'];
            }

            return ['value' => $v1, 'errorKey' => null];
        }

        if ($hasCamel) {
            return ['value' => self::normalizeString($data['medicationAllergies'] ?? null), 'errorKey' => null];
        }

        return ['value' => self::normalizeString($data['medication_allergies'] ?? null), 'errorKey' => null];
    }

    private static function normalizeString(mixed $v): string
    {
        if ($v === null) {
            return '';
        }

        return trim((string) $v);
    }
}
