<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Plugin\Sale\SalePurchasedItem;
use App\Repository\ClubDependent\Plugin\Sale\SalePurchasedItemRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method        SalePurchasedItem|Proxy                              create(array|callable $attributes = [])
 * @method static SalePurchasedItem|Proxy                              createOne(array $attributes = [])
 * @method static SalePurchasedItem|Proxy                              find(object|array|mixed $criteria)
 * @method static SalePurchasedItem|Proxy                              findOrCreate(array $attributes)
 * @method static SalePurchasedItem|Proxy                              first(string $sortedField = 'id')
 * @method static SalePurchasedItem|Proxy                              last(string $sortedField = 'id')
 * @method static SalePurchasedItem|Proxy                              random(array $attributes = [])
 * @method static SalePurchasedItem|Proxy                              randomOrCreate(array $attributes = [])
 * @method static SalePurchasedItemRepository|ProxyRepositoryDecorator repository()
 * @method static \App\Entity\ClubDependent\Plugin\Sale\SalePurchasedItem[]|\Zenstruck\Foundry\Persistence\Proxy[] all()
 * @method static \App\Entity\ClubDependent\Plugin\Sale\SalePurchasedItem[]|\Zenstruck\Foundry\Persistence\Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static \App\Entity\ClubDependent\Plugin\Sale\SalePurchasedItem[]|\Zenstruck\Foundry\Persistence\Proxy[] createSequence((iterable|callable) $sequence)
 * @method static \App\Entity\ClubDependent\Plugin\Sale\SalePurchasedItem[]|\Zenstruck\Foundry\Persistence\Proxy[] findBy(array $attributes)
 * @method static \App\Entity\ClubDependent\Plugin\Sale\SalePurchasedItem[]|\Zenstruck\Foundry\Persistence\Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static \App\Entity\ClubDependent\Plugin\Sale\SalePurchasedItem[]|\Zenstruck\Foundry\Persistence\Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        SalePurchasedItem&Proxy<SalePurchasedItem> create(array|callable $attributes = [])
 * @phpstan-method static SalePurchasedItem&Proxy<SalePurchasedItem> createOne(array $attributes = [])
 * @phpstan-method static SalePurchasedItem&Proxy<SalePurchasedItem> find(object|array|mixed $criteria)
 * @phpstan-method static SalePurchasedItem&Proxy<SalePurchasedItem> findOrCreate(array $attributes)
 * @phpstan-method static SalePurchasedItem&Proxy<SalePurchasedItem> first(string $sortedField = 'id')
 * @phpstan-method static SalePurchasedItem&Proxy<SalePurchasedItem> last(string $sortedField = 'id')
 * @phpstan-method static SalePurchasedItem&Proxy<SalePurchasedItem> random(array $attributes = [])
 * @phpstan-method static SalePurchasedItem&Proxy<SalePurchasedItem> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<SalePurchasedItem, EntityRepository> repository()
 * @phpstan-method static list<SalePurchasedItem&Proxy<SalePurchasedItem>> all()
 * @phpstan-method static list<SalePurchasedItem&Proxy<SalePurchasedItem>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<SalePurchasedItem&Proxy<SalePurchasedItem>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<SalePurchasedItem&Proxy<SalePurchasedItem>> findBy(array $attributes)
 * @phpstan-method static list<SalePurchasedItem&Proxy<SalePurchasedItem>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<SalePurchasedItem&Proxy<SalePurchasedItem>> randomSet(int $number, array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<SalePurchasedItem>
 */
final class SalePurchasedItemFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
   */
  public function __construct() {
  }

  public static function class(): string {
    return SalePurchasedItem::class;
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
   */
  protected function defaults(): array|callable {
    return [
      'item' => InventoryItemFactory::randomOrCreate(),
      'quantity' => self::faker()->numberBetween(1, 10),
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(SalePurchasedItem $salePurchasedItem): void {})
      ;
  }
}
