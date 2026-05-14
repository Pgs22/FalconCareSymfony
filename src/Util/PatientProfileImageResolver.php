<?php

namespace App\Util;

/**
 * Resolves patient profile image from JSON with priority:
 * profile_image > profile_image_url > profileImage.
 */
final class PatientProfileImageResolver
{
    public const MAX_LENGTH = 2_000_000;

    /**
     * @return array{present: false}|array{present: true, value: string|null}
     */
    public static function pickFromArray(array $data): array
    {
        if (\array_key_exists('profile_image', $data)) {
            return ['present' => true, 'value' => $data['profile_image']];
        }
        if (\array_key_exists('profile_image_url', $data)) {
            return ['present' => true, 'value' => $data['profile_image_url']];
        }
        if (\array_key_exists('profileImage', $data)) {
            return ['present' => true, 'value' => $data['profileImage']];
        }

        return ['present' => false];
    }

    /**
     * @return array{ok: true, value: ?string}|array{ok: false, messageKey: string, messageParams?: array<string, string|int>}
     */
    public static function validateAndNormalize(mixed $value): array
    {
        if ($value === null) {
            return ['ok' => true, 'value' => null];
        }
        if (!\is_string($value)) {
            return ['ok' => false, 'messageKey' => 'PATIENT_PROFILE_IMAGE_NOT_STRING'];
        }
        $trimmed = trim($value);
        if (\strlen($trimmed) > self::MAX_LENGTH) {
            return [
                'ok' => false,
                'messageKey' => 'PATIENT_PROFILE_IMAGE_TOO_LARGE',
                'messageParams' => ['%max_chars%' => (string) self::MAX_LENGTH],
            ];
        }
        if ($trimmed !== '' && !preg_match('#^data:image/[a-zA-Z0-9.+-]+;base64,#', $trimmed)) {
            return ['ok' => false, 'messageKey' => 'PATIENT_PROFILE_IMAGE_INVALID_DATA_URL'];
        }

        return ['ok' => true, 'value' => $trimmed === '' ? null : $trimmed];
    }

    /**
     * Normalizes stored value for JSON: empty string is treated as no image (null).
     */
    public static function normalizeForApi(?string $stored): ?string
    {
        if ($stored === null) {
            return null;
        }
        $t = trim($stored);

        return $t === '' ? null : $t;
    }
}
