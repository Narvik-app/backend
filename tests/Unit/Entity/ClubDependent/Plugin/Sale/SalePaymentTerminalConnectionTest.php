<?php

namespace App\Tests\Unit\Entity\ClubDependent\Plugin\Sale;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminalConnection;
use PHPUnit\Framework\TestCase;

class SalePaymentTerminalConnectionTest extends TestCase {
  public function testIsNotConfiguredByDefault(): void {
    $connection = new SalePaymentTerminalConnection();

    $this->assertFalse($connection->isConfigured());
  }

  public function testIsNotConfiguredWithEmptyCredentials(): void {
    $connection = new SalePaymentTerminalConnection();
    $connection->setCredentials([]);

    $this->assertFalse($connection->isConfigured());
  }

  public function testIsConfiguredWithCredentials(): void {
    $connection = new SalePaymentTerminalConnection();
    $connection->setCredentials(['apiKey' => 'sk_test', 'merchantCode' => 'MC1']);

    $this->assertTrue($connection->isConfigured());
  }

  public function testSetNameCapitalizesFirstLetter(): void {
    $connection = new SalePaymentTerminalConnection();
    $connection->setName('caisse sumup');

    $this->assertSame('Caisse sumup', $connection->getName());
  }

  public function testNewConnectionHasNoDevices(): void {
    $connection = new SalePaymentTerminalConnection();

    $this->assertCount(0, $connection->getDevices());
  }
}
