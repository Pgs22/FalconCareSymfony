<?php

namespace App\Repository;

use App\Entity\Treatment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Treatment>
 */
class TreatmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Treatment::class);
    }

    /**
     * Actualiza el estado de un tratamiento a 'Finalitzat' por su ID.
     */
    public function markAsFinished(int $treatmentId): int
    {
        return $this->createQueryBuilder('t')
            ->update()
            ->set('t.status', ':newStatus')
            ->where('t.id = :id')
            ->setParameter('newStatus', 'Finalitzat')
            ->setParameter('id', $treatmentId)
            ->getQuery()
            ->execute();
    }

    /**
     * Obtiene los tratamientos activos de un paciente a través de sus citas.
     */
    public function findActiveTreatmentsByPatient(int $patientId): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.appointments', 'a')
            ->innerJoin('a.patient', 'p')
            ->leftJoin('t.pathologies', 'path')
            ->leftJoin('path.pathology_type', 'pt')
            ->addSelect('path', 'pt')
            ->where('p.id = :patientId')
            ->andWhere('t.status = :status')
            ->setParameter('patientId', $patientId)
            ->setParameter('status', 'Actiu')
            ->getQuery()
            ->getResult();
    }
}