<?php

namespace App\Tests\Unit\Service\PaymentTerminal;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminalConnection;
use App\Enum\SalePaymentTerminalProvider;
use App\Service\PaymentTerminal\Credentials\TerminalCredentialsInterface;
use App\Service\PaymentTerminal\Dto\TerminalCheckoutResult;
use App\Service\PaymentTerminal\Dto\TerminalCheckoutStatusResult;
use App\Service\PaymentTerminal\Dto\TerminalDevice;
use App\Service\PaymentTerminal\PaymentTerminalManager;
use App\Service\PaymentTerminal\PaymentTerminalProviderInterface;
use PHPUnit\Framework\TestCase;

class PaymentTerminalManagerTest extends TestCase {
  private function fakeProvider(SalePaymentTerminalProvider $provider): PaymentTerminalProviderInterface {
    return new readonly class($provider) implements PaymentTerminalProviderInterface {
      public function __construct(private SalePaymentTerminalProvider $provider) {
      }

      public function getProvider(): SalePaymentTerminalProvider {
        return $this->provider;
      }

      public function credentialsFromArray(array $data): TerminalCredentialsInterface {
        throw new \RuntimeException('not implemented');
      }

      public function validateCredentials(array $data): void {
      }

      public function canListDevices(): bool {
        return false;
      }

      public function listDevices(TerminalCredentialsInterface $credentials): array {
        return [];
      }

      public function getDeviceStatus(TerminalCredentialsInterface $credentials): TerminalDevice {
        throw new \RuntimeException('not implemented');
      }

      public function credentialsForDevice(SalePaymentTerminalConnection $connection, string $deviceId): TerminalCredentialsInterface {
        throw new \RuntimeException('not implemented');
      }

      public function createCheckout(SalePaymentTerminalConnection $connection, string $deviceId, string $amount, string $description): TerminalCheckoutResult {
        throw new \RuntimeException('not implemented');
      }

      public function getCheckoutStatus(SalePaymentTerminalConnection $connection, string $clientTransactionId): TerminalCheckoutStatusResult {
        throw new \RuntimeException('not implemented');
      }

      public function cancelCheckout(SalePaymentTerminalConnection $connection, string $deviceId): void {
      }
    };
  }

  public function testForProviderResolvesRegisteredProvider(): void {
    $sumup = $this->fakeProvider(SalePaymentTerminalProvider::sumup);
    $manager = new PaymentTerminalManager([$sumup]);

    $this->assertSame($sumup, $manager->forProvider(SalePaymentTerminalProvider::sumup));
  }

  public function testForConnectionResolvesProviderMatchingConnection(): void {
    $sumup = $this->fakeProvider(SalePaymentTerminalProvider::sumup);
    $manager = new PaymentTerminalManager([$sumup]);

    $connection = new SalePaymentTerminalConnection();
    $connection->setProvider(SalePaymentTerminalProvider::sumup);

    $this->assertSame($sumup, $manager->forConnection($connection));
  }

  public function testForProviderWithNoRegisteredImplementationThrows(): void {
    $manager = new PaymentTerminalManager([]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("No payment terminal provider registered for 'sumup'");
    $manager->forProvider(SalePaymentTerminalProvider::sumup);
  }

  public function testLastRegisteredProviderWinsOnDuplicateProviderValue(): void {
    $first = $this->fakeProvider(SalePaymentTerminalProvider::sumup);
    $second = $this->fakeProvider(SalePaymentTerminalProvider::sumup);
    $manager = new PaymentTerminalManager([$first, $second]);

    $this->assertSame($second, $manager->forProvider(SalePaymentTerminalProvider::sumup));
  }
}
