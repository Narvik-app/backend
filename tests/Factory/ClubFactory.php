<?php

namespace App\Tests\Factory;

use App\Entity\Club;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method        Club|Proxy                              create(array|callable $attributes = [])
 * @method static Club|Proxy                              createOne(array $attributes = [])
 * @method static Club|Proxy                              find(object|array|mixed $criteria)
 * @method static Club|Proxy                              findOrCreate(array $attributes)
 * @method static Club|Proxy                              first(string $sortedField = 'id')
 * @method static Club|Proxy                              last(string $sortedField = 'id')
 * @method static Club|Proxy                              random(array $attributes = [])
 * @method static Club|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ClubRepository|ProxyRepositoryDecorator repository()
 * @method static \App\Entity\Club[]|\Zenstruck\Foundry\Persistence\Proxy[] all()
 * @method static \App\Entity\Club[]|\Zenstruck\Foundry\Persistence\Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static \App\Entity\Club[]|\Zenstruck\Foundry\Persistence\Proxy[] createSequence((iterable|callable) $sequence)
 * @method static \App\Entity\Club[]|\Zenstruck\Foundry\Persistence\Proxy[] findBy(array $attributes)
 * @method static \App\Entity\Club[]|\Zenstruck\Foundry\Persistence\Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static \App\Entity\Club[]|\Zenstruck\Foundry\Persistence\Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Club&Proxy<Club> create(array|callable $attributes = [])
 * @phpstan-method static Club&Proxy<Club> createOne(array $attributes = [])
 * @phpstan-method static Club&Proxy<Club> find(object|array|mixed $criteria)
 * @phpstan-method static Club&Proxy<Club> findOrCreate(array $attributes)
 * @phpstan-method static Club&Proxy<Club> first(string $sortedField = 'id')
 * @phpstan-method static Club&Proxy<Club> last(string $sortedField = 'id')
 * @phpstan-method static Club&Proxy<Club> random(array $attributes = [])
 * @phpstan-method static Club&Proxy<Club> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<Club, EntityRepository> repository()
 * @phpstan-method static list<Club&Proxy<Club>> all()
 * @phpstan-method static list<Club&Proxy<Club>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Club&Proxy<Club>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Club&Proxy<Club>> findBy(array $attributes)
 * @phpstan-method static list<Club&Proxy<Club>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Club&Proxy<Club>> randomSet(int $number, array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<Club>
 */
final class ClubFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  /**
   * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
   *
   */
  public function __construct() {
  }

  public static function class(): string {
    return Club::class;
  }

  /**
   * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
   */
  protected function defaults(): array|callable {
    return [
      'name'             => self::faker()->company(),
      'presencesEnabled' => self::faker()->boolean(),
      'salesEnabled'     => self::faker()->boolean(),
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this->afterInstantiate(function(Club $club): void {
      $club->getSettings()?->setSeasonEnd("12-31"); // For the tests, we force the end of the year
    })
      ;
  }
}
