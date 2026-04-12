<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * Acceso a historias clínicas, documentos y mutaciones de paciente en la API (panel médico).
 */
final class PatientRecordsAccessChecker
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function canAccessPatientClinicalApi(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN')
            || $this->security->isGranted('ROLE_DOCTOR')
            || $this->security->isGranted('ROLE_STAFF');
    }
}
