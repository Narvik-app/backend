<?php

namespace App\Service\Plugin\TimeAndTravelDeclaration;

use App\Enum\GlobalSetting as GlobalSettingEnum;
use App\Enum\VehicleCategory;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\MileageRateRepository;
use App\Repository\GlobalSettingRepository;

/**
 * Computes the official kilometric indemnity for a vehicle category/fiscal power over a
 * cumulative distance, applying the electric vehicle bonus (super-admin configurable, see
 * GlobalSetting::TIME_AND_TRAVEL_ELECTRIC_BONUS_RATE — defaults to the legal +20%).
 */
class MileageRateCalculationService {
  public const string DEFAULT_ELECTRIC_BONUS_RATE = '0.20';

  private ?float $electricBonusRate = null;

  public function __construct(
    private readonly MileageRateRepository $rateRepository,
    private readonly GlobalSettingRepository $globalSettingRepository,
  ) {
  }

  public function getElectricBonusRate(): float {
    if ($this->electricBonusRate === null) {
      $setting = $this->globalSettingRepository->findOneByName(GlobalSettingEnum::TIME_AND_TRAVEL_ELECTRIC_BONUS_RATE->name);
      $this->electricBonusRate = (float) ($setting?->getValue() ?? self::DEFAULT_ELECTRIC_BONUS_RATE);
    }

    return $this->electricBonusRate;
  }

  /**
   * @param int $kilometers The volunteer's CUMULATIVE distance for the vehicle over the period —
   *                        the whole distance is priced by whichever single bracket it falls into.
   */
  public function calculate(VehicleCategory $category, int $fiscalPower, int $kilometers, bool $isElectric): ?MileageRateCalculationResult {
    if ($kilometers <= 0) {
      return null;
    }

    $rate = $this->rateRepository->findApplicableRate($category, $fiscalPower, $kilometers);
    if (!$rate) {
      return null;
    }

    $amount = $rate->calculateAmount($kilometers);
    $electricBonusRate = 0.0;
    if ($isElectric) {
      $electricBonusRate = $this->getElectricBonusRate();
      $amount *= 1 + $electricBonusRate;
    }

    return new MileageRateCalculationResult($rate, $kilometers, $electricBonusRate, $amount);
  }
}
