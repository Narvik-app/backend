<?php

namespace App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @method TimeAndTravelExport|Proxy create(array|callable $attributes = [])
 * @method static TimeAndTravelExport|Proxy createOne(array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<TimeAndTravelExport>
 */
final class TimeAndTravelExportFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return TimeAndTravelExport::class;
  }

  protected function defaults(): array {
    return [
      'club' => _InitStory::club_1(),
      'startDate' => new \DateTimeImmutable('-2 months'),
      'endDate' => new \DateTimeImmutable('now'),
      'generatedBy' => _InitStory::MEMBER_admin_club_1(),
    ];
  }
}
