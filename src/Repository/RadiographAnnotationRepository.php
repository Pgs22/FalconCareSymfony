<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Document;
use App\Entity\RadiographAnnotation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RadiographAnnotation>
 */
class RadiographAnnotationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RadiographAnnotation::class);
    }

    /**
     * @return list<RadiographAnnotation>
     */
    public function findByDocumentOrdered(Document $document): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.document = :document')
            ->setParameter('document', $document)
            ->orderBy('a.updatedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(RadiographAnnotation $annotation): RadiographAnnotation
    {
        $em = $this->getEntityManager();
        $em->persist($annotation);
        $em->flush();

        return $annotation;
    }

    public function remove(RadiographAnnotation $annotation): void
    {
        $em = $this->getEntityManager();
        $em->remove($annotation);
        $em->flush();
    }
}
