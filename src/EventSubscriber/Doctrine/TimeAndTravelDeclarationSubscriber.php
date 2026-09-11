<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Service\MileageRateCalculationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;

/**
 * Hydrates travelAmount/timeAmount/totalAmount, none of which are persisted columns.
 * travelAmount is a per-declaration ESTIMATE from the official mileage scale (see
 * TimeAndTravelDeclaration::getTravelAmount() for why it can't be the authoritative figure).
 * timeAmount/totalAmount depend on the club's SMIC hourly rate (lives on ClubSetting, not on the
 * declaration) — cached for the lifetime of the request to avoid one query per row in a collection.
 */
#[AsEntityListener(entity: TimeAndTravelDeclaration::class)]
class TimeAndTravelDeclarationSubscriber extends AbstractEventSubscriber {
  /** @var array<int, float|null> */
  private array $smicRateByClubId = [];

  public function __construct(
    private readonly MileageRateCalculationService $mileageRateCalculationService,
  ) {
  }

  public function postLoad(TimeAndTravelDeclaration $declaration, PostLoadEventArgs $args): void {
    $vehicle = $declaration->getMemberVehicle();
    $kilometers = $declaration->getKilometers();
    if ($vehicle && $kilometers) {
      $result = $this->mileageRateCalculationService->calculate($vehicle->getCategory(), $vehicle->getFiscalPower() ?? 0, $kilometers, $vehicle->isElectric());
      $declaration->setTravelAmount($result?->amount ?? 0.0);
    }

    $club = $declaration->getClub();
    if (!$club) {
      return;
    }

    $clubId = $club->getId();
    if (!array_key_exists((string) $clubId, $this->smicRateByClubId)) {
      $rate = $club->getSettings()?->getSmicHourlyRate();
      $this->smicRateByClubId[$clubId] = $rate !== null ? (float) $rate : null;
    }

    $smicRate = $this->smicRateByClubId[$clubId];
    if ($smicRate === null) {
      return;
    }

    $timeAmount = (float) ($declaration->getHours() ?? 0) * $smicRate;
    $declaration->setTimeAmount($timeAmount);
    $declaration->setTotalAmount($declaration->getTravelAmount() + $timeAmount);
  }
}
