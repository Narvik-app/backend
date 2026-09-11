<?php

namespace App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MileageRate;
use App\Enum\VehicleCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MileageRate>
 */
class MileageRateRepository extends ServiceEntityRepository {
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, MileageRate::class);
  }

  /**
   * The row that applies to this category/fiscal power for a given cumulative distance — the
   * first tier (in tierOrder) whose maxKm is at or above that distance, or the last (unbounded)
   * tier of the group if the distance exceeds every bounded tier.
   */
  public function findApplicableRate(VehicleCategory $category, int $fiscalPower, int $kilometers): ?MileageRate {
    $rates = $this->createQueryBuilder('r')
      ->where('r.category = :category')
      ->andWhere('(r.minFiscalPower IS NULL OR r.minFiscalPower <= :fiscalPower)')
      ->andWhere('(r.maxFiscalPower IS NULL OR r.maxFiscalPower >= :fiscalPower)')
      ->setParameter('category', $category)
      ->setParameter('fiscalPower', $fiscalPower)
      ->orderBy('r.tierOrder', 'ASC')
      ->getQuery()
      ->getResult();

    foreach ($rates as $rate) {
      if ($rate->getTierMaxKm() === null || $kilometers <= $rate->getTierMaxKm()) {
        return $rate;
      }
    }

    return null;
  }
}
