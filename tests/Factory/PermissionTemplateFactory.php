<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\PermissionTemplate;
use App\Repository\ClubDependent\PermissionTemplateRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @method        PermissionTemplate|Proxy                         create(array|callable $attributes = [])
 * @method static PermissionTemplate|Proxy                         createOne(array $attributes = [])
 * @method static PermissionTemplate|Proxy                         find(object|array|mixed $criteria)
 * @method static PermissionTemplate|Proxy                         findOrCreate(array $attributes)
 * @method static PermissionTemplate|Proxy                         first(string $sortedField = 'id')
 * @method static PermissionTemplate|Proxy                         last(string $sortedField = 'id')
 * @method static PermissionTemplate|Proxy                         random(array $attributes = [])
 * @method static PermissionTemplate|Proxy                         randomOrCreate(array $attributes = [])
 * @method static PermissionTemplateRepository|ProxyRepositoryDecorator repository()
 * @method static PermissionTemplate[]|Proxy[]                     all()
 * @method static PermissionTemplate[]|Proxy[]                     createMany(int $number, array|callable $attributes = [])
 * @method static PermissionTemplate[]|Proxy[]                     createSequence((iterable|callable) $sequence)
 * @method static PermissionTemplate[]|Proxy[]                     findBy(array $attributes)
 * @method static PermissionTemplate[]|Proxy[]                     randomRange(int $min, int $max, array $attributes = [])
 * @method static PermissionTemplate[]|Proxy[]                     randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        PermissionTemplate&Proxy<PermissionTemplate> create(array|callable $attributes = [])
 * @phpstan-method static PermissionTemplate&Proxy<PermissionTemplate> createOne(array $attributes = [])
 * @phpstan-method static PermissionTemplate&Proxy<PermissionTemplate> find(object|array|mixed $criteria)
 * @phpstan-method static PermissionTemplate&Proxy<PermissionTemplate> findOrCreate(array $attributes)
 * @phpstan-method static PermissionTemplate&Proxy<PermissionTemplate> first(string $sortedField = 'id')
 * @phpstan-method static PermissionTemplate&Proxy<PermissionTemplate> last(string $sortedField = 'id')
 * @phpstan-method static PermissionTemplate&Proxy<PermissionTemplate> random(array $attributes = [])
 * @phpstan-method static PermissionTemplate&Proxy<PermissionTemplate> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<PermissionTemplate, EntityRepository> repository()
 * @phpstan-method static list<PermissionTemplate&Proxy<PermissionTemplate>> all()
 * @phpstan-method static list<PermissionTemplate&Proxy<PermissionTemplate>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<PermissionTemplate&Proxy<PermissionTemplate>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<PermissionTemplate&Proxy<PermissionTemplate>> findBy(array $attributes)
 * @phpstan-method static list<PermissionTemplate&Proxy<PermissionTemplate>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<PermissionTemplate&Proxy<PermissionTemplate>> randomSet(int $number, array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<PermissionTemplate>
 */
final class PermissionTemplateFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public function __construct() {
    parent::__construct();
  }

  public static function class(): string {
    return PermissionTemplate::class;
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
   */
  protected function defaults(): array {
    return [
      'name' => self::faker()->words(2, true),
    ];
  }

  #[\Override]
  protected function initialize(): static {
    return $this;
  }
}
