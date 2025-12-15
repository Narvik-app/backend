<?php

namespace App\Tests\Factory;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Sale\Sale;
use App\Repository\ClubDependent\Plugin\Sale\SaleRepository;
use App\Service\SeasonService;
use App\Service\UtilsService;
use App\Tests\Story\_InitStory;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method        Sale|Proxy                              create(array|callable $attributes = [])
 * @method static Sale|Proxy                              createOne(array $attributes = [])
 * @method static Sale|Proxy                              find(object|array|mixed $criteria)
 * @method static Sale|Proxy                              findOrCreate(array $attributes)
 * @method static Sale|Proxy                              first(string $sortedField = 'id')
 * @method static Sale|Proxy                              last(string $sortedField = 'id')
 * @method static Sale|Proxy                              random(array $attributes = [])
 * @method static Sale|Proxy                              randomOrCreate(array $attributes = [])
 * @method static SaleRepository|ProxyRepositoryDecorator repository()
 * @method static \App\Entity\ClubDependent\Plugin\Sale\Sale[]|\Zenstruck\Foundry\Persistence\Proxy[] all()
 * @method static \App\Entity\ClubDependent\Plugin\Sale\Sale[]|\Zenstruck\Foundry\Persistence\Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static \App\Entity\ClubDependent\Plugin\Sale\Sale[]|\Zenstruck\Foundry\Persistence\Proxy[] createSequence((iterable|callable) $sequence)
 * @method static \App\Entity\ClubDependent\Plugin\Sale\Sale[]|\Zenstruck\Foundry\Persistence\Proxy[] findBy(array $attributes)
 * @method static \App\Entity\ClubDependent\Plugin\Sale\Sale[]|\Zenstruck\Foundry\Persistence\Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static \App\Entity\ClubDependent\Plugin\Sale\Sale[]|\Zenstruck\Foundry\Persistence\Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Sale&Proxy<Sale> create(array|callable $attributes = [])
 * @phpstan-method static Sale&Proxy<Sale> createOne(array $attributes = [])
 * @phpstan-method static Sale&Proxy<Sale> find(object|array|mixed $criteria)
 * @phpstan-method static Sale&Proxy<Sale> findOrCreate(array $attributes)
 * @phpstan-method static Sale&Proxy<Sale> first(string $sortedField = 'id')
 * @phpstan-method static Sale&Proxy<Sale> last(string $sortedField = 'id')
 * @phpstan-method static Sale&Proxy<Sale> random(array $attributes = [])
 * @phpstan-method static Sale&Proxy<Sale> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<Sale, EntityRepository> repository()
 * @phpstan-method static list<Sale&Proxy<Sale>> all()
 * @phpstan-method static list<Sale&Proxy<Sale>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Sale&Proxy<Sale>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Sale&Proxy<Sale>> findBy(array $attributes)
 * @phpstan-method static list<Sale&Proxy<Sale>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Sale&Proxy<Sale>> randomSet(int $number, array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<Sale>
 */
final class SaleFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
   */
  public function __construct() {
  }

  public static function class(): string {
    return Sale::class;
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
   */
  protected function defaults(): array|callable {
    $club = _InitStory::club_1();
    $seasonDates = SeasonService::calculateStartEndDate($club, new \DateTimeImmutable());

    return [
      // 'price' => self::faker()->randomFloat(2, 20, 80),
      'club' => $club,
      'seller' => _InitStory::MEMBER_supervisor_club_1(),
      'salePurchasedItems'  => SalePurchasedItemFactory::createMany(self::faker()->numberBetween(1, 6)),
      'paymentMode' => SalePaymentModeFactory::randomOrCreate(),
      'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween($seasonDates['start']->format('Y-m-d'), $seasonDates['end']->format('Y-m-d'))),
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(Sale $sale): void {})
      ;
  }
}
