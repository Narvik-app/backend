<?php

namespace App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberVehicle>
 * @implements ClubLinkedInterface<MemberVehicle>
 */
class MemberVehicleRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use ClubLinkedTrait;
  use UuidEntityRepositoryTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, MemberVehicle::class);
  }

  /**
   * Whether the vehicle is referenced by at least one declaration (used to guard deletion)
   */
  public function isReferencedByDeclarations(MemberVehicle $vehicle): bool {
    $count = $this->getEntityManager()
      ->createQueryBuilder()
      ->select('COUNT(d.id)')
      ->from(TimeAndTravelDeclaration::class, 'd')
      ->where('d.memberVehicle = :vehicle')
      ->setParameter('vehicle', $vehicle)
      ->getQuery()
      ->getSingleScalarResult();

    return $count > 0;
  }
}
