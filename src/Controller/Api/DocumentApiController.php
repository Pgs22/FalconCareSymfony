<?php

namespace App\Controller\Api;

use App\Entity\Document;
use App\Entity\Patient;
use App\Repository\DocumentRepository;
use App\Repository\PatientRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

#[Route('/api/documents')]
#[OA\Tag(name: 'Documents')]
final class DocumentApiController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * To search all documents
     */
    #[Route('', name: 'api_document_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents',
        summary: 'List documents',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Document list')]
    )]
    public function getAll(): JsonResponse
    {
        $documents = $this->documentRepository->getAll();

        return $this->json($documents, Response::HTTP_OK, [], [
            'groups' => ['document:read']
        ]);
    }

    /**
     * To search for documents by date YYYY-MM-DD
     */
    #[Route('/captureDate', name: 'api_document_search', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/captureDate',
        summary: 'Search documents by capture date',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'))],
        responses: [
            new OA\Response(response: 200, description: 'Paginator/collection'),
            new OA\Response(response: 400, description: 'Bad request'),
        ]
    )]
    public function findByCaptureDate(Request $request): JsonResponse
    {
        $dateString = $request->query->get('date');

        if (!$dateString) {
            return $this->json(['error' => 'Date parameter is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $date = new \DateTime($dateString);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format. Use Y-m-d'], Response::HTTP_BAD_REQUEST);
        }

        $documents = $this->documentRepository->findByCaptureDate($date);

        return $this->json($documents, Response::HTTP_OK, [], [
            'groups' => ['document:read']
        ]);
    }

    /**
     * To search for documents by ID
     */
    #[Route('/{id}', name: 'api_document_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/{id}',
        summary: 'Get document metadata',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Document'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function findById(int $id): JsonResponse
    {
        $document = $this->documentRepository->findById($id);

        if (!$document) {
            return $this->json(['message' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->serializer->serialize($document, 'json', [
            'groups' => ['document:read'],
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * To create documents and we used method handleFileStorage() to rename with id Patient and 
     * store the file in the server. The route path to save on database.
     */
    #[Route('', name: 'api_document_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/documents',
        summary: 'Upload document',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file', 'patient', 'type'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'patient', type: 'integer', example: 1),
                        new OA\Property(property: 'type', type: 'string', example: 'xray'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 404, description: 'Patient not found'),
        ]
    )]
    public function create(Request $request, PatientRepository $patientRepo): JsonResponse
    {
        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            return $this->json(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
        }

        $patientId = $request->request->get('patient');
        $patient = $patientRepo->findById($patientId);
        
        if (!$patient) {
            return $this->json(['error' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $filename = $this->handleFileStorage($uploadedFile, $patient);
        } catch (FileException $e) {
            return $this->json(['error' => 'Could not upload file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $document = $this->documentRepository->create(
            $filename, 
            $request->request->all(), 
            $patient
        );

        return $this->json($document, Response::HTTP_CREATED, [], [
            'groups' => ['document:read']
        ]);
    }

    #[Route('/{id}', name: 'api_document_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/documents/{id}',
        summary: 'Update document metadata',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $document = $this->documentRepository->findById($id);

        if (!$document) {
            return $this->json(['message' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        $this->documentRepository->edit($document, $data);

        $jsonContent = $this->serializer->serialize($document, 'json', ['groups' => ['document:read']]);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    #[Route('/{patientId}/{documentId}', name: 'api_document_delete', methods: ['DELETE'])]
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
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$document->getPatient() || $document->getPatient()->getId() !== $patientId) {
            return $this->json(['error' => 'Document does not belong to this patient'], Response::HTTP_FORBIDDEN);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFilePath();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->documentRepository->delete($document);

        return new JsonResponse(['result' => 'deleted', 'id' => $documentId], Response::HTTP_OK);
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

    #[Route('/{id}/download', name: 'api_document_download', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/{id}/download',
        summary: 'Download document file',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Binary file'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function download(Document $document): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFilePath();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('The file does not exist');
        }

        return $this->file($filePath);
    }

    /**
     * We search for patient documents by their NIF field
     */
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
        PatientRepository $patientRepo,
        DocumentRepository $documentRepo
    ): JsonResponse {

        $patients = $patientRepo->findByIdentityDocument($identityDocument);
        
        if (empty($patients)) {
            return $this->json(['error' => 'Paciente no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Al ser un array lo que devuelve tu repo, pillamos el primero
        $patient = $patients[0];

        // Buscamos los documentos asociados
        $documents = $documentRepo->findBy(['patient' => $patient]);

        // En lugar de hacer un foreach, pasamos la colección directamente
        // Symfony usará los Groups para saber qué campos mostrar
        return $this->json($documents, Response::HTTP_OK, [], [
            'groups' => ['document:read']
        ]);
    }
}