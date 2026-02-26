<?php

namespace App\Controller;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/document')]
final class DocumentController extends AbstractController
{
    private function documentToArray(Document $document): array
    {
        return [
            'id' => $document->getId(),
            'type' => $document->getType(),
            'filePath' => $document->getFilePath(),
            'captureDate' => $document->getCaptureDate()?->format('c'),
            'description' => $document->getDescription(),
            'patientId' => $document->getPatient()?->getId(),
        ];
    }
    #[Route(name: 'app_document_index', methods: ['GET'])]
    public function index(DocumentRepository $documentRepository): Response
    {
        $documents = array_map([$this, 'documentToArray'], $documentRepository->findAll());
        return $this->json($documents);
    }

    #[Route('/new', name: 'app_document_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);
        $document = new Document();
        $document->setType($data['type'] ?? '');
        $document->setFilePath($data['filePath'] ?? '');
        if (!empty($data['captureDate'])) {
            $document->setCaptureDate(new \DateTimeImmutable($data['captureDate']));
        }
        $document->setDescription($data['description'] ?? null);
        // patient assignment omitted for brevity

        $entityManager->persist($document);
        $entityManager->flush();

        return $this->json($this->documentToArray($document), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_document_show', methods: ['GET'])]
    public function show(Document $document): Response
    {
        return $this->json($this->documentToArray($document));
    }

    #[Route('/{id}', name: 'app_document_update', methods: ['PUT'])]
    public function update(Request $request, Document $document, EntityManagerInterface $entityManager): Response
    {
        // Expecting JSON payload with fields to change
        $data = json_decode($request->getContent(), true);
        if (isset($data['type'])) {
            $document->setType($data['type']);
        }
        if (isset($data['filePath'])) {
            $document->setFilePath($data['filePath']);
        }
        if (isset($data['captureDate'])) {
            $document->setCaptureDate(new \DateTimeImmutable($data['captureDate']));
        }
        if (array_key_exists('description', $data)) {
            $document->setDescription($data['description']);
        }
        // patient relationship updates not handled here

        $entityManager->flush();

        return $this->json($this->documentToArray($document));
    }

    #[Route('/{id}', name: 'app_document_delete', methods: ['DELETE'])]
    public function delete(Document $document, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($document);
        $entityManager->flush();

        return $this->json(['result' => 'deleted', 'id' => $document->getId()]);
    }
}
