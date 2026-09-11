<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationRepository;
use App\Service\MileageRateCalculationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;

/**
 * Hydrates a preview of the calculation that will apply to this vehicle's declarations this
 * calendar year, so a member can see it and flag it to support if a value looks wrong — see
 * MileageRateCalculationService for why this can't just be "kilometers * a fixed rate".
 */
#[AsEntityListener(entity: MemberVehicle::class)]
class MemberVehicleSubscriber extends AbstractEventSubscriber {
  public function __construct(
    private readonly TimeAndTravelDeclarationRepository $declarationRepository,
    private readonly MileageRateCalculationService $mileageRateCalculationService,
  ) {
  }

  public function postLoad(MemberVehicle $vehicle, PostLoadEventArgs $args): void {
    if (!$vehicle->getFiscalPower()) {
      return;
    }

    $kilometers = $this->declarationRepository->sumKilometersForVehicleInYear($vehicle, (int) date('Y'));
    $vehicle->setCurrentYearKilometers($kilometers);

    if ($kilometers <= 0) {
      return;
    }

    $result = $this->mileageRateCalculationService->calculate($vehicle->getCategory(), $vehicle->getFiscalPower(), $kilometers, $vehicle->isElectric());
    if (!$result) {
      $vehicle->setCurrentYearCalculationDescription('Aucun barème officiel ne correspond à cette puissance/catégorie.');
      return;
    }

    $vehicle->setCurrentYearEstimatedAmount(number_format($result->amount, 2, '.', ''));
    $vehicle->setCurrentYearCalculationDescription(sprintf(
      '%s km × %s%s%s',
      $kilometers,
      $result->rate->getRate(),
      (float) $result->rate->getAddend() > 0 ? ' + ' . $result->rate->getAddend() . ' €' : '',
      $result->electricBonusRate > 0 ? sprintf(' (+%d%% véhicule électrique)', round($result->electricBonusRate * 100)) : ''
    ));
  }
}
