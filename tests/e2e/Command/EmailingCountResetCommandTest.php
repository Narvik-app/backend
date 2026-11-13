<?php

namespace App\Tests\e2e\Command;

use App\Entity\Club;
use App\Enum\GlobalSetting;
use App\Service\GlobalSettingService;
use App\Tests\AbstractTestCase;
use App\Tests\Factory\ClubFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class EmailingCountResetCommandTest extends AbstractTestCase {
  #[\Override]
  public function initDefaultFixtures(): void {
  }

  private function runReset(): void {
    $application = new Application(self::$kernel);
    new CommandTester($application->find('emailing:count:reset'))->execute([]);
  }

  private function refreshed(Club $club): Club {
    self::getContainer()->get('doctrine')->getManager()->refresh($club);
    return $club;
  }

  private function currentMonth(): string {
    return new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'))->format('Y-m');
  }

  public function testResetsTheCountAndRemembersTheMonth(): void {
    $club = ClubFactory::createOne(['currentMonthEmailsSent' => 42]);

    $this->runReset();

    $this->assertSame(0, $this->refreshed($club)->getCurrentMonthEmailsSent());
    $this->assertSame(
      $this->currentMonth(),
      self::getContainer()->get(GlobalSettingService::class)->getSettingValue(GlobalSetting::EMAILING_COUNT_LAST_RESET),
    );
  }

  public function testDoesNotResetTwiceInTheSameMonth(): void {
    $this->runReset();
    $club = ClubFactory::createOne(['currentMonthEmailsSent' => 42]);

    $this->runReset();

    $this->assertSame(42, $this->refreshed($club)->getCurrentMonthEmailsSent());
  }

  public function testCatchesUpAMissedMonth(): void {
    $globalSettingService = self::getContainer()->get(GlobalSettingService::class);
    $globalSettingService->updateSettingValue(GlobalSetting::EMAILING_COUNT_LAST_RESET, '2000-01');
    self::getContainer()->get('doctrine')->getManager()->flush();
    $club = ClubFactory::createOne(['currentMonthEmailsSent' => 42]);

    $this->runReset();

    $this->assertSame(0, $this->refreshed($club)->getCurrentMonthEmailsSent());
  }
}
