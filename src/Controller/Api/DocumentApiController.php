<?php

namespace App\Controller\Api;

use App\Entity\Document;
use App\Entity\Patient;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentRepository $documentRepository,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route(name: 'api_document_list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $documents = $this->documentRepository->findBy([], ['id' => 'ASC']);
        $data = $this->serializer->serialize($documents, 'json', [
            'groups' => ['document:read'],
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'api_document_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Document $document): JsonResponse
    {
        $data = $this->serializer->serialize($document, 'json', [
            'groups' => ['document:read'],
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route(name: 'api_document_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // 1. Validate file existence [cite: 12-02-2026]
        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
        }

        // 2. Validate patient existence - REQUIRED FOR DOCUMENTS [cite: 12-02-2026]
        $patientId = $request->request->get('patient');
        if (!$patientId) {
            return new JsonResponse(['error' => 'Patient ID is required to upload a document'], Response::HTTP_BAD_REQUEST);
        }

        $patient = $this->entityManager->getRepository(Patient::class)->find($patientId);
        
        if (!$patient) {
            return new JsonResponse(['error' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        // 3. Handle physical file storage [cite: 12-02-2026]
        try {
            // Calling the private helper method [cite: 12-02-2026]
            $filename = $this->handleFileStorage($uploadedFile, $patient);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Could not upload file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // 4. Create and persist entity [cite: 12-02-2026]
        $document = new Document();
        $document->setFilePath($filename);
        $document->setType((string) $request->request->get('type', ''));
        $document->setCaptureDate(new \DateTimeImmutable());
        $document->setDescription($request->request->get('description'));
        $document->setPatient($patient);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        // 5. Serialize response [cite: 12-02-2026]
        $data = $this->serializer->serialize($document, 'json', ['groups' => ['document:read']]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{patientId}/{documentId}', name: 'api_document_delete', methods: ['DELETE'])]
    public function delete(
        int $patientId,
        int $documentId,
        EntityManagerInterface $entityManager,
        DocumentRepository $documentRepository
    ): Response {
        // 1. Find the document by its unique ID [cite: 12-02-2026]
        $document = $documentRepository->find($documentId);

        if (!$document) {
            return $this->json(['error' => 'Document not found'], 404);
        }

        // 2. Security validation: Does the document belong to this patient? [cite: 12-02-2026]
        if ($document->getPatient()->getId() !== $patientId) {
            return $this->json(['error' => 'Document does not belong to this patient'], 403);
        }

        // 3. Delete physical file
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFilePath();
        if (file_exists($filePath)) { unlink($filePath); }

        // 4. Remove from database [cite: 12-02-2026]
        $entityManager->remove($document);
        $entityManager->flush();

        return $this->json(['result' => 'deleted', 'id' => $documentId]);
    }

    /**
     * Private method to manage file moving and renaming [cite: 12-02-2026]
     */
    private function handleFileStorage(UploadedFile $file, ?Patient $patient): string
    {
        $extension = $file->guessExtension() ?: 'bin';
        
        // Professional Naming: p{id}_{uniqid}.{ext} or anonymous_{uniqid}.{ext} [cite: 12-02-2026]
        $prefix = $patient ? 'p' . $patient->getId() : 'anonymous';
        $newFilename = $prefix . '_' . uniqid() . '.' . $extension;

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/documents';
        
        $file->move($uploadDir, $newFilename);

        return $newFilename;
    }

    /**
     * Return the raw file for a document. Useful for downloads.
     */
    #[Route('/{id}/download', name: 'api_document_download', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function download(Document $document): Response
    {
        $file = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFilePath();
        return $this->file($file);
    }
}