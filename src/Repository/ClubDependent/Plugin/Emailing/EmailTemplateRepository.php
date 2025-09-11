<?php

namespace App\Repository\ClubDependent\Plugin\Emailing;

use App\Entity\ClubDependent\Plugin\Emailing\EmailTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailTemplate>
 */
class EmailTemplateRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, EmailTemplate::class);
  }

  //    /**
  //     * @return EmailTemplate[] Returns an array of EmailTemplate objects
  //     */
  //    public function findByExampleField($value): array
  //    {
  //        return $this->createQueryBuilder('e')
  //            ->andWhere('e.exampleField = :val')
  //            ->setParameter('val', $value)
  //            ->orderBy('e.id', 'ASC')
  //            ->setMaxResults(10)
  //            ->getQuery()
  //            ->getResult()
  //        ;
  //    }

  //    public function findOneBySomeField($value): ?EmailTemplate
  //    {
  //        return $this->createQueryBuilder('e')
  //            ->andWhere('e.exampleField = :val')
  //            ->setParameter('val', $value)
  //            ->getQuery()
  //            ->getOneOrNullResult()
  //        ;
  //    }
}
