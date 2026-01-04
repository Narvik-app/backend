<?php

namespace App\Tests\Factory;

use App\Entity\MemberPermission;
use App\Enum\Permission;
use App\Repository\MemberPermissionRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @method        MemberPermission|Proxy                         create(array|callable $attributes = [])
 * @method static MemberPermission|Proxy                         createOne(array $attributes = [])
 * @method static MemberPermission|Proxy                         find(object|array|mixed $criteria)
 * @method static MemberPermission|Proxy                         findOrCreate(array $attributes)
 * @method static MemberPermission|Proxy                         first(string $sortedField = 'id')
 * @method static MemberPermission|Proxy                         last(string $sortedField = 'id')
 * @method static MemberPermission|Proxy                         random(array $attributes = [])
 * @method static MemberPermission|Proxy                         randomOrCreate(array $attributes = [])
 * @method static MemberPermissionRepository|ProxyRepositoryDecorator repository()
 * @method static MemberPermission[]|Proxy[]                     all()
 * @method static MemberPermission[]|Proxy[]                     createMany(int $number, array|callable $attributes = [])
 * @method static MemberPermission[]|Proxy[]                     createSequence((iterable|callable) $sequence)
 * @method static MemberPermission[]|Proxy[]                     findBy(array $attributes)
 * @method static MemberPermission[]|Proxy[]                     randomRange(int $min, int $max, array $attributes = [])
 * @method static MemberPermission[]|Proxy[]                     randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        MemberPermission&Proxy<MemberPermission> create(array|callable $attributes = [])
 * @phpstan-method static MemberPermission&Proxy<MemberPermission> createOne(array $attributes = [])
 * @phpstan-method static MemberPermission&Proxy<MemberPermission> find(object|array|mixed $criteria)
 * @phpstan-method static MemberPermission&Proxy<MemberPermission> findOrCreate(array $attributes)
 * @phpstan-method static MemberPermission&Proxy<MemberPermission> first(string $sortedField = 'id')
 * @phpstan-method static MemberPermission&Proxy<MemberPermission> last(string $sortedField = 'id')
 * @phpstan-method static MemberPermission&Proxy<MemberPermission> random(array $attributes = [])
 * @phpstan-method static MemberPermission&Proxy<MemberPermission> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<MemberPermission, EntityRepository> repository()
 * @phpstan-method static list<MemberPermission&Proxy<MemberPermission>> all()
 * @phpstan-method static list<MemberPermission&Proxy<MemberPermission>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<MemberPermission&Proxy<MemberPermission>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<MemberPermission&Proxy<MemberPermission>> findBy(array $attributes)
 * @phpstan-method static list<MemberPermission&Proxy<MemberPermission>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<MemberPermission&Proxy<MemberPermission>> randomSet(int $number, array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<MemberPermission>
 */
final class MemberPermissionFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public function __construct() {
    parent::__construct();
  }

  public static function class(): string {
    return MemberPermission::class;
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
   */
  protected function defaults(): array {
    return [
      'permission' => self::faker()->randomElement(Permission::cases()),
    ];
  }

  #[\Override]
  protected function initialize(): static {
    return $this;
  }
}
