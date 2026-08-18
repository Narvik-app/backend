<?php

namespace App\Repository\ClubDependent;

use App\Entity\ClubDependent\MemberControlType;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Interface\SortableRepositoryInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\SortableEntityRepositoryTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberControlType>
 */
class MemberControlTypeRepository extends ServiceEntityRepository implements SortableRepositoryInterface, ClubLinkedInterface {
  use SortableEntityRepositoryTrait;
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, MemberControlType::class);
  }

  /**
   * @return MemberControlType[]
   */
  public function findAllAutomaticByClub(\App\Entity\Club $club): array {
    $qb = $this->createQueryBuilder('t');
    $this->applyClubRestriction($qb, $club);
    return $qb
      ->andWhere('t.activity IS NOT NULL')
      ->getQuery()
      ->getResult();
  }
}
