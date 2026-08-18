<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\MemberControlType;
use App\Tests\Story\_InitStory;

final class MemberControlTypeFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return MemberControlType::class;
  }

  protected function defaults(): array {
    return [
      'club' => _InitStory::club_1(),
      'name' => ucfirst(self::faker()->words(2, true)),
      'warningDays' => 335,
      'alertDays' => 365,
    ];
  }
}
