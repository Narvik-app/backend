<?php

namespace App\Tests\Unit\Entity\ClubDependent\Plugin\Sale;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminalConnection;
use PHPUnit\Framework\TestCase;

class SalePaymentTerminalTest extends TestCase {
  private function configuredConnection(): SalePaymentTerminalConnection {
    $connection = new SalePaymentTerminalConnection();
    $connection->setAvailable(true);
    $connection->setCredentials(['apiKey' => 'sk_test', 'merchantCode' => 'MC1']);
    return $connection;
  }

  public function testIsUsableWhenAvailableAndConnectionConfigured(): void {
    $terminal = new SalePaymentTerminal();
    $terminal->setAvailable(true);
    $terminal->setConnection($this->configuredConnection());

    $this->assertTrue($terminal->isUsable());
  }

  public function testIsNotUsableWhenTerminalUnavailable(): void {
    $terminal = new SalePaymentTerminal();
    $terminal->setAvailable(false);
    $terminal->setConnection($this->configuredConnection());

    $this->assertFalse($terminal->isUsable());
  }

  public function testIsNotUsableWhenConnectionUnavailable(): void {
    $connection = $this->configuredConnection();
    $connection->setAvailable(false);

    $terminal = new SalePaymentTerminal();
    $terminal->setAvailable(true);
    $terminal->setConnection($connection);

    $this->assertFalse($terminal->isUsable());
  }

  public function testIsNotUsableWhenConnectionHasNoCredentials(): void {
    $connection = $this->configuredConnection();
    $connection->setCredentials(null);

    $terminal = new SalePaymentTerminal();
    $terminal->setAvailable(true);
    $terminal->setConnection($connection);

    $this->assertFalse($terminal->isUsable());
  }

  public function testIsForceTerminalSelectionDelegatesToConnection(): void {
    $connection = $this->configuredConnection();
    $connection->setForceTerminalSelection(true);

    $terminal = new SalePaymentTerminal();
    $terminal->setConnection($connection);

    $this->assertTrue($terminal->isForceTerminalSelection());
  }

  public function testIsForceTerminalSelectionFalseWhenConnectionDoesNotForceIt(): void {
    $terminal = new SalePaymentTerminal();
    $terminal->setConnection($this->configuredConnection());

    $this->assertFalse($terminal->isForceTerminalSelection());
  }

  public function testSetNameCapitalizesFirstLetter(): void {
    $terminal = new SalePaymentTerminal();
    $terminal->setName('caisse principale');

    $this->assertSame('Caisse principale', $terminal->getName());
  }
}
