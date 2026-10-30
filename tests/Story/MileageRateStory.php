<?php

namespace App\Tests\Story;

use App\Enum\VehicleCategory;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\MileageRateFactory;
use Zenstruck\Foundry\Story;

/**
 * Official French kilometric mileage scale (Arrêté du 27 mars 2023) —
 * see https://www.service-public.gouv.fr/particuliers/vosdroits/F1132
 */
final class MileageRateStory extends Story {
  public function build(): void {
    // [category, minFiscalPower, maxFiscalPower, [[tierMaxKm, rate, addend], ...]]
    $table = [
      [VehicleCategory::car, null, 3, [[5000, '0.529', '0.00'], [20000, '0.316', '1065.00'], [null, '0.370', '0.00']]],
      [VehicleCategory::car, 4, 4, [[5000, '0.606', '0.00'], [20000, '0.340', '1330.00'], [null, '0.407', '0.00']]],
      [VehicleCategory::car, 5, 5, [[5000, '0.636', '0.00'], [20000, '0.357', '1395.00'], [null, '0.427', '0.00']]],
      [VehicleCategory::car, 6, 6, [[5000, '0.665', '0.00'], [20000, '0.374', '1457.00'], [null, '0.447', '0.00']]],
      [VehicleCategory::car, 7, null, [[5000, '0.697', '0.00'], [20000, '0.394', '1515.00'], [null, '0.470', '0.00']]],

      [VehicleCategory::motorcycle, 1, 2, [[3000, '0.395', '0.00'], [6000, '0.099', '891.00'], [null, '0.248', '0.00']]],
      [VehicleCategory::motorcycle, 3, 5, [[3000, '0.468', '0.00'], [6000, '0.082', '1158.00'], [null, '0.275', '0.00']]],
      [VehicleCategory::motorcycle, 6, null, [[3000, '0.606', '0.00'], [6000, '0.079', '1583.00'], [null, '0.343', '0.00']]],

      [VehicleCategory::moped, null, null, [[3000, '0.315', '0.00'], [6000, '0.079', '711.00'], [null, '0.198', '0.00']]],
    ];

    foreach ($table as [$category, $minFiscalPower, $maxFiscalPower, $tiers]) {
      foreach ($tiers as $tierOrder => [$tierMaxKm, $rate, $addend]) {
        MileageRateFactory::createOne([
          'category' => $category,
          'minFiscalPower' => $minFiscalPower,
          'maxFiscalPower' => $maxFiscalPower,
          'tierOrder' => $tierOrder + 1,
          'tierMaxKm' => $tierMaxKm,
          'rate' => $rate,
          'addend' => $addend,
        ]);
      }
    }
  }
}
