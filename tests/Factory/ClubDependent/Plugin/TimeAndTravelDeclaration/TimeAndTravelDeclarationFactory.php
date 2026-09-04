<?php

namespace App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationRepository;
use App\Service\SeasonService;
use App\Tests\Factory\MemberFactory;
use DateTimeImmutable;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method TimeAndTravelDeclaration|Proxy create(array|callable $attributes = [])
 * @method static TimeAndTravelDeclaration|Proxy createOne(array $attributes = [])
 * @method static TimeAndTravelDeclaration|Proxy find(object|array|mixed $criteria)
 * @method static TimeAndTravelDeclaration|Proxy findOrCreate(array $attributes)
 * @method static TimeAndTravelDeclaration|Proxy first(string $sortedField = 'id')
 * @method static TimeAndTravelDeclaration|Proxy last(string $sortedField = 'id')
 * @method static TimeAndTravelDeclaration|Proxy random(array $attributes = [])
 * @method static TimeAndTravelDeclaration|Proxy randomOrCreate(array $attributes = [])
 * @method static TimeAndTravelDeclaration[]|Proxy[] all()
 * @method static TimeAndTravelDeclaration[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static TimeAndTravelDeclaration[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static TimeAndTravelDeclaration[]|Proxy[] findBy(array $attributes)
 * @method static TimeAndTravelDeclaration[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static TimeAndTravelDeclaration[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method FactoryCollection<TimeAndTravelDeclaration|Proxy> many(int $min, int|null $max = null)
 * @method FactoryCollection<TimeAndTravelDeclaration|Proxy> sequence(iterable|callable $sequence)
 * @method static ProxyRepositoryDecorator<TimeAndTravelDeclaration, TimeAndTravelDeclarationRepository> repository()
 * @extends PersistentProxyObjectFactory<TimeAndTravelDeclaration>
 */
final class TimeAndTravelDeclarationFactory extends PersistentProxyObjectFactory {
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
    $member = MemberFactory::random();
    $seasonDates = SeasonService::calculateStartEndDate($member->getClub(), new DateTimeImmutable());

    return [
      'date'       => DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween($seasonDates['start']->format('Y-m-d'), $seasonDates['end']->format('Y-m-d'))),
      'member'     => MemberFactory::random(),      'departureLocation' => self::faker()->city(),
      'arrivalLocation' => self::faker()->city(),
      'kilometers' => self::faker()->numberBetween(10, 500),
      'hours' => (string) self::faker()->randomFloat(2, 1.00, 8.00),
      'description' => self::faker()->sentence(3),
      'isRoundtrip' => self::faker()->boolean(70),
      'memberVehicle' => MemberVehicleFactory::random(),
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(TimeAndTravelDeclaration $timeAndTravelDeclaration): void {})
      ;
  }

  public static function class(): string {
    return TimeAndTravelDeclaration::class;
  }
}
