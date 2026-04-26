<?php

namespace App\Repository;

use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Patient>
 */
class PatientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Patient::class);
    }

    public function getAll(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findById(int $id): ?Patient
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByIdentityDocument(string $identityDocument): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.identityDocument = :doc')
            ->setParameter('doc', $identityDocument)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdentityDocument(string $identityDocument): ?Patient
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.identityDocument = :doc')
            ->setParameter('doc', $identityDocument)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByName(string $name): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('LOWER(p.firstName) LIKE LOWER(:name) OR LOWER(p.lastName) LIKE LOWER(:name)')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('p.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search by first name, last name, full name, and/or id.
     *
     * If the term is only digits, resolves by primary key first (doctor panel / Neon id lookup)
     * before running the broader text query — avoids empty results when CONCAT/LIKE behaves
     * differently across drivers and keeps numeric search deterministic.
     *
     * Full name uses nested CONCAT (DQL supports two arguments per CONCAT only).
     */
    public function search(string $term): array
    {
        $trimmed = trim($term);
        if ($trimmed === '') {
            return $this->getAll();
        }

        if (ctype_digit($trimmed)) {
            $byId = $this->findById((int) $trimmed);
            if ($byId !== null) {
                return [$byId];
            }
        }

        try {
            return $this->searchByNameOrId($trimmed);
        } catch (\Throwable) {
            try {
                return $this->findByName($trimmed);
            } catch (\Throwable) {
                return [];
            }
        }
    }

    /**
     * @throws \Doctrine\ORM\Query\QueryException
     */
    private function searchByNameOrId(string $trimmed): array
    {
        $like = '%' . $trimmed . '%';
        $qb = $this->createQueryBuilder('p');
        $orX = $qb->expr()->orX(
            'LOWER(p.firstName) LIKE LOWER(:like)',
            'LOWER(p.lastName) LIKE LOWER(:like)',
            'LOWER(CONCAT(CONCAT(p.firstName, \' \'), p.lastName)) LIKE LOWER(:like)'
        );

        if (ctype_digit($trimmed)) {
            $orX->add($qb->expr()->eq('p.id', ':searchId'));
            $qb->setParameter('searchId', (int) $trimmed);
        }

        return $qb
            ->where($orX)
            ->setParameter('like', $like)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function create(Patient $patient): Patient
    {
        $em = $this->getEntityManager();
        $em->persist($patient);
        $em->flush();

        return $patient;
    }

    public function edit(Patient $patient): Patient
    {
        $em = $this->getEntityManager();
        $em->persist($patient);
        $em->flush();

        return $patient;
    }

    public function delete(Patient $patient): void
    {
        $em = $this->getEntityManager();
        $em->remove($patient);
        $em->flush();
    }
}