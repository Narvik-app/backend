<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\MemberControl;
use App\Tests\Story\_InitStory;

final class MemberControlFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return MemberControl::class;
  }

  protected function defaults(): array {
    return [
      'member' => _InitStory::MEMBER_member_club_1(),
      'type' => MemberControlTypeFactory::new(),
      'date' => new \DateTimeImmutable('-6 months'),
    ];
  }
}
