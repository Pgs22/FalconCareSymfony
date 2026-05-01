<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Patient;
use App\Repository\DocumentRepository;
use App\Repository\PatientRepository;
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

#[Route('/api/documents')]
#[OA\Tag(name: 'Documents')]
final class DocumentApiController extends AbstractController
{
    private const MAX_UPLOAD_BYTES = 10_485_760; // 10 MiB

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
        #[Autowire('%env(API_BASE_URL)%')]
        private readonly string $apiBaseUrl,
    ) {
    }

    #[Route('/captureDate', name: 'api_document_search', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/captureDate',
        summary: 'Search documents by capture date (scoped to one patient)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'patientId', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
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

        $patientId = PatientIriParser::parsePatientId($request->query->get('patientId') ?? $request->query->get('patient'));
        if ($patientId === null) {
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_PATIENT_FILTER_REQUIRED',
                'Query parameter patientId (or patient IRI) is required.'
            );
        }

        $patient = $patientRepository->findById($patientId);
        if (!$patient) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'PATIENT_NOT_FOUND', 'Patient not found');
        }

        $dateString = $request->query->get('date');
        if (!$dateString) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DATE_REQUIRED', 'Date parameter is required');
        }

        try {
            $date = new \DateTime((string) $dateString);
        } catch (\Exception) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'INVALID_DATE', 'Invalid date format. Use Y-m-d');
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
            return $this->apiError(Response::HTTP_NOT_FOUND, 'PATIENT_NOT_FOUND', 'Patient not found');
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
        ]
    )]
    public function download(Request $request, int $id): Response
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        $patientId = PatientIriParser::parsePatientId($request->query->get('patientId'));
        if ($patientId === null) {
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_PATIENT_ID_REQUIRED',
                'Query parameter patientId is required and must match the document\'s patient.'
            );
        }

        $document = $this->documentRepository->findById($id);
        if (!$document || !$document->getPatient()) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'DOCUMENT_NOT_FOUND', 'Document not found');
        }

        if ($document->getPatient()->getId() !== $patientId) {
            return $this->apiError(
                Response::HTTP_FORBIDDEN,
                'DOCUMENT_PATIENT_MISMATCH',
                'Document does not belong to the indicated patient.'
            );
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFilePath();
        if (!is_file($filePath)) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'DOCUMENT_FILE_NOT_FOUND', 'File not found on server');
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
            new OA\Parameter(name: 'patient', in: 'query', required: false, description: 'IRI absoluta /api/patients/{id}', schema: new OA\Schema(type: 'string')),
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
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_PATIENT_FILTER_REQUIRED',
                'Provide patientId, patient.id, or patient (IRI) to list documents. Global listing is disabled.'
            );
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
                    required: ['file', 'patient', 'type'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'patient', type: 'string', description: 'IRI absoluta p. ej. http://127.0.0.1:8000/api/patients/1'),
                        new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
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
        ]
    )]
    public function create(Request $request, PatientRepository $patientRepository): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof UploadedFile) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_FILE_REQUIRED', 'No file provided');
        }

        if (\UPLOAD_ERR_INI_SIZE === $uploadedFile->getError() || \UPLOAD_ERR_FORM_SIZE === $uploadedFile->getError()) {
            return $this->apiError(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'DOCUMENT_FILE_TOO_LARGE', 'Uploaded file exceeds size limit.');
        }
        if ($uploadedFile->getError() !== \UPLOAD_ERR_OK) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_FILE_UPLOAD_ERROR', 'Upload error detected.');
        }

        $patientRaw = $request->request->get('patient');
        $patientId = PatientIriParser::parsePatientId($patientRaw);
        if ($patientId === null) {
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_PATIENT_INVALID',
                'Field "patient" must be a numeric id or an IRI containing /api/patients/{id}.'
            );
        }

        $patient = $patientRepository->findById($patientId);
        if (!$patient) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'PATIENT_NOT_FOUND', 'Patient not found');
        }

        $size = $uploadedFile->getSize() ?? 0;
        if ($size <= 0) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_FILE_EMPTY', 'Uploaded file is empty.');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            return $this->apiError(
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
                'DOCUMENT_FILE_TOO_LARGE',
                'Uploaded file exceeds the maximum allowed size (10 MiB).'
            );
        }

        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        if ($extension === '' || !\array_key_exists($extension, self::ALLOWED_EXTENSIONS)) {
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_FILE_EXTENSION_NOT_ALLOWED',
                'File extension is not allowed.'
            );
        }

        $detectedMime = $uploadedFile->getMimeType() ?: $uploadedFile->getClientMimeType() ?: 'application/octet-stream';
        if (!\in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_FILE_MIME_NOT_ALLOWED',
                'File MIME type is not allowed.',
                ['mimeType' => $detectedMime]
            );
        }

        $typeRaw = trim((string) $request->request->get('type', ''));
        if ($typeRaw !== '' && !\in_array($typeRaw, self::ALLOWED_MIME_TYPES, true)) {
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_TYPE_NOT_ALLOWED',
                'Provided type is not allowed.',
                ['type' => $typeRaw]
            );
        }

        try {
            $filename = $this->handleFileStorage($uploadedFile, $patient);
        } catch (FileException) {
            return $this->apiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'DOCUMENT_FILE_STORE_FAILED', 'Could not upload file');
        }

        $type = trim((string) $request->request->get('type', ''));
        if ($type === '') {
            $type = $detectedMime;
        }

        $description = $request->request->get('description');
        $description = $description !== null && $description !== '' ? (string) $description : null;

        $originalName = $uploadedFile->getClientOriginalName() ?: null;

        $document = $this->documentRepository->create(
            $filename,
            ['type' => $type, 'description' => $description],
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
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_PATIENT_ID_REQUIRED', 'Query parameter patientId is required.');
        }

        $document = $this->documentRepository->findById($id);
        if (!$document || !$document->getPatient()) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'DOCUMENT_NOT_FOUND', 'Document not found');
        }

        if ($document->getPatient()->getId() !== $patientId) {
            return $this->apiError(
                Response::HTTP_FORBIDDEN,
                'DOCUMENT_PATIENT_MISMATCH',
                'Document does not belong to the indicated patient.'
            );
        }

        return $this->json(DocumentApiSerializer::toArray($document, $this->apiBaseUrl), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_document_update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[OA\Put(
        path: '/api/documents/{id}',
        summary: 'Update document metadata',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
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
        if (!$document) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'DOCUMENT_NOT_FOUND', 'Document not found');
        }

        $patientId = PatientIriParser::parsePatientId($request->query->get('patientId'));
        if ($patientId === null) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'DOCUMENT_PATIENT_ID_REQUIRED', 'Query parameter patientId is required.');
        }

        if (!$document->getPatient() || $document->getPatient()->getId() !== $patientId) {
            return $this->apiError(
                Response::HTTP_FORBIDDEN,
                'DOCUMENT_PATIENT_MISMATCH',
                'Document does not belong to the indicated patient.'
            );
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->apiError(Response::HTTP_BAD_REQUEST, 'INVALID_JSON', 'Invalid JSON');
        }

        if (!\array_key_exists('description', $data)) {
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_DESCRIPTION_REQUIRED',
                'Field "description" is required for document note updates.'
            );
        }
        if ($data['description'] !== null && !\is_string($data['description'])) {
            return $this->apiError(
                Response::HTTP_BAD_REQUEST,
                'DOCUMENT_DESCRIPTION_INVALID',
                'Field "description" must be a string or null.'
            );
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
        if (!$document) {
            return $this->apiError(Response::HTTP_NOT_FOUND, 'DOCUMENT_NOT_FOUND', 'Document not found');
        }

        if (!$document->getPatient() || $document->getPatient()->getId() !== $patientId) {
            return $this->apiError(Response::HTTP_FORBIDDEN, 'DOCUMENT_PATIENT_MISMATCH', 'Document does not belong to this patient');
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
            return $this->apiError(Response::HTTP_NOT_FOUND, 'PATIENT_NOT_FOUND', 'Patient not found');
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
        if (\array_key_exists('patient', $q)) {
            return PatientIriParser::parsePatientId($q['patient']);
        }

        return null;
    }

    private function clinicalForbidden(): JsonResponse
    {
        return $this->apiError(
            Response::HTTP_FORBIDDEN,
            'DOCUMENT_ACCESS_FORBIDDEN',
            'You do not have permission to access patient documents.'
        );
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
     */
    private function apiError(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return $this->json([
            'error' => Response::$statusTexts[$status] ?? 'Error',
            'code' => $code,
            'message' => $message,
            'status' => $status,
            'details' => (object) $details,
        ], $status);
    }
}
