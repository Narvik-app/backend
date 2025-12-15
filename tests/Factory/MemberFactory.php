<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Member;
use App\Repository\ClubDependent\MemberRepository;
use App\Tests\Story\SeasonStory;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 *
 * @method Member|Proxy create(array|callable $attributes = [])
 * @method static Member|Proxy createOne(array $attributes = [])
 * @method static Member|Proxy find(object|array|mixed $criteria)
 * @method static Member|Proxy findOrCreate(array $attributes)
 * @method static Member|Proxy first(string $sortedField = 'id')
 * @method static Member|Proxy last(string $sortedField = 'id')
 * @method static Member|Proxy random(array $attributes = [])
 * @method static Member|Proxy randomOrCreate(array $attributes = [])
 * @method static \App\Entity\ClubDependent\Member[]|\Zenstruck\Foundry\Persistence\Proxy[] all()
 * @method static \App\Entity\ClubDependent\Member[]|\Zenstruck\Foundry\Persistence\Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static \App\Entity\ClubDependent\Member[]|\Zenstruck\Foundry\Persistence\Proxy[] createSequence((iterable|callable) $sequence)
 * @method static \App\Entity\ClubDependent\Member[]|\Zenstruck\Foundry\Persistence\Proxy[] findBy(array $attributes)
 * @method static \App\Entity\ClubDependent\Member[]|\Zenstruck\Foundry\Persistence\Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static \App\Entity\ClubDependent\Member[]|\Zenstruck\Foundry\Persistence\Proxy[] randomSet(int $number, array $attributes = [])
 * @method FactoryCollection<Member|Proxy> many(int $min, int|null $max = null)
 * @method FactoryCollection<Member|Proxy> sequence(iterable|callable $sequence)
 * @method static ProxyRepositoryDecorator<Member, MemberRepository> repository()
 *
 * @phpstan-method \App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member> create(array|callable $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member> createOne(array $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member> find(object|array|mixed $criteria)
 * @phpstan-method static \App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member> findOrCreate(array $attributes)
 * @phpstan-method static \App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member> first(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member> last(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member> random(array $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member>> all()
 * @phpstan-method static list<\App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<\App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member>> findBy(array $attributes)
 * @phpstan-method static list<\App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member>> randomSet(int $number, array $attributes = [])
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member>> many(int $min, int|null $max = null)
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\ClubDependent\Member&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\Member>> sequence(iterable|callable $sequence)
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<Member>
 */
final class MemberFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
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
      'skipAutoSetUserMember' => true,
      'club'                  => ClubFactory::first(),
      'firstname'             => self::faker()->firstName(),
      'lastname'              => self::faker()->lastName(),
      'email'                 => self::faker()->unique()->safeEmail(),
      'licence'               => str_pad(self::faker()->numberBetween(1000000, 99999999), 8, "0", STR_PAD_LEFT),
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this->afterInstantiate(function(Member $member): void {
      // For all member we link them with the current season
      MemberSeasonFactory::new([
        'season' => SeasonStory::season_current(),
        'member' => $member
      ])->create();
    });
  }

  public static function class(): string {
    return Member::class;
  }
}
