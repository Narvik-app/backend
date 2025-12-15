<?php

namespace App\Tests\Factory;

use App\Entity\AgeCategory;
use App\Repository\AgeCategoryRepository;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method AgeCategory|Proxy create(array|callable $attributes = [])
 * @method static AgeCategory|Proxy createOne(array $attributes = [])
 * @method static AgeCategory|Proxy find(object|array|mixed $criteria)
 * @method static AgeCategory|Proxy findOrCreate(array $attributes)
 * @method static AgeCategory|Proxy first(string $sortedField = 'id')
 * @method static AgeCategory|Proxy last(string $sortedField = 'id')
 * @method static AgeCategory|Proxy random(array $attributes = [])
 * @method static AgeCategory|Proxy randomOrCreate(array $attributes = [])
 * @method static \App\Entity\AgeCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] all()
 * @method static \App\Entity\AgeCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static \App\Entity\AgeCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] createSequence((iterable|callable) $sequence)
 * @method static \App\Entity\AgeCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] findBy(array $attributes)
 * @method static \App\Entity\AgeCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static \App\Entity\AgeCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] randomSet(int $number, array $attributes = [])
 * @method FactoryCollection<AgeCategory|Proxy> many(int $min, int|null $max = null)
 * @method FactoryCollection<AgeCategory|Proxy> sequence(iterable|callable $sequence)
 * @method static ProxyRepositoryDecorator<AgeCategory, AgeCategoryRepository> repository()
 *
 * @phpstan-method \App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory> create(array|callable $attributes = [])
 * @phpstan-method static \App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory> createOne(array $attributes = [])
 * @phpstan-method static \App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory> find(object|array|mixed $criteria)
 * @phpstan-method static \App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory> findOrCreate(array $attributes)
 * @phpstan-method static \App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory> first(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory> last(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory> random(array $attributes = [])
 * @phpstan-method static \App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<\App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory>> all()
 * @phpstan-method static list<\App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<\App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<\App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory>> findBy(array $attributes)
 * @phpstan-method static list<\App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<\App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory>> randomSet(int $number, array $attributes = [])
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory>> many(int $min, int|null $max = null)
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\AgeCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\AgeCategory>> sequence(iterable|callable $sequence)
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<AgeCategory>
 */
final class AgeCategoryFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
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
      'code' => 'S1',
      'name' => 'Senior 1',
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(AgeCategory $ageCategory): void {})
      ;
  }

  public static function class(): string {
    return AgeCategory::class;
  }
}
