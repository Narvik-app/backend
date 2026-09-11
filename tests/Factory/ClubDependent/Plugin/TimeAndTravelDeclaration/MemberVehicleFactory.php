<?php

namespace App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\VehicleEngineType;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleRepository;
use App\Tests\Factory\MemberFactory;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method MemberVehicle|Proxy create(array|callable $attributes = [])
 * @method static MemberVehicle|Proxy createOne(array $attributes = [])
 * @method static MemberVehicle|Proxy find(object|array|mixed $criteria)
 * @method static MemberVehicle|Proxy findOrCreate(array $attributes)
 * @method static MemberVehicle|Proxy first(string $sortedField = 'id')
 * @method static MemberVehicle|Proxy last(string $sortedField = 'id')
 * @method static MemberVehicle|Proxy random(array $attributes = [])
 * @method static MemberVehicle|Proxy randomOrCreate(array $attributes = [])
 * @method static MemberVehicle[]|Proxy[] all()
 * @method static MemberVehicle[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static MemberVehicle[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static MemberVehicle[]|Proxy[] findBy(array $attributes)
 * @method static MemberVehicle[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static MemberVehicle[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method FactoryCollection<MemberVehicle|Proxy> many(int $min, int|null $max = null)
 * @method FactoryCollection<MemberVehicle|Proxy> sequence(iterable|callable $sequence)
 * @method static ProxyRepositoryDecorator<MemberVehicle, MemberVehicleRepository> repository()
 *
 * @extends PersistentProxyObjectFactory<MemberVehicle>
 */
final class MemberVehicleFactory extends PersistentProxyObjectFactory {
  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
   */
  protected function defaults(): array {
    return [
      'member' => MemberFactory::random(),
      'brand' => self::faker()->randomElement(['Renault', 'Peugeot', 'Citroën', 'Volkswagen', 'Ford', 'Toyota', 'BMW', 'Mercedes', 'Audi', 'Nissan']),
      'model' => self::faker()->boolean(70) ? self::faker()->word() : null,
      'licensePlate' => strtoupper(self::faker()->bothify('??-###-??')),
      'engineType' => self::faker()->randomElement(VehicleEngineType::cases()),
      'fiscalPower' => self::faker()->numberBetween(4, 20),
      'fiscalCoefficient' => (string) self::faker()->randomFloat(4, 0.1000, 1.5000),
      'isEnabled' => self::faker()->boolean(90),
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(MemberVehicle $memberVehicle): void {})
      ;
  }

  public static function class(): string {
    return MemberVehicle::class;
  }
}
