<?php

declare(strict_types=1);

namespace App\Util;

use App\Entity\Document;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formato de salida alineado con el front Angular (aliases + JSON-LD parcial).
 */
final class DocumentApiSerializer
{
    public static function toArray(Document $document, string $apiBaseUrl): array
    {
        $base = rtrim($apiBaseUrl, '/');
        $patient = $document->getPatient();
        $patientId = $patient?->getId();
        $patientIri = $patientId !== null ? $base . '/api/patients/' . $patientId : null;
        $docIri = $base . '/api/documents/' . $document->getId();
        $storedPath = $document->getFilePath() ?? '';
        $displayName = $document->getOriginalName() ?? $storedPath;
        $mime = $document->getType() ?? 'application/octet-stream';
        $capture = $document->getCaptureDate()?->format(DATE_ATOM);
        $desc = $document->getDescription();
        $downloadUrl = $base . '/api/documents/' . $document->getId() . '/download';

        return [
            '@id' => $docIri,
            '@type' => 'Document',
            'id' => $document->getId(),
            'patient' => [
                '@id' => $patientIri,
                'id' => $patientId,
            ],
            'patientId' => $patientId,
            'patient_id' => $patientId,
            'description' => $desc,
            'notes' => $desc,
            'clinicalNotes' => $desc,
            'clinical_notes' => $desc,
            'captureDate' => $capture,
            'capture_date' => $capture,
            'createdAt' => $capture,
            'created_at' => $capture,
            'originalName' => $displayName,
            'original_name' => $displayName,
            'originalFilename' => $displayName,
            'original_filename' => $displayName,
            'fileName' => $displayName,
            'file_name' => $displayName,
            'filename' => $displayName,
            'name' => $displayName,
            'title' => $displayName,
            'path' => $storedPath,
            'filePath' => $storedPath,
            'file_path' => $storedPath,
            'storagePath' => $storedPath !== '' ? 'uploads/documents/' . basename($storedPath) : '',
            'storage_path' => $storedPath !== '' ? 'uploads/documents/' . basename($storedPath) : '',
            'url' => $downloadUrl,
            'fileUrl' => $downloadUrl,
            'file_url' => $downloadUrl,
            'mimeType' => $mime,
            'mime_type' => $mime,
            'type' => $mime,
        ];
    }

    /**
     * @param iterable<Document> $documents
     *
     * @return list<array<string, mixed>>
     */
    public static function collection(iterable $documents, string $apiBaseUrl): array
    {
        $out = [];
        foreach ($documents as $document) {
            $out[] = self::toArray($document, $apiBaseUrl);
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $members
     *
     * @return array<string, mixed>
     */
    public static function hydraCollection(array $members, string $collectionIri): array
    {
        $count = \count($members);

        return [
            '@context' => [
                'hydra' => 'http://www.w3.org/ns/hydra/core#',
            ],
            '@id' => $collectionIri,
            '@type' => 'hydra:Collection',
            'hydra:member' => $members,
            'hydra:totalItems' => $count,
            'member' => $members,
            'totalItems' => $count,
        ];
    }

    public static function wantsHydra(Request $request): bool
    {
        if ($request->query->get('hydra') === '1') {
            return true;
        }

        return str_contains((string) $request->headers->get('Accept', ''), 'application/ld+json');
    }

    /**
     * @param list<array<string, mixed>> $members
     */
    public static function createDocumentListResponse(Request $request, array $members, int $status = Response::HTTP_OK): JsonResponse
    {
        $collectionIri = $request->getSchemeAndHttpHost() . $request->getRequestUri();
        if (self::wantsHydra($request)) {
            return new JsonResponse(
                self::hydraCollection($members, $collectionIri),
                $status,
                ['Content-Type' => 'application/ld+json']
            );
        }

        return new JsonResponse($members, $status);
    }
}
