<?php

namespace App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Enum\VehicleEngineType;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @method MemberVehicle|Proxy create(array|callable $attributes = [])
 * @method static MemberVehicle|Proxy createOne(array $attributes = [])
 * @method static MemberVehicle|Proxy random(array $attributes = [])
 * @method static MemberVehicle|Proxy randomOrCreate(array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<MemberVehicle>
 */
final class MemberVehicleFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return MemberVehicle::class;
  }

  protected function defaults(): array {
    return [
      'member' => _InitStory::MEMBER_member_club_1(),
      'brand' => self::faker()->randomElement(['Renault', 'Peugeot', 'Citroën', 'Toyota']),
      'model' => self::faker()->word(),
      'licensePlate' => strtoupper(self::faker()->bothify('??-###-??')),
      // Deterministic (not electric) by default — the electric +20% bonus would otherwise
      // silently perturb travel amount assertions in unrelated tests; set explicitly where needed.
      'engineType' => VehicleEngineType::petrol,
      'fiscalPower' => self::faker()->numberBetween(4, 10),
      'isEnabled' => true,
    ];
  }
}
