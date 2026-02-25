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