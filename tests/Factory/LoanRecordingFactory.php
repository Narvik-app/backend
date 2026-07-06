<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Plugin\Loan\LoanRecording;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @method LoanRecording|Proxy create(array|callable $attributes = [])
 * @method static LoanRecording|Proxy createOne(array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<LoanRecording>
 */
final class LoanRecordingFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return LoanRecording::class;
  }

  protected function defaults(): array {
    return [
      'club' => _InitStory::club_1(),
      'loanItem' => LoanItemFactory::randomOrCreate(),
      'recordingType' => LoanRecordingTypeFactory::randomOrCreate(),
      'author' => _InitStory::MEMBER_supervisor_club_1(),
      'description' => self::faker()->sentence(10),
      'date' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 year')),
    ];
  }
}
