<?php

namespace App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportAttestation;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeAndTravelExportAttestation>
 * @implements ClubLinkedInterface<TimeAndTravelExportAttestation>
 */
class TimeAndTravelExportAttestationRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use ClubLinkedTrait;
  use UuidEntityRepositoryTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, TimeAndTravelExportAttestation::class);
  }
}
