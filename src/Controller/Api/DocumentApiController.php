<?php

namespace App\Controller\Api;

use App\Entity\Document;
use App\Entity\Patient;
use App\Repository\DocumentRepository;
use App\Repository\PatientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/documents')]
final class DocumentApiController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly SerializerInterface $serializer,
    ) {
    }

    // --- Updated Route to /index to match PatientController [cite: 12-02-2026] ---
    #[Route('/index', name: 'api_document_list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $documents = $this->documentRepository->findAllDocuments();
        
        $data = $this->serializer->serialize($documents, 'json', [
            'groups' => ['document:read'],
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    // --- Added clearer path for search [cite: 12-02-2026] ---
    #[Route('/search/date', name: 'api_document_search', methods: ['GET'])]
    public function searchByDate(Request $request): JsonResponse
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

        $documents = $this->documentRepository->findByDate($date);

        $data = $this->serializer->serialize($documents, 'json', [
            'groups' => ['document:read'],
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    // --- Rest of the methods remain the same ---
    #[Route('/{id}', name: 'api_document_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
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

    #[Route('/create', name: 'api_document_create', methods: ['POST'])]
    public function create(Request $request, PatientRepository $patientRepo): JsonResponse
    {
        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
        }

        $patientId = $request->request->get('patient');
        $patient = $patientRepo->findById($patientId);
        
        if (!$patient) {
            return new JsonResponse(['error' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $filename = $this->handleFileStorage($uploadedFile, $patient);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Could not upload file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $document = $this->documentRepository->create(
            $filename, 
            $request->request->all(), 
            $patient
        );

        $data = $this->serializer->serialize($document, 'json', ['groups' => ['document:read']]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}', name: 'api_document_update', methods: ['PUT'])]
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
    public function download(Document $document): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFilePath();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('The file does not exist');
        }

        return $this->file($filePath);
    }
}