<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\MemberSeason;
use App\Repository\ClubDependent\MemberSeasonRepository;
use App\Tests\Story\AgeCategoryStory;
use App\Tests\Story\SeasonStory;
use Zenstruck\Foundry\FactoryCollection;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

use function Zenstruck\Foundry\lazy;

/**
 *
 * @method MemberSeason|Proxy create(array|callable $attributes = [])
 * @method static MemberSeason|Proxy createOne(array $attributes = [])
 * @method static MemberSeason|Proxy find(object|array|mixed $criteria)
 * @method static MemberSeason|Proxy findOrCreate(array $attributes)
 * @method static MemberSeason|Proxy first(string $sortedField = 'id')
 * @method static MemberSeason|Proxy last(string $sortedField = 'id')
 * @method static MemberSeason|Proxy random(array $attributes = [])
 * @method static MemberSeason|Proxy randomOrCreate(array $attributes = [])
 * @method static \App\Entity\ClubDependent\MemberSeason[]|\Zenstruck\Foundry\Persistence\Proxy[] all()
 * @method static \App\Entity\ClubDependent\MemberSeason[]|\Zenstruck\Foundry\Persistence\Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static \App\Entity\ClubDependent\MemberSeason[]|\Zenstruck\Foundry\Persistence\Proxy[] createSequence((iterable|callable) $sequence)
 * @method static \App\Entity\ClubDependent\MemberSeason[]|\Zenstruck\Foundry\Persistence\Proxy[] findBy(array $attributes)
 * @method static \App\Entity\ClubDependent\MemberSeason[]|\Zenstruck\Foundry\Persistence\Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static \App\Entity\ClubDependent\MemberSeason[]|\Zenstruck\Foundry\Persistence\Proxy[] randomSet(int $number, array $attributes = [])
 * @method FactoryCollection<MemberSeason|Proxy> many(int $min, int|null $max = null)
 * @method FactoryCollection<MemberSeason|Proxy> sequence(iterable|callable $sequence)
 * @method static ProxyRepositoryDecorator<MemberSeason, MemberSeasonRepository> repository()
 *
 * @phpstan-method \App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason> create(array|callable $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason> createOne(array $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason> find(object|array|mixed $criteria)
 * @phpstan-method static \App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason> findOrCreate(array $attributes)
 * @phpstan-method static \App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason> first(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason> last(string $sortedField = 'id')
 * @phpstan-method static \App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason> random(array $attributes = [])
 * @phpstan-method static \App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason>> all()
 * @phpstan-method static list<\App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<\App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason>> findBy(array $attributes)
 * @phpstan-method static list<\App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<\App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason>> randomSet(int $number, array $attributes = [])
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason>> many(int $min, int|null $max = null)
 * @phpstan-method \Zenstruck\Foundry\FactoryCollection<\App\Entity\ClubDependent\MemberSeason&\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\ClubDependent\MemberSeason>> sequence(iterable|callable $sequence)
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<MemberSeason>
 */
final class MemberSeasonFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
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
      'member'          => lazy(fn() => MemberFactory::random()),
      'ageCategory'     => AgeCategoryStory::getRandom("age_categories"),
      'isSecondaryClub' => self::faker()->boolean(25),
      //      'season'      => $season->object(), // Done in self::initialize()
    ];
  }

  /**
   * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
   */
  #[\Override]
  protected function initialize(): static {
    return $this// ->afterInstantiate(function(MemberSeason $memberSeason): void {})
    ->afterInstantiate(function(MemberSeason $memberSeason): void {
      if (!$memberSeason->getSeason()) {
        $season = SeasonStory::season_current();
        $memberSeason->setSeason($season);
      }
    });
  }

  public static function class(): string {
    return MemberSeason::class;
  }
}
