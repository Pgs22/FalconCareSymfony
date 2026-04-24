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
    private const PLACEHOLDER_VALUES = [
        'n/a',
        'na',
        'none',
        'null',
        'ninguna',
        'cap',
        'cap coneguda',
        'sin alergias',
        'sin alergia',
        'no allergies',
        'no allergy',
    ];
    /**
     * For POST: requires at least one key; values must be non-empty after trim; both keys must match if present.
     *
     * @return array{ok: true, value: string}|array{ok: false, message: string}
     */
    public static function resolveForCreate(array $data): array
    {
        $hasCamel = \array_key_exists('medicationAllergies', $data);
        $hasSnake = \array_key_exists('medication_allergies', $data);
        $hasFlags = self::hasAllergyFlags($data);

        if (!$hasCamel && !$hasSnake && !$hasFlags) {
            return ['ok' => false, 'message' => 'Missing required fields'];
        }

        $r = self::mergeTwoKeys($data, $hasCamel, $hasSnake);
        if ($r['error'] !== null) {
            return ['ok' => false, 'message' => $r['error']];
        }

        if ($r['value'] === '' && $hasFlags) {
            return ['ok' => true, 'value' => self::serializeFlagsToString($data)];
        }

        if ($r['value'] === '' && !$hasFlags) {
            return ['ok' => false, 'message' => 'Missing required fields'];
        }

        return ['ok' => true, 'value' => $r['value']];
    }

    /**
     * For PUT partial update: returns null if neither key sent; otherwise resolved value or error if mismatch.
     *
     * @return array{apply: false}|array{apply: true, value: string}|array{apply: true, error: string}
     */
    public static function resolveForPartialUpdate(array $data): array
    {
        $hasCamel = \array_key_exists('medicationAllergies', $data);
        $hasSnake = \array_key_exists('medication_allergies', $data);
        $hasFlags = self::hasAllergyFlags($data);

        if (!$hasCamel && !$hasSnake && !$hasFlags) {
            return ['apply' => false];
        }

        $r = self::mergeTwoKeys($data, $hasCamel, $hasSnake);
        if ($r['error'] !== null) {
            return ['apply' => true, 'error' => $r['error']];
        }

        if ($r['value'] === '' && $hasFlags) {
            return ['apply' => true, 'value' => self::serializeFlagsToString($data)];
        }

        return ['apply' => true, 'value' => $r['value']];
    }

    /**
     * @return array{value: string, error: ?string}
     */
    private static function mergeTwoKeys(array $data, bool $hasCamel, bool $hasSnake): array
    {
        if ($hasCamel && $hasSnake) {
            $v1 = self::normalizeString($data['medicationAllergies'] ?? null);
            $v2 = self::normalizeString($data['medication_allergies'] ?? null);
            if ($v1 !== $v2) {
                return ['value' => '', 'error' => 'medicationAllergies and medication_allergies must match when both are provided'];
            }

            return ['value' => $v1, 'error' => null];
        }

        if ($hasCamel) {
            return ['value' => self::normalizeString($data['medicationAllergies'] ?? null), 'error' => null];
        }

        return ['value' => self::normalizeString($data['medication_allergies'] ?? null), 'error' => null];
    }

    private static function normalizeString(mixed $v): string
    {
        if ($v === null) {
            return '';
        }

        $raw = trim((string) $v);
        if ($raw === '') {
            return '';
        }

        $raw = (string) preg_replace(
            '/\b(n\/a|na|none|null|ninguna|cap coneguda|cap|sin alergias|sin alergia|no allergies|no allergy)\b/iu',
            '',
            $raw
        );

        $parts = preg_split('/[,;|\/]+/', $raw) ?: [];
        $normalized = [];
        $seen = [];

        foreach ($parts as $part) {
            $item = trim($part);
            if ($item === '') {
                continue;
            }

            $lower = mb_strtolower($item);
            if (in_array($lower, self::PLACEHOLDER_VALUES, true)) {
                continue;
            }

            if (isset($seen[$lower])) {
                continue;
            }

            $seen[$lower] = true;
            $normalized[] = $item;
        }

        return implode(', ', $normalized);
    }

    private static function hasAllergyFlags(array $data): bool
    {
        return array_key_exists('selectedAllergies', $data)
            || array_key_exists('selected_allergies', $data)
            || array_key_exists('allergiesBitmask', $data)
            || array_key_exists('allergies_bitmask', $data);
    }

    private static function serializeFlagsToString(array $data): string
    {
        $selected = [];
        if (array_key_exists('selectedAllergies', $data) && is_array($data['selectedAllergies'])) {
            $selected = $data['selectedAllergies'];
        } elseif (array_key_exists('selected_allergies', $data) && is_array($data['selected_allergies'])) {
            $selected = $data['selected_allergies'];
        } elseif (array_key_exists('allergiesBitmask', $data) || array_key_exists('allergies_bitmask', $data)) {
            $bitmask = (int) ($data['allergiesBitmask'] ?? $data['allergies_bitmask'] ?? 0);
            $catalog = \App\Entity\Patient::getAllergyCatalog();
            foreach ($catalog as $flag => $label) {
                if (($bitmask & (int) $flag) === (int) $flag) {
                    $selected[] = $flag;
                }
            }
        }

        $catalog = \App\Entity\Patient::getAllergyCatalog();
        $labels = [];
        $seen = [];
        foreach ($selected as $value) {
            $flag = (int) $value;
            if (!isset($catalog[$flag])) {
                continue;
            }
            $label = (string) $catalog[$flag];
            $key = mb_strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $labels[] = $label;
        }

        return implode(', ', $labels);
    }
}
