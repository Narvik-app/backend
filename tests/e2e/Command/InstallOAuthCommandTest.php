<?php

namespace App\Tests\e2e\Command;

use App\Security\Grant\PasskeyGrant;
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

  public function testCreatesTheFrontClientRestrictedToItsActualGrants(): void {
    $clientManager = self::getContainer()->get(ClientManagerInterface::class);
    $this->assertNull($clientManager->find('front'), 'precondition: no front client yet');

    $this->runInstallOAuth();

    $front = $clientManager->find('front');
    $this->assertNotNull($front);
    $this->assertTrue($front->isConfidential(), 'the front client authenticates unauthenticated calls, it must keep a secret');
    $this->assertSame(['password', 'refresh_token', PasskeyGrant::IDENTIFIER], array_map(strval(...), $front->getGrants()));
    $this->assertSame(['all'], array_map(strval(...), $front->getScopes()));
  }

  public function testDoesNotTouchAnAlreadyProvisionedFrontClient(): void {
    $clientManager = self::getContainer()->get(ClientManagerInterface::class);

    $application = new Application(self::$kernel);
    new CommandTester($application->find('league:oauth2-server:create-client'))->execute([
      'name' => 'Narvik front',
      'identifier' => 'front',
      'secret' => 'a-custom-secret-set-by-the-operator',
    ]);

    $this->runInstallOAuth();

    $front = $clientManager->find('front');
    $this->assertTrue($front->isConfidential());
    // The secret is hashed and can't be re-displayed, so it is never touched.
    // An empty grant list already allows every grant (including the passkey one), so it is kept unrestricted.
    $this->assertSame([], $front->getGrants(), 'grants should be left as originally created, unrestricted');
  }

  public function testAddsTheNewGrantsToAnAlreadyProvisionedFrontClient(): void {
    $clientManager = self::getContainer()->get(ClientManagerInterface::class);

    // A front client created before passkeys existed
    $application = new Application(self::$kernel);
    new CommandTester($application->find('league:oauth2-server:create-client'))->execute([
      'name' => 'Narvik front',
      'identifier' => 'front',
      'secret' => 'a-custom-secret-set-by-the-operator',
      '--grant-type' => ['password', 'refresh_token'],
    ]);
    $secret = $clientManager->find('front')->getSecret();

    $this->runInstallOAuth();
    $this->runInstallOAuth(); // Idempotent

    $front = $clientManager->find('front');
    $this->assertSame(['password', 'refresh_token', PasskeyGrant::IDENTIFIER], array_map(strval(...), $front->getGrants()));
    $this->assertSame($secret, $front->getSecret(), 'the secret must be kept');
  }
}
