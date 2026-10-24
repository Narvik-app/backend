<?php

namespace App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberVehicle>
 * @implements ClubLinkedInterface<MemberVehicle>
 */
class MemberVehicleRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, MemberVehicle::class);
  }

  /**
   * Find all enabled vehicles for a member
   */
  public function findEnabledVehiclesForMember(string $memberUuid): array {
    return $this->createQueryBuilder('mv')
      ->join('mv.member', 'm')
      ->where('m.uuid = :memberUuid')
      ->andWhere('mv.isEnabled = :isEnabled')
      ->setParameter('memberUuid', $memberUuid)
      ->setParameter('isEnabled', true)
      ->orderBy('mv.brand', 'ASC')
      ->orderBy('mv.model', 'ASC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Find vehicles that can be deleted (no declarations in the current year)
   */
  public function findVehiclesDeletableForYear(Club $club): array {
    $qb = $this->createQueryBuilder('mv');
    $this->applyClubRestriction($qb, $club);

    return $qb
      ->join('mv.member', 'm')
      ->leftJoin('mv.timeAndTravelDeclarations', 'd')
      ->where('c.uuid = :clubUuid')
      ->andWhere('d.date IS NULL OR YEAR(d.date) != :year')
      ->setParameter('year', date('Y'))
      ->getQuery()
      ->getResult();
  }
}
