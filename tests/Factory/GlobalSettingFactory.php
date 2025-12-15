<?php

namespace App\Tests\Factory;

use App\Entity\GlobalSetting;
use App\Repository\GlobalSettingRepository;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method GlobalSetting|Proxy create(array|callable $attributes = [])
 * @method static GlobalSetting|Proxy createOne(array $attributes = [])
 * @method static GlobalSetting|Proxy find(object|array|mixed $criteria)
 * @method static GlobalSetting|Proxy findOrCreate(array $attributes)
 * @method static GlobalSetting|Proxy first(string $sortedField = 'id')
 * @method static GlobalSetting|Proxy last(string $sortedField = 'id')
 * @method static GlobalSetting|Proxy random(array $attributes = [])
 * @method static GlobalSetting|Proxy randomOrCreate(array $attributes = [])
 * @method static \App\Entity\GlobalSetting[]|\Zenstruck\Foundry\Persistence\Proxy[] all()
 * @method static \App\Entity\GlobalSetting[]|\Zenstruck\Foundry\Persistence\Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static \App\Entity\GlobalSetting[]|\Zenstruck\Foundry\Persistence\Proxy[] createSequence((iterable|callable) $sequence)
 * @method static \App\Entity\GlobalSetting[]|\Zenstruck\Foundry\Persistence\Proxy[] findBy(array $attributes)
 * @method static \App\Entity\GlobalSetting[]|\Zenstruck\Foundry\Persistence\Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static \App\Entity\GlobalSetting[]|\Zenstruck\Foundry\Persistence\Proxy[] randomSet(int $number, array $attributes = [])
 * @method FactoryCollection<GlobalSetting|Proxy> many(int $min, int|null $max = null)
 * @method FactoryCollection<GlobalSetting|Proxy> sequence(iterable|callable $sequence)
 * @method static ProxyRepositoryDecorator<GlobalSetting, GlobalSettingRepository> repository()
 *
 * @phpstan-method \App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting> create(array|callable $attributes = [])
 * @phpstan-method static \App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting> createOne(array $attributes = [])
 * @phpstan-method static \App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting> find(object|array|mixed $criteria)
 * @phpstan-method static \App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting> findOrCreate(array $attributes)
 * @phpstan-method static \App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting> first(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting> last(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting> random(array $attributes = [])
 * @phpstan-method static \App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<\App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting>> all()
 * @phpstan-method static list<\App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<\App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<\App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting>> findBy(array $attributes)
 * @phpstan-method static list<\App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<\App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting>> randomSet(int $number, array $attributes = [])
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting>> many(int $min, int|null $max = null)
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\GlobalSetting&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\GlobalSetting>> sequence(iterable|callable $sequence)
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<GlobalSetting>
 */
final class GlobalSettingFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
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
      'name'  => self::faker()->text(255),
      'value' => self::faker()->text(255),
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(GlobalSetting $globalSetting): void {})
      ;
  }

  public static function class(): string {
    return GlobalSetting::class;
  }
}
