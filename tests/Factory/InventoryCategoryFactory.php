<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Plugin\Sale\InventoryCategory;
use App\Repository\ClubDependent\Plugin\Sale\InventoryCategoryRepository;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method InventoryCategory|Proxy create(array|callable $attributes = [])
 * @method static InventoryCategory|Proxy createOne(array $attributes = [])
 * @method static InventoryCategory|Proxy find(object|array|mixed $criteria)
 * @method static InventoryCategory|Proxy findOrCreate(array $attributes)
 * @method static InventoryCategory|Proxy first(string $sortedField = 'id')
 * @method static InventoryCategory|Proxy last(string $sortedField = 'id')
 * @method static InventoryCategory|Proxy random(array $attributes = [])
 * @method static InventoryCategory|Proxy randomOrCreate(array $attributes = [])
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] all()
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] createSequence((iterable|callable) $sequence)
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] findBy(array $attributes)
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory[]|\Zenstruck\Foundry\Persistence\Proxy[] randomSet(int $number, array $attributes = [])
 * @method FactoryCollection<InventoryCategory|Proxy> many(int $min, int|null $max = null)
 * @method FactoryCollection<InventoryCategory|Proxy> sequence(iterable|callable $sequence)
 * @method static ProxyRepositoryDecorator<InventoryCategory, InventoryCategoryRepository> repository()
 *
 * @phpstan-method \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory> create(array|callable $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory> createOne(array $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory> find(object|array|mixed $criteria)
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory> findOrCreate(array $attributes)
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory> first(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory> last(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory> random(array $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory>> all()
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory>> findBy(array $attributes)
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory>> randomSet(int $number, array $attributes = [])
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory>> many(int $min, int|null $max = null)
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryCategory>> sequence(iterable|callable $sequence)
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<InventoryCategory>
 */
final class InventoryCategoryFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
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
      'name'   => self::faker()->text(10),
      'club'   => _InitStory::club_1(),
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(InventoryCategory $inventoryCategory): void {})
      ;
  }

  public static function class(): string {
    return InventoryCategory::class;
  }
}
