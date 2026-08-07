<?php

namespace App\Tests\e2e\Command;

use App\Tests\AbstractTestCase;
use App\Tests\Story\_InitStory;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class InstallOAuthCommandTest extends AbstractTestCase {
  #[\Override]
  public function initDefaultFixtures(): void {
    _InitStory::load();
  }

  private function runInstallOAuth(): void {
    $application = new Application(self::$kernel);
    $command = $application->find('install:oauth');
    new CommandTester($command)->execute([]);
  }

  public function testReconcilesAnAlreadyProvisionedBadgerClient(): void {
    $clientManager = self::getContainer()->get(ClientManagerInterface::class);

    // _InitStory creates the badger client the pre-fix way: confidential, no grant/scope restriction.
    $before = $clientManager->find('badger');
    $this->assertTrue($before->isConfidential(), 'precondition: fixture badger client starts confidential');
    $this->assertSame([], $before->getGrants(), 'precondition: fixture badger client starts with unrestricted grants');

    $this->runInstallOAuth();

    $after = $clientManager->find('badger');
    $this->assertFalse($after->isConfidential(), 'badger client should be demoted to public');
    $this->assertSame('', $after->getSecret());
    $this->assertSame(['refresh_token'], array_map(strval(...), $after->getGrants()));
    $this->assertSame(['badger'], array_map(strval(...), $after->getScopes()));
  }

  public function testRunningItTwiceIsANoop(): void {
    $this->runInstallOAuth();

    $clientManager = self::getContainer()->get(ClientManagerInterface::class);
    $reconciled = $clientManager->find('badger');

    $this->runInstallOAuth();

    $stillReconciled = $clientManager->find('badger');
    $this->assertFalse($stillReconciled->isConfidential());
    $this->assertSame(
      array_map(strval(...), $reconciled->getGrants()),
      array_map(strval(...), $stillReconciled->getGrants()),
    );
  }
}
