<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\File;
use App\Enum\FileCategory;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\Extension\Core\Type\FileType;

/**
 * @extends ServiceEntityRepository<File>
 */
class FileRepository extends ServiceEntityRepository {
  use UuidEntityRepositoryTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, File::class);
  }

  /**
   * @return File[] Returns an array of File objects
   */
  public function findByClubAndCategory(Club $club, FileCategory $category): array {
    return $this->createQueryBuilder('f')
                ->andWhere('f.club = :club')
                ->andWhere('f.category = :category')
                ->setParameter('club', $club)
                ->setParameter('category', $category)
                ->getQuery()
                ->getResult();
  }

  /**
   * @return File[]
   */
  public function findAllExpiredEmailAttachments(): array {
    return $this->createQueryBuilder('f')
                ->andWhere('f.category <= :category')
                ->andWhere('f.createdAt <= :date')
                ->setParameter('category', FileCategory::club_email)
                ->setParameter('date', new \DateTimeImmutable()->sub(new \DateInterval('P7D')))
                ->getQuery()
                ->getResult();
  }

  //    /**
  //     * @return File[] Returns an array of File objects
  //     */
  //    public function findByExampleField($value): array
  //    {
  //        return $this->createQueryBuilder('f')
  //            ->andWhere('f.exampleField = :val')
  //            ->setParameter('val', $value)
  //            ->orderBy('f.id', 'ASC')
  //            ->setMaxResults(10)
  //            ->getQuery()
  //            ->getResult()
  //        ;
  //    }

  //    public function findOneBySomeField($value): ?File
  //    {
  //        return $this->createQueryBuilder('f')
  //            ->andWhere('f.exampleField = :val')
  //            ->setParameter('val', $value)
  //            ->getQuery()
  //            ->getOneOrNullResult()
  //        ;
  //    }
}
