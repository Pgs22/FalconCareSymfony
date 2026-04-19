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
            ->andWhere('a.visitDate = :date') 
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('a.visitTime', 'ASC')
            ->getQuery()
            ->getResult();
    }    
    
    public function findByWeek(\DateTime $date): array
    {
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


    public function findOverlappingAppointments($date, $startTime, int $duration, $boxId, $excludeId = null, ?int $cleaningMinutes = null): array 
    {
        if ($startTime instanceof \DateTimeInterface) {
            $start = \DateTimeImmutable::createFromInterface($startTime);
        } else {
            $start = new \DateTimeImmutable($startTime ?? 'now');
        }

        $totalDuration = $duration + max(0, (int) ($cleaningMinutes ?? Appointment::DEFAULT_CLEANING_MINUTES));
        $endTime = $start->modify("+" . $totalDuration . " minutes");

        $dateParam = ($date instanceof \DateTimeInterface) ? $date->format('Y-m-d') : $date;

        $qb = $this->createQueryBuilder('a')
            ->where('a.visitDate = :date')
            ->andWhere('a.box = :boxId')
            ->setParameter('date', $dateParam)
            ->setParameter('boxId', $boxId);

        if ($excludeId) {
            $qb->andWhere('a.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $results = $qb->getQuery()->getResult();

        return array_filter($results, function(Appointment $existing) use ($start, $endTime) {
            $exStartRaw = $existing->getVisitTime();
            if (!$exStartRaw) return false;

            $exStart = \DateTimeImmutable::createFromInterface($exStartRaw);
            
            $exTotalDuration = $existing->getTotalDurationWithCleaning(); 
            $exEnd = $exStart->modify("+" . $exTotalDuration . " minutes");
            
            return ($start < $exEnd && $endTime > $exStart);
        });
    }
}


