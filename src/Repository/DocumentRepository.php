<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    // --- READ OPERATIONS [cite: 12-02-2026] ---

    public function findAllDocuments(): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findById(int $id): ?Document
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByDate(\DateTime $date): array
    {
        $date->setTime(0, 0, 0);
        $nextDay = clone $date;
        $nextDay->modify('+1 day');

        return $this->createQueryBuilder('d')
            ->andWhere('d.captureDate >= :date')
            ->andWhere('d.captureDate < :nextDay')
            ->setParameter('date', $date)
            ->setParameter('nextDay', $nextDay)
            ->orderBy('d.captureDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // --- WRITE OPERATIONS [cite: 12-02-2026] ---

    public function create(string $filename, array $data, Patient $patient): Document
    {
        $document = new Document();
        $document->setFilePath($filename);
        $document->setType($data['type'] ?? '');
        $document->setCaptureDate(new \DateTimeImmutable());
        $document->setDescription($data['description'] ?? null);
        $document->setPatient($patient);

        $em = $this->getEntityManager();
        $em->persist($document);
        $em->flush();

        return $document;
    }

    public function edit(Document $document, array $data): Document
    {
        if (isset($data['type'])) {
            $document->setType($data['type']);
        }
        if (isset($data['captureDate'])) {
            $document->setCaptureDate(new \DateTimeImmutable($data['captureDate']));
        }
        if (array_key_exists('description', $data)) {
            $document->setDescription($data['description']);
        }

        $this->getEntityManager()->flush();

        return $document;
    }

    public function delete(Document $document): void
    {
        $em = $this->getEntityManager();
        $em->remove($document);
        $em->flush();
    }
}