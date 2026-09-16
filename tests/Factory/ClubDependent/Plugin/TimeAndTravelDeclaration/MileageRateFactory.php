<?php

namespace App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MileageRate;
use App\Enum\VehicleCategory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @method static MileageRate|Proxy createOne(array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<MileageRate>
 */
final class MileageRateFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return MileageRate::class;
  }

  protected function defaults(): array {
    return [
      'category' => VehicleCategory::car,
      'minFiscalPower' => 3,
      'maxFiscalPower' => 3,
      'tierOrder' => 1,
      'tierMaxKm' => 5000,
      'rate' => '0.529',
      'addend' => '0.00',
    ];
  }
}
