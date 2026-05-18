<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\PatientMedicationAllergiesResolver;
use PHPUnit\Framework\TestCase;

final class PatientMedicationAllergiesResolverTest extends TestCase
{
    public function testCreateAllowsMissingMedicationAllergies(): void
    {
        self::assertSame(
            ['ok' => true, 'value' => ''],
            PatientMedicationAllergiesResolver::resolveForCreate([])
        );
    }

    public function testCreateAllowsEmptyMedicationAllergies(): void
    {
        self::assertSame(
            ['ok' => true, 'value' => ''],
            PatientMedicationAllergiesResolver::resolveForCreate(['medicationAllergies' => ''])
        );
    }

    public function testCreateStillRejectsMismatchedMedicationAllergyAliases(): void
    {
        self::assertSame(
            [
                'ok' => false,
                'message' => 'medicationAllergies and medication_allergies must match when both are provided',
            ],
            PatientMedicationAllergiesResolver::resolveForCreate([
                'medicationAllergies' => 'Penicillin',
                'medication_allergies' => '',
            ])
        );
    }
}
