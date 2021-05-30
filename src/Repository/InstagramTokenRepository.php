<?php

namespace AndersBjorkland\InstagramDisplayExtension\Repository;

use AndersBjorkland\InstagramDisplayExtension\Entity\InstagramToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method InstagramToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method InstagramToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method InstagramToken[]    findAll()
 * @method InstagramToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstagramTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstagramToken::class);
    }

    // /**
    //  * @return InstagramToken[] Returns an array of InstagramToken objects
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
    public function findOneBySomeField($value): ?InstagramToken
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
