<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\PatientIriParser;
use PHPUnit\Framework\TestCase;

final class PatientIriParserTest extends TestCase
{
    public function testParsesPatient11Not1(): void
    {
        self::assertSame(11, PatientIriParser::parsePatientId('http://127.0.0.1:8000/api/patients/11'));
        self::assertSame(1, PatientIriParser::parsePatientId('http://127.0.0.1:8000/api/patients/1'));
    }

    public function testParsesNumericString(): void
    {
        self::assertSame(11, PatientIriParser::parsePatientId('11'));
    }
}
