<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Patient;
use App\Repository\DocumentRepository;
use App\Repository\AppointmentRepository;
use App\Repository\PatientRepository;
use App\Repository\RadiographAnnotationRepository;
use App\Service\PatientRecordsAccessChecker;
use App\Util\DocumentApiSerializer;
use App\Util\PatientIriParser;
use App\Util\RadiographAnnotationSerializer;
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
    private const MAX_UPLOAD_SIZE_BYTES = 52428800; // 50 MiB

    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly RadiographAnnotationRepository $annotationRepository,
        private readonly AppointmentRepository $appointmentRepository,
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
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Query parameter patientId (or patient IRI) is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $patient = $patientRepository->findById($patientId);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $dateString = $request->query->get('date');
        if (!$dateString) {
            return $this->json(['error' => 'Date parameter is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $date = new \DateTime((string) $dateString);
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid date format. Use Y-m-d'], Response::HTTP_BAD_REQUEST);
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
            return $this->json(['error' => 'Paciente no encontrado'], Response::HTTP_NOT_FOUND);
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
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Query parameter patientId is required and must match the document\'s patient.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $document = $this->documentRepository->findById($id);
        if (!$document || !$document->getPatient()) {
            return $this->json(['message' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        if ($document->getPatient()->getId() !== $patientId) {
            return $this->json([
                'error' => 'Forbidden',
                'message' => 'Document does not belong to the indicated patient.',
            ], Response::HTTP_FORBIDDEN);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFilePath();
        if (!is_file($filePath)) {
            return $this->json(['message' => 'File not found on server'], Response::HTTP_NOT_FOUND);
        }

        return $this->file(
            $filePath,
            $document->getOriginalName() ?? $document->getFilePath(),
            ResponseHeaderBag::DISPOSITION_INLINE,
            [
                'Content-Type' => $document->getType() ?: 'application/octet-stream',
            ]
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
            new OA\Parameter(name: 'patient_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
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
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Provide patientId, patient.id, or patient (IRI) to list documents. Global listing is disabled.',
            ], Response::HTTP_BAD_REQUEST);
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
            $uploadError = (int) $request->server->get('UPLOAD_ERR', 0);
            if ($uploadError === \UPLOAD_ERR_INI_SIZE || $uploadError === \UPLOAD_ERR_FORM_SIZE) {
                return $this->payloadTooLargeErrorResponse();
            }

            return $this->json([
                'error' => 'Bad request',
                'message' => 'No file provided in "file" field.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($uploadedFile->getError() === \UPLOAD_ERR_INI_SIZE || $uploadedFile->getError() === \UPLOAD_ERR_FORM_SIZE) {
            return $this->payloadTooLargeErrorResponse();
        }

        $maxUploadSize = self::MAX_UPLOAD_SIZE_BYTES;
        if ($uploadedFile->getSize() === 0) {
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Uploaded file is empty.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($uploadedFile->getSize() > $maxUploadSize) {
            return $this->payloadTooLargeErrorResponse($maxUploadSize);
        }

        $patientRaw = $request->request->get('patient');
        $patientId = PatientIriParser::parsePatientId($patientRaw);
        if ($patientId === null) {
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Field "patient" must be a numeric id or an IRI containing /api/patients/{id}.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $patient = $patientRepository->findById($patientId);
        if (!$patient) {
            return $this->json(['error' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $filename = $this->handleFileStorage($uploadedFile, $patient);
        } catch (FileException) {
            return $this->json(['error' => 'Could not upload file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $type = trim((string) $request->request->get('type', ''));
        if ($type === '') {
            $type = $uploadedFile->getClientMimeType() ?: 'application/octet-stream';
        }

        $description = $request->request->get('description');
        $description = $description !== null ? trim((string) $description) : null;
        $description = $description !== '' ? $description : null;

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
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Query parameter patientId is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $document = $this->documentRepository->findById($id);
        if (!$document || !$document->getPatient()) {
            return $this->json(['message' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        if ($document->getPatient()->getId() !== $patientId) {
            return $this->json([
                'error' => 'Forbidden',
                'message' => 'Document does not belong to the indicated patient.',
            ], Response::HTTP_FORBIDDEN);
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
            new OA\Response(response: 400, description: 'Bad request'),
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
            return $this->json(['message' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $patientId = PatientIriParser::parsePatientId($request->query->get('patientId'));
        if ($patientId === null) {
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Query parameter patientId is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$document->getPatient() || $document->getPatient()->getId() !== $patientId) {
            return $this->json([
                'error' => 'Forbidden',
                'message' => 'Document does not belong to the indicated patient.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (\array_key_exists('description', $data) && $data['description'] !== null && !\is_string($data['description'])) {
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Field "description" must be a string or null.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (\array_key_exists('description', $data) && \is_string($data['description'])) {
            $data['description'] = trim($data['description']);
        }

        $this->documentRepository->edit($document, $data);

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
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$document->getPatient() || $document->getPatient()->getId() !== $patientId) {
            return $this->json(['error' => 'Document does not belong to this patient'], Response::HTTP_FORBIDDEN);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFilePath();
        if (is_file($filePath) && !@unlink($filePath)) {
            return $this->json([
                'error' => 'Conflict',
                'message' => 'The document file could not be deleted from storage.',
            ], Response::HTTP_CONFLICT);
        }

        $this->documentRepository->delete($document);

        return $this->json(['result' => 'deleted', 'id' => $documentId], Response::HTTP_OK);
    }

    #[Route('/{id}/annotations', name: 'api_document_annotations_list', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/{id}/annotations',
        summary: 'List radiograph annotations for a document',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patientId', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'appointmentId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Annotations list'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function listAnnotations(Request $request, int $id): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        [$document, $patientId, $errorResponse] = $this->resolveDocumentByPatientScope($request, $id);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $annotations = $this->annotationRepository->findByDocumentOrdered($document);
        $appointmentId = PatientIriParser::parsePatientId($request->query->get('appointmentId'));
        if ($appointmentId !== null) {
            $annotations = array_values(array_filter(
                $annotations,
                static fn ($annotation) => $annotation->getAppointment()?->getId() === $appointmentId
            ));
        }

        return $this->json([
            'documentId' => $document->getId(),
            'patientId' => $patientId,
            'items' => RadiographAnnotationSerializer::collection($annotations),
        ], Response::HTTP_OK);
    }

    #[Route('/{id}/annotations', name: 'api_document_annotations_create', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[OA\Post(
        path: '/api/documents/{id}/annotations',
        summary: 'Create radiograph annotation',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function createAnnotation(Request $request, int $id): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        [$document, $patientId, $errorResponse] = $this->resolveDocumentByPatientScope($request, $id);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'Bad request', 'message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $appointmentId = PatientIriParser::parsePatientId($data['appointmentId'] ?? $data['visitId'] ?? null);
        if ($appointmentId === null) {
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Field appointmentId (or visitId) is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $appointment = $this->appointmentRepository->find($appointmentId);
        if ($appointment === null) {
            return $this->json(['error' => 'Appointment not found'], Response::HTTP_NOT_FOUND);
        }

        if ($appointment->getPatient()?->getId() !== $patientId) {
            return $this->json([
                'error' => 'Forbidden',
                'message' => 'Appointment does not belong to the indicated patient.',
            ], Response::HTTP_FORBIDDEN);
        }

        $payload = $data['payload'] ?? $data['data'] ?? null;
        if (!\is_array($payload)) {
            return $this->json([
                'error' => 'Bad request',
                'message' => 'Field payload (or data) must be an object.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $now = new \DateTimeImmutable();
        $annotation = new \App\Entity\RadiographAnnotation();
        $annotation
            ->setDocument($document)
            ->setPatient($document->getPatient())
            ->setAppointment($appointment)
            ->setTool((string) ($data['tool'] ?? 'generic'))
            ->setLabel(isset($data['label']) ? (string) $data['label'] : null)
            ->setColor(isset($data['color']) ? (string) $data['color'] : null)
            ->setPayload($payload)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->annotationRepository->save($annotation);

        return $this->json(RadiographAnnotationSerializer::toArray($annotation), Response::HTTP_CREATED);
    }

    #[Route('/{documentId}/annotations/{annotationId}', name: 'api_document_annotations_update', requirements: ['documentId' => '\\d+', 'annotationId' => '\\d+'], methods: ['PUT'])]
    public function updateAnnotation(Request $request, int $documentId, int $annotationId): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        [$document, $patientId, $errorResponse] = $this->resolveDocumentByPatientScope($request, $documentId);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $annotation = $this->annotationRepository->find($annotationId);
        if ($annotation === null || $annotation->getDocument()?->getId() !== $document->getId()) {
            return $this->json(['error' => 'Annotation not found'], Response::HTTP_NOT_FOUND);
        }

        if ($annotation->getPatient()?->getId() !== $patientId) {
            return $this->json(['error' => 'Forbidden', 'message' => 'Annotation does not belong to this patient.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'Bad request', 'message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        if (\array_key_exists('appointmentId', $data) || \array_key_exists('visitId', $data)) {
            $appointmentId = PatientIriParser::parsePatientId($data['appointmentId'] ?? $data['visitId']);
            if ($appointmentId === null) {
                return $this->json(['error' => 'Bad request', 'message' => 'appointmentId must be a valid id.'], Response::HTTP_BAD_REQUEST);
            }
            $appointment = $this->appointmentRepository->find($appointmentId);
            if ($appointment === null) {
                return $this->json(['error' => 'Appointment not found'], Response::HTTP_NOT_FOUND);
            }
            if ($appointment->getPatient()?->getId() !== $patientId) {
                return $this->json(['error' => 'Forbidden', 'message' => 'Appointment does not belong to this patient.'], Response::HTTP_FORBIDDEN);
            }
            $annotation->setAppointment($appointment);
        }

        if (\array_key_exists('tool', $data)) {
            $annotation->setTool((string) $data['tool']);
        }
        if (\array_key_exists('label', $data)) {
            $annotation->setLabel($data['label'] !== null ? (string) $data['label'] : null);
        }
        if (\array_key_exists('color', $data)) {
            $annotation->setColor($data['color'] !== null ? (string) $data['color'] : null);
        }
        if (\array_key_exists('payload', $data) || \array_key_exists('data', $data)) {
            $payload = $data['payload'] ?? $data['data'];
            if (!\is_array($payload)) {
                return $this->json(['error' => 'Bad request', 'message' => 'payload must be an object.'], Response::HTTP_BAD_REQUEST);
            }
            $annotation->setPayload($payload);
        }

        $annotation->setUpdatedAt(new \DateTimeImmutable());
        $this->annotationRepository->save($annotation);

        return $this->json(RadiographAnnotationSerializer::toArray($annotation), Response::HTTP_OK);
    }

    #[Route('/{documentId}/annotations/{annotationId}', name: 'api_document_annotations_delete', requirements: ['documentId' => '\\d+', 'annotationId' => '\\d+'], methods: ['DELETE'])]
    public function deleteAnnotation(Request $request, int $documentId, int $annotationId): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->clinicalForbidden();
        }

        [$document, $patientId, $errorResponse] = $this->resolveDocumentByPatientScope($request, $documentId);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $annotation = $this->annotationRepository->find($annotationId);
        if ($annotation === null || $annotation->getDocument()?->getId() !== $document->getId()) {
            return $this->json(['error' => 'Annotation not found'], Response::HTTP_NOT_FOUND);
        }

        if ($annotation->getPatient()?->getId() !== $patientId) {
            return $this->json(['error' => 'Forbidden', 'message' => 'Annotation does not belong to this patient.'], Response::HTTP_FORBIDDEN);
        }

        $this->annotationRepository->remove($annotation);

        return $this->json(['result' => 'deleted', 'id' => $annotationId], Response::HTTP_OK);
    }

    private function jsonDocumentsForPatientId(Request $request, int $patientId, PatientRepository $patientRepository): JsonResponse
    {
        $patient = $patientRepository->findById($patientId);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $documents = $this->documentRepository->findByPatientOrdered($patient);
        $members = DocumentApiSerializer::collection($documents, $this->apiBaseUrl);

        return DocumentApiSerializer::createDocumentListResponse($request, $members);
    }

    /**
     * @return array{0: ?\App\Entity\Document, 1: ?int, 2: ?JsonResponse}
     */
    private function resolveDocumentByPatientScope(Request $request, int $documentId): array
    {
        $patientId = PatientIriParser::parsePatientId($request->query->get('patientId'));
        if ($patientId === null) {
            return [null, null, $this->json([
                'error' => 'Bad request',
                'message' => 'Query parameter patientId is required.',
            ], Response::HTTP_BAD_REQUEST)];
        }

        $document = $this->documentRepository->findById($documentId);
        if ($document === null || $document->getPatient() === null) {
            return [null, null, $this->json(['message' => 'Document not found'], Response::HTTP_NOT_FOUND)];
        }

        if ($document->getPatient()->getId() !== $patientId) {
            return [null, null, $this->json([
                'error' => 'Forbidden',
                'message' => 'Document does not belong to the indicated patient.',
            ], Response::HTTP_FORBIDDEN)];
        }

        return [$document, $patientId, null];
    }

    private function resolvePatientFilterFromQuery(Request $request): ?int
    {
        $q = $request->query->all();
        // Angular puede enviar patient.id; algunos entornos lo normalizan a patient_id.
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
        return $this->json([
            'error' => 'Forbidden',
            'message' => 'You do not have permission to access patient documents.',
        ], Response::HTTP_FORBIDDEN);
    }

    private function payloadTooLargeErrorResponse(int $maxUploadSize = self::MAX_UPLOAD_SIZE_BYTES): JsonResponse
    {
        return $this->json([
            'error' => 'Payload too large',
            'message' => 'Uploaded file exceeds the maximum allowed size.',
            'maxUploadBytes' => $maxUploadSize,
        ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
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
}
