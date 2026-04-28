<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Patient;
use PHPUnit\Framework\TestCase;

final class PatientAllergiesBitmaskTest extends TestCase
{
    public function testChlorhexidineAllergyIsPartOfBitmaskCatalog(): void
    {
        self::assertSame(16, Patient::ALLERGY_CHLORHEXIDINE);
        self::assertSame('Chlorhexidine', Patient::getAllergyCatalog()[Patient::ALLERGY_CHLORHEXIDINE]);

        $bitmask = Patient::buildAllergiesBitmask([
            Patient::ALLERGY_PENICILLIN,
            Patient::ALLERGY_CHLORHEXIDINE,
        ]);

        self::assertSame(17, $bitmask);
    }
}
