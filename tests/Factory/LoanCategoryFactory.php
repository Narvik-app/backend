<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Plugin\Loan\LoanCategory;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @method LoanCategory|Proxy create(array|callable $attributes = [])
 * @method static LoanCategory|Proxy createOne(array $attributes = [])
 * @method static LoanCategory|Proxy random(array $attributes = [])
 * @method static LoanCategory|Proxy randomOrCreate(array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<LoanCategory>
 */
final class LoanCategoryFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return LoanCategory::class;
  }

  protected function defaults(): array {
    return [
      'club' => _InitStory::club_1(),
      'name' => ucfirst(self::faker()->words(2, true)),
      'weight' => self::faker()->numberBetween(0, 100),
    ];
  }
}
