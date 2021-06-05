<?php

namespace AndersBjorkland\InstagramDisplayExtension\Repository;

use AndersBjorkland\InstagramDisplayExtension\Entity\InstagramMedia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method InstagramMedia|null find($id, $lockMode = null, $lockVersion = null)
 * @method InstagramMedia|null findOneBy(array $criteria, array $orderBy = null)
 * @method InstagramMedia[]    findAll()
 * @method InstagramMedia[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstagramMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstagramMedia::class);
    }

    // /**
    //  * @return InstagramMedia[] Returns an array of InstagramMedia objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?InstagramMedia
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
