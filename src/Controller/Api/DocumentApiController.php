<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Concerns\ApiTranslatorTrait;
use App\Entity\Patient;
use App\Repository\DocumentRepository;
use App\Repository\PatientRepository;
use App\Service\DocumentPatientAccessGuard;
use App\Service\PatientRecordsAccessChecker;
use App\Util\DocumentApiSerializer;
use App\Util\PatientIriParser;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/documents')]
#[OA\Tag(name: 'Documents')]
final class DocumentApiController extends AbstractController
{
    use ApiTranslatorTrait;

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/bmp',
        'image/tiff',
        'application/dicom',
        'application/dicom+json',
    ];

    /** @var array<string, string> */
    private const ALLOWED_EXTENSIONS = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'dcm' => 'application/dicom',
    ];

    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly PatientRecordsAccessChecker $patientRecordsAccess,
        private readonly DocumentPatientAccessGuard $documentPatientAccess,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(API_BASE_URL)%')]
        private readonly string $apiBaseUrl,
        #[Autowire('%env(int:DOCUMENT_MAX_UPLOAD_BYTES)%')]
        private readonly int $maxUploadBytes,
    ) {
    }

    #[Route('/captureDate', name: 'api_document_search', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/captureDate',
        summary: 'Search documents by capture date (scoped to one patient)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'patientId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patient.id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patient_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patient', in: 'query', required: false, description: 'IRI u otros alias como en GET /api/documents', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Documents for patient on date'),
            new OA\Response(response: 400, description: 'Bad request'),
        ]
    )]
    public function findByCaptureDate(Request $request, PatientRepository $patientRepository): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        $patientId = $this->resolvePatientFilterFromQuery($request);
        if ($patientId === null) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_PATIENT_FILTER_REQUIRED');
        }

        $patient = $patientRepository->findById($patientId);
        if (!$patient) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'PATIENT_NOT_FOUND');
        }

        $dateString = $request->query->get('date');
        if (!$dateString) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DATE_REQUIRED');
        }

        try {
            $date = new \DateTime((string) $dateString);
        } catch (\Exception) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'INVALID_DATE');
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = min(100, max(1, (int) $request->query->get('limit', '10')));
        $paginator = $this->documentRepository->findByCaptureDate($patient, $date, $page, $limit);

        $documents = iterator_to_array($paginator);
        $members = DocumentApiSerializer::collection($documents, $this->apiBaseUrl);

        return DocumentApiSerializer::createDocumentListResponse($request, $members);
    }

    #[Route('/patient-docs/{identityDocument}', name: 'api_documents_by_nif', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/patient-docs/{identityDocument}',
        summary: 'List documents by patient identity document',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'identityDocument', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Document list'),
            new OA\Response(response: 404, description: 'Patient not found'),
        ]
    )]
    public function findByIdentityDocumentPatient(
        string $identityDocument,
        PatientRepository $patientRepository,
    ): JsonResponse {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        $patients = $patientRepository->findByIdentityDocument($identityDocument);
        if ($patients === []) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'PATIENT_NOT_FOUND');
        }

        $patient = $patients[0];
        $documents = $this->documentRepository->findByPatientOrdered($patient);
        $members = DocumentApiSerializer::collection($documents, $this->apiBaseUrl);

        return $this->json($members, Response::HTTP_OK);
    }

    #[Route('/{id}/download', name: 'api_document_download', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/{id}/download',
        summary: 'Download document file (patientId required)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(
                name: 'patientId',
                in: 'query',
                required: true,
                description: 'Must match the document owner patient (prevents IDOR).',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Binary file'),
            new OA\Response(response: 400, description: 'Missing patientId'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 413, description: 'Payload too large'),
        ]
    )]
    public function download(Request $request, int $id): Response
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        $patientId = PatientIriParser::parsePatientId($request->query->get('patientId'));
        if ($patientId === null) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_PATIENT_ID_REQUIRED');
        }

        $document = $this->documentRepository->findById($id);
        if ($err = $this->documentPatientAccess->validateDocumentOwnership($document, $patientId)) {
            return $this->apiError($err['status'], $err['code']);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFilePath();
        if (!is_file($filePath)) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'DOCUMENT_FILE_NOT_FOUND');
        }

        $downloadName = $document->getOriginalName() ?? $document->getFilePath();

        return $this->file(
            $filePath,
            $downloadName,
            ResponseHeaderBag::DISPOSITION_INLINE,
            ['Content-Type' => $document->getType() ?? 'application/octet-stream']
        );
    }

    #[Route('', name: 'api_document_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents',
        summary: 'List documents for one patient (patientId, patient.id, or patient IRI required)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'patientId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patient.id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patient_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patient', in: 'query', required: false, description: 'IRI absoluta {API_BASE}/api/patients/{id}', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Array or hydra:Collection'),
            new OA\Response(response: 400, description: 'Missing patient filter'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function list(Request $request, PatientRepository $patientRepository): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        $patientId = $this->resolvePatientFilterFromQuery($request);
        if ($patientId === null) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_PATIENT_FILTER_REQUIRED');
        }

        return $this->jsonDocumentsForPatientId($request, $patientId, $patientRepository);
    }

    #[Route('', name: 'api_document_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/documents',
        summary: 'Upload document (multipart)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file', 'patient'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'patient', type: 'string', description: 'IRI absoluta exacta: {API_BASE}/api/patients/{id}'),
                        new OA\Property(property: 'type', type: 'string', example: 'application/pdf', description: 'MIME declarado; application/octet-stream se normaliza según extensión'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Patient not found'),
            new OA\Response(response: 413, description: 'Payload too large (see details.maxUploadBytes)'),
        ]
    )]
    public function create(Request $request, PatientRepository $patientRepository): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof UploadedFile) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_FILE_REQUIRED');
        }

        if (\UPLOAD_ERR_INI_SIZE === $uploadedFile->getError() || \UPLOAD_ERR_FORM_SIZE === $uploadedFile->getError()) {
            return $this->apiError(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'DOCUMENT_FILE_TOO_LARGE');
        }
        if ($uploadedFile->getError() !== \UPLOAD_ERR_OK) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_FILE_UPLOAD_ERROR');
        }

        $patientRaw = $request->request->get('patient');
        if (!\is_string($patientRaw) || trim($patientRaw) === '') {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_PATIENT_REQUIRED');
        }

        $patientId = PatientIriParser::parsePatientIdFromPostPatientAbsoluteIri($patientRaw, $this->apiBaseUrl);
        if ($patientId === null) {
            $base = rtrim($this->apiBaseUrl, '/');

            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_PATIENT_ABSOLUTE_IRI_REQUIRED',
                ['apiBaseUrl' => $base],
                ['%base_url%' => $base]
            );
        }

        $patient = $patientRepository->findById($patientId);
        if (!$patient) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'PATIENT_NOT_FOUND');
        }

        $size = $uploadedFile->getSize() ?? 0;
        if ($size <= 0) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_FILE_EMPTY');
        }
        if ($size > $this->maxUploadBytes) {
            return $this->apiError(
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
                'DOCUMENT_FILE_TOO_LARGE',
                [],
                ['%max_bytes%' => (string) $this->maxUploadBytes]
            );
        }

        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        if ($extension === '' || !\array_key_exists($extension, self::ALLOWED_EXTENSIONS)) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_FILE_EXTENSION_NOT_ALLOWED');
        }

        $resolvedMimeFromExtension = self::ALLOWED_EXTENSIONS[$extension];
        $detectedMime = $uploadedFile->getMimeType() ?: $uploadedFile->getClientMimeType() ?: 'application/octet-stream';

        $detectedAllowed = \in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)
            || $detectedMime === 'application/octet-stream';
        if (!$detectedAllowed) {
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_FILE_MIME_NOT_ALLOWED',
                ['mimeType' => $detectedMime],
                ['%mime_type%' => $detectedMime]
            );
        }

        if ($detectedMime !== 'application/octet-stream' && $detectedMime !== $resolvedMimeFromExtension) {
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_FILE_MIME_EXTENSION_MISMATCH',
                ['mimeType' => $detectedMime, 'expectedMime' => $resolvedMimeFromExtension],
                ['%mime_type%' => $detectedMime, '%expected_mime%' => $resolvedMimeFromExtension]
            );
        }

        $typeRaw = trim((string) $request->request->get('type', ''));
        if ($typeRaw !== '') {
            if ($typeRaw === 'application/octet-stream') {
                $finalStoredType = $resolvedMimeFromExtension;
            } elseif (!\in_array($typeRaw, self::ALLOWED_MIME_TYPES, true)) {
                return $this->apiError(
                    Response::HTTP_BAD_REQUEST,
                    'DOCUMENT_TYPE_NOT_ALLOWED',
                    ['type' => $typeRaw],
                    ['%type%' => $typeRaw]
                );
            } elseif ($typeRaw !== $resolvedMimeFromExtension) {
                return $this->apiError(
                    Response::HTTP_BAD_REQUEST,
                    'DOCUMENT_TYPE_FILE_MISMATCH',
                    ['type' => $typeRaw, 'expectedType' => $resolvedMimeFromExtension],
                    ['%type%' => $typeRaw, '%expected_type%' => $resolvedMimeFromExtension]
                );
            } else {
                $finalStoredType = $typeRaw;
            }
        } else {
            $finalStoredType = $detectedMime === 'application/octet-stream' ? $resolvedMimeFromExtension : $detectedMime;
        }

        try {
            $filename = $this->handleFileStorage($uploadedFile, $patient);
        } catch (FileException) {
            return $this->apiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'DOCUMENT_FILE_STORE_FAILED');
        }

        $description = $request->request->get('description');
        $description = $description !== null && $description !== '' ? (string) $description : null;

        $originalName = $uploadedFile->getClientOriginalName() ?: null;

        $document = $this->documentRepository->create(
            $filename,
            ['type' => $finalStoredType, 'description' => $description],
            $patient,
            $originalName
        );

        return $this->json(
            DocumentApiSerializer::toArray($document, $this->apiBaseUrl),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'api_document_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/{id}',
        summary: 'Get document metadata (patientId query required)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patientId', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Document'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function findById(Request $request, int $id): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        $patientId = PatientIriParser::parsePatientId($request->query->get('patientId'));
        if ($patientId === null) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_PATIENT_ID_REQUIRED');
        }

        $document = $this->documentRepository->findById($id);
        if ($err = $this->documentPatientAccess->validateDocumentOwnership($document, $patientId)) {
            return $this->apiError($err['status'], $err['code']);
        }

        return $this->json(DocumentApiSerializer::toArray($document, $this->apiBaseUrl), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_document_update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[OA\Put(
        path: '/api/documents/{id}',
        summary: 'Update document metadata',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(
                name: 'patientId',
                in: 'query',
                required: true,
                description: 'Must match the document owner patient.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        $document = $this->documentRepository->findById($id);
        $patientId = PatientIriParser::parsePatientId($request->query->get('patientId'));
        if ($patientId === null) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_PATIENT_ID_REQUIRED');
        }

        if ($err = $this->documentPatientAccess->validateDocumentOwnership($document, $patientId)) {
            return $this->apiError($err['status'], $err['code']);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'INVALID_JSON');
        }

        if (!\array_key_exists('description', $data)) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_DESCRIPTION_REQUIRED');
        }
        if ($data['description'] !== null && !\is_string($data['description'])) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_DESCRIPTION_INVALID');
        }

        $this->documentRepository->edit($document, ['description' => $data['description']]);

        return $this->json(DocumentApiSerializer::toArray($document, $this->apiBaseUrl), Response::HTTP_OK);
    }

    #[Route('/{patientId}/{documentId}', name: 'api_document_delete', requirements: ['patientId' => '\\d+', 'documentId' => '\\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/documents/{patientId}/{documentId}',
        summary: 'Delete document (patient scoped)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'patientId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'documentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(int $patientId, int $documentId): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        $document = $this->documentRepository->findById($documentId);
        if ($err = $this->documentPatientAccess->validateDocumentOwnership($document, $patientId)) {
            return $this->apiError($err['status'], $err['code']);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFilePath();
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $this->documentRepository->delete($document);

        return $this->json(['result' => 'deleted', 'id' => $documentId], Response::HTTP_OK);
    }

    private function jsonDocumentsForPatientId(Request $request, int $patientId, PatientRepository $patientRepository): JsonResponse
    {
        $patient = $patientRepository->findById($patientId);
        if (!$patient) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'PATIENT_NOT_FOUND');
        }

        $documents = $this->documentRepository->findByPatientOrdered($patient);
        $members = DocumentApiSerializer::collection($documents, $this->apiBaseUrl);

        return DocumentApiSerializer::createDocumentListResponse($request, $members);
    }

    private function resolvePatientFilterFromQuery(Request $request): ?int
    {
        $q = $request->query->all();
        // Angular suele enviar patient.id; PHP puede normalizar el punto a guion bajo en QUERY_STRING.
        foreach (['patient.id', 'patient_id'] as $key) {
            if (\array_key_exists($key, $q)) {
                return PatientIriParser::parsePatientId($q[$key]);
            }
        }
        if (\array_key_exists('patientId', $q)) {
            return PatientIriParser::parsePatientId($q['patientId']);
        }
        if (\array_key_exists('patient', $q) && !\is_array($q['patient'])) {
            return PatientIriParser::parsePatientId($q['patient']);
        }
        if (\array_key_exists('patient[id]', $q)) {
            return PatientIriParser::parsePatientId($q['patient[id]']);
        }
        if (isset($q['patient']) && \is_array($q['patient']) && \array_key_exists('id', $q['patient'])) {
            return PatientIriParser::parsePatientId($q['patient']['id']);
        }

        return null;
    }

    private function clinicalForbidden(): JsonResponse
    {
        return $this->apiError(Response::HTTP_FORBIDDEN, 'DOCUMENT_ACCESS_FORBIDDEN');
    }

    private function handleFileStorage(UploadedFile $file, Patient $patient): string
    {
        $extension = $file->guessExtension() ?: 'bin';
        $prefix = 'p' . $patient->getId();
        $newFilename = $prefix . '_' . uniqid() . '.' . $extension;
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/documents';
        $file->move($uploadDir, $newFilename);

        return $newFilename;
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, string|int|float|bool|null> $transParams
     */
    private function apiError(int $status, string $code, array $details = [], array $transParams = []): JsonResponse
    {
        $maxUploadBytes = null;
        if ($status === Response::HTTP_REQUEST_ENTITY_TOO_LARGE) {
            $maxUploadBytes = $this->maxUploadBytes;
            $details['maxUploadBytes'] = $maxUploadBytes;
        }

        $payload = [
            'error' => $this->apiHttpLine($status),
            'code' => $code,
            'message' => $this->apiTrans(
                ($code === 'DOCUMENT_FILE_TOO_LARGE' && isset($transParams['%max_bytes%']))
                    ? 'DOCUMENT_FILE_TOO_LARGE_BYTES'
                    : $code,
                $transParams
            ),
            'status' => $status,
            'details' => (object) $details,
        ];
        if ($maxUploadBytes !== null) {
            $payload['maxUploadBytes'] = $maxUploadBytes;
        }

        return $this->json($payload, $status);
    }
}
