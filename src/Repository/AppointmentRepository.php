<?php

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    public function findByDate(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.visit_date = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('a.visit_time', 'ASC')
            ->getQuery()
            ->getResult();
    }    
    
    public function findByWeek(\DateTime $date): array
    {
        // Calculamos el inicio (Lunes) y fin (Domingo) de esa semana
        $startOfWeek = (clone $date)->modify('monday this week')->setTime(0, 0);
        $endOfWeek = (clone $date)->modify('sunday this week')->setTime(23, 59, 59);

        return $this->createQueryBuilder('a')
            ->andWhere('a.visitDate >= :start')
            ->andWhere('a.visitDate <= :end')
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->orderBy('a.visitDate', 'ASC')
            ->addOrderBy('a.visitTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
