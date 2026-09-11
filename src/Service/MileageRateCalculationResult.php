<?php

namespace App\Service;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MileageRate;

final readonly class MileageRateCalculationResult {
  public function __construct(
    public MileageRate $rate,
    public int $kilometers,
    public float $electricBonusRate,
    public float $amount,
  ) {
  }
}
