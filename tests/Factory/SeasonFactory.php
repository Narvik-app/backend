<?php

namespace App\Tests\Factory;

use App\Entity\Season;
use App\Repository\SeasonRepository;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method Season|Proxy create(array|callable $attributes = [])
 * @method static Season|Proxy createOne(array $attributes = [])
 * @method static Season|Proxy find(object|array|mixed $criteria)
 * @method static Season|Proxy findOrCreate(array $attributes)
 * @method static Season|Proxy first(string $sortedField = 'id')
 * @method static Season|Proxy last(string $sortedField = 'id')
 * @method static Season|Proxy random(array $attributes = [])
 * @method static Season|Proxy randomOrCreate(array $attributes = [])
 * @method static \App\Entity\Season[]|\Zenstruck\Foundry\Persistence\Proxy[] all()
 * @method static \App\Entity\Season[]|\Zenstruck\Foundry\Persistence\Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static \App\Entity\Season[]|\Zenstruck\Foundry\Persistence\Proxy[] createSequence((iterable|callable) $sequence)
 * @method static \App\Entity\Season[]|\Zenstruck\Foundry\Persistence\Proxy[] findBy(array $attributes)
 * @method static \App\Entity\Season[]|\Zenstruck\Foundry\Persistence\Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static \App\Entity\Season[]|\Zenstruck\Foundry\Persistence\Proxy[] randomSet(int $number, array $attributes = [])
 * @method FactoryCollection<Season|Proxy> many(int $min, int|null $max = null)
 * @method FactoryCollection<Season|Proxy> sequence(iterable|callable $sequence)
 * @method static ProxyRepositoryDecorator<Season, SeasonRepository> repository()
 *
 * @phpstan-method \App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season> create(array|callable $attributes = [])
 * @phpstan-method static \App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season> createOne(array $attributes = [])
 * @phpstan-method static \App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season> find(object|array|mixed $criteria)
 * @phpstan-method static \App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season> findOrCreate(array $attributes)
 * @phpstan-method static \App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season> first(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season> last(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season> random(array $attributes = [])
 * @phpstan-method static \App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<\App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season>> all()
 * @phpstan-method static list<\App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<\App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<\App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season>> findBy(array $attributes)
 * @phpstan-method static list<\App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<\App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season>> randomSet(int $number, array $attributes = [])
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season>> many(int $min, int|null $max = null)
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\Season&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\Season>> sequence(iterable|callable $sequence)
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<Season>
 */
final class SeasonFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
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
      'name' => '2023/2024',
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(Season $season): void {})
      ;
  }

  public static function class(): string {
    return Season::class;
  }
}
