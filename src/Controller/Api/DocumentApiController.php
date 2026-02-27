<?php

namespace App\Controller\Api;

use App\Entity\Document;
use App\Entity\Patient;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
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
            // you may want to expose specific fields only
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'api_document_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Document $document): JsonResponse
    {
        $data = $this->serializer->serialize($document, 'json', []);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * Upload a new document file. Expects a multipart/form-data request with a "file" field.
     * Additional form parameters: type, description, patient (id).
     */
    #[Route(name: 'api_document_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
        }

        $newFilename = uniqid('', true) . '.' . $uploadedFile->guessExtension();

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/documents';
        if (!is_dir($uploadDir)) {
            // try to create the directory if it doesn't exist (permissions permitting)
            @mkdir($uploadDir, 0777, true);
        }

        try {
            $uploadedFile->move(
                $uploadDir,
                $newFilename
            );
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Could not upload file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $document = new Document();
        $document->setFilePath($newFilename);
        $document->setType((string) $request->request->get('type', ''));
        $document->setCaptureDate(new \DateTimeImmutable());
        $document->setDescription($request->request->get('description'));

        $pacient = null;
        $patientId = $request->request->get('patient');
        if ($patientId) {
            $patient = $this->entityManager->getRepository(Patient::class)->find($patientId);
        }
        $document->setPatient($patient);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $data = $this->serializer->serialize($document, 'json', []);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    /**
     * Return the raw file for a document. Useful for downloads.
     */
    #[Route('/{id}/download', name: 'api_document_download', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function download(Document $document): Response
    {
        $file = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $document->getFilePath();
        return $this->file($file);
    }
}
