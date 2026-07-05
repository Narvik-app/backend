<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @method Loan|Proxy create(array|callable $attributes = [])
 * @method static Loan|Proxy createOne(array $attributes = [])
 * @method static Loan|Proxy random(array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<Loan>
 */
final class LoanFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return Loan::class;
  }

  protected function defaults(): array {
    return [
      'club' => _InitStory::club_1(),
      'loanItem' => LoanItemFactory::randomOrCreate(),
      'member' => self::faker()->boolean(75) ? MemberFactory::random() : null,
      'author' => _InitStory::MEMBER_supervisor_club_1(),
      'startDate' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 year')),
      'endDate' => null,
      'comment' => self::faker()->boolean(15) ? self::faker()->sentence(6) : null,
    ];
  }
}
