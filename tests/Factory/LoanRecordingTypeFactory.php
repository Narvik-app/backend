<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Plugin\Loan\LoanRecordingType;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @method LoanRecordingType|Proxy create(array|callable $attributes = [])
 * @method static LoanRecordingType|Proxy createOne(array $attributes = [])
 * @method static LoanRecordingType|Proxy random(array $attributes = [])
 * @method static LoanRecordingType|Proxy randomOrCreate(array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<LoanRecordingType>
 */
final class LoanRecordingTypeFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return LoanRecordingType::class;
  }

  protected function defaults(): array {
    return [
      'club' => _InitStory::club_1(),
      'name' => ucfirst(self::faker()->word()),
      'color' => self::faker()->safeHexColor(),
    ];
  }
}
