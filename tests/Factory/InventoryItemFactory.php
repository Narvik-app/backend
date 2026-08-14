<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Plugin\Sale\InventoryItem;
use App\Repository\ClubDependent\Plugin\Sale\InventoryItemRepository;
use App\Tests\Story\_InitStory;
use App\Tests\Story\InventoryCategoryStory;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method InventoryItem|Proxy create(array|callable $attributes = [])
 * @method static InventoryItem|Proxy createOne(array $attributes = [])
 * @method static InventoryItem|Proxy find(object|array|mixed $criteria)
 * @method static InventoryItem|Proxy findOrCreate(array $attributes)
 * @method static InventoryItem|Proxy first(string $sortedField = 'id')
 * @method static InventoryItem|Proxy last(string $sortedField = 'id')
 * @method static InventoryItem|Proxy random(array $attributes = [])
 * @method static InventoryItem|Proxy randomOrCreate(array $attributes = [])
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem[]|\Zenstruck\Foundry\Persistence\Proxy[] all()
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem[]|\Zenstruck\Foundry\Persistence\Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem[]|\Zenstruck\Foundry\Persistence\Proxy[] createSequence((iterable|callable) $sequence)
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem[]|\Zenstruck\Foundry\Persistence\Proxy[] findBy(array $attributes)
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem[]|\Zenstruck\Foundry\Persistence\Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem[]|\Zenstruck\Foundry\Persistence\Proxy[] randomSet(int $number, array $attributes = [])
 * @method FactoryCollection<InventoryItem|Proxy> many(int $min, int|null $max = null)
 * @method FactoryCollection<InventoryItem|Proxy> sequence(iterable|callable $sequence)
 * @method static ProxyRepositoryDecorator<InventoryItem, InventoryItemRepository> repository()
 *
 * @phpstan-method \App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem> create(array|callable $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem> createOne(array $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem> find(object|array|mixed $criteria)
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem> findOrCreate(array $attributes)
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem> first(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem> last(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem> random(array $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem>> all()
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem>> findBy(array $attributes)
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem>> randomSet(int $number, array $attributes = [])
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem>> many(int $min, int|null $max = null)
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Plugin\Sale\InventoryItem>> sequence(iterable|callable $sequence)
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<InventoryItem>
 */
final class InventoryItemFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-service
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
   */
  protected function defaults(): array {
    return [
      'club'            => _InitStory::club_1(),
      'name'            => self::faker()->unique()->text(10),
      'description'     => self::faker()->boolean(40) ? self::faker()->words(self::faker()->numberBetween(4, 8), true) : null,
      'canBeSold'       => self::faker()->boolean(75),
      'category'        => InventoryCategoryStory::getRandom('default'),
      'purchasePrice'   => self::faker()->randomFloat(2, 1, 10),
      'sellingPrice'    => self::faker()->randomFloat(2, 10, 20),
      'sellingQuantity' => self::faker()->boolean(75) ? self::faker()->numberBetween(1, 5) : 1,
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(InventoryItem $inventoryItem): void {})
      ;
  }

  public static function class(): string {
    return InventoryItem::class;
  }
}
