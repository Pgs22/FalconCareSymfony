<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;

/**
 * Única fuente de verdad para «documento pertenece al paciente X» en la API REST de documentos.
 */
final class DocumentPatientAccessGuard
{
    /**
     * @return array{status: int, code: string}|null null si el documento existe y pertenece al paciente
     */
    public function validateDocumentOwnership(?Document $document, int $patientId): ?array
    {
        if (!$document || !$document->getPatient()) {
            return [
                'status' => 404,
                'code' => 'DOCUMENT_NOT_FOUND',
            ];
        }

        if ($document->getPatient()->getId() !== $patientId) {
            return [
                'status' => 403,
                'code' => 'DOCUMENT_PATIENT_MISMATCH',
            ];
        }

        return null;
    }
}
