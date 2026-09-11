<?php

namespace App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @method TimeAndTravelDeclaration|Proxy create(array|callable $attributes = [])
 * @method static TimeAndTravelDeclaration|Proxy createOne(array $attributes = [])
 * @method static TimeAndTravelDeclaration|Proxy random(array $attributes = [])
 * @method static TimeAndTravelDeclaration|Proxy randomOrCreate(array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<TimeAndTravelDeclaration>
 */
final class TimeAndTravelDeclarationFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return TimeAndTravelDeclaration::class;
  }

  protected function defaults(): array {
    return [
      'member' => _InitStory::MEMBER_member_club_1(),
      'date' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-2 months')),
      'departureLocation' => mb_substr(self::faker()->city(), 0, TimeAndTravelDeclaration::LOCATION_MAX_LENGTH),
      'arrivalLocation' => mb_substr(self::faker()->city(), 0, TimeAndTravelDeclaration::LOCATION_MAX_LENGTH),
      'kilometers' => self::faker()->numberBetween(5, 150),
      'hours' => self::faker()->randomFloat(2, 1, 8),
      'description' => mb_substr(self::faker()->sentence(4), 0, TimeAndTravelDeclaration::DESCRIPTION_MAX_LENGTH),
      'isRoundtrip' => self::faker()->boolean(70),
      'memberVehicle' => MemberVehicleFactory::randomOrCreate(),
    ];
  }
}
