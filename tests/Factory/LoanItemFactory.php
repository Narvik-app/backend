<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Enum\LoanItemStatus;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @method LoanItem|Proxy create(array|callable $attributes = [])
 * @method static LoanItem|Proxy createOne(array $attributes = [])
 * @method static LoanItem|Proxy random(array $attributes = [])
 * @method static LoanItem|Proxy randomOrCreate(array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<LoanItem>
 */
final class LoanItemFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return LoanItem::class;
  }

  protected function defaults(): array {
    return [
      'club' => _InitStory::club_1(),
      'name' => ucfirst(self::faker()->words(2, true)),
      'description' => self::faker()->boolean(50) ? self::faker()->sentence(8) : null,
      'loanPrice' => self::faker()->randomFloat(2, 2, 25),
      'purchasePrice' => self::faker()->randomFloat(2, 20, 300),
      'category' => LoanCategoryFactory::randomOrCreate(),
      'status' => LoanItemStatus::available,
      'visibleOnSalePage' => self::faker()->boolean(85),
    ];
  }
}
