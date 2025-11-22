<?php

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Component\Filesystem\Filesystem;
use Zenstruck\Foundry\Test\Factories;

abstract class AbstractTestCase extends ApiTestCase {
  use Factories;

  protected static ?bool $alwaysBootKernel = true; // We make the test ready for api-platform 5.0

  public function setUp(): void {
    parent::setUp();
    $this->initBaseFixtures();
    $this->initDefaultFixtures();
  }

  protected function initBaseFixtures(): void {}

  public function initDefaultFixtures(): void {}

  #[Before]
  public static function _resetDatabaseBeforeEachTest(): void {
    $registry = self::getContainer()->get('doctrine');
    /** @var Connection $connection */
    $connection = $registry->getConnection();
    $connection->executeQuery('CREATE EXTENSION IF NOT EXISTS unaccent;');
  }


  public function tearDown(): void {
    $fs = self::getContainer()->get(FileSystem::class);
    $testFolder = self::$kernel->getContainer()->getParameter('app.files');
    if ($fs->exists($testFolder)) {
      $fs->remove($testFolder);
    }

    parent::tearDown();
  }

  public function debugTestDatabase(): never {
    \DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver::commit();
    die; // The DB changes are actually persisted
  }
}
