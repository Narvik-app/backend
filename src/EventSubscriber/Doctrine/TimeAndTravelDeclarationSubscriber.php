<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;

/**
 * Hydrates timeAmount/totalAmount, which depend on the club's SMIC hourly rate
 * (a value that lives on ClubSetting, not on the declaration itself).
 * The per-club rate is cached for the lifetime of the request to avoid one
 * lazy-loaded ClubSetting query per row in a collection.
 */
#[AsEntityListener(entity: TimeAndTravelDeclaration::class)]
class TimeAndTravelDeclarationSubscriber extends AbstractEventSubscriber {
  /** @var array<int, float|null> */
  private array $smicRateByClubId = [];

  public function postLoad(TimeAndTravelDeclaration $declaration, PostLoadEventArgs $args): void {
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
