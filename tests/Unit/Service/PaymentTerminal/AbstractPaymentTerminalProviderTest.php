<?php

namespace App\Tests\Unit\Service\PaymentTerminal;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminalConnection;
use App\Enum\SalePaymentTerminalProvider;
use App\Service\PaymentTerminal\AbstractPaymentTerminalProvider;
use App\Service\PaymentTerminal\Credentials\TerminalCredentialsInterface;
use App\Service\PaymentTerminal\Dto\TerminalCheckoutResult;
use App\Service\PaymentTerminal\Dto\TerminalCheckoutStatusResult;
use App\Service\PaymentTerminal\PaymentTerminalException;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

class AbstractPaymentTerminalProviderTest extends TestCase {
  private function provider(): AbstractPaymentTerminalProvider {
    return new class(new NullLogger()) extends AbstractPaymentTerminalProvider {
      public function getProvider(): SalePaymentTerminalProvider {
        return SalePaymentTerminalProvider::sumup;
      }

      public function credentialsFromArray(array $data): TerminalCredentialsInterface {
        return new class($data) implements TerminalCredentialsInterface {
          public function __construct(private readonly array $data) {
          }

          public function assertComplete(): void {
          }

          public function getProvider(): SalePaymentTerminalProvider {
            return SalePaymentTerminalProvider::sumup;
          }

          public function jsonSerialize(): array {
            return $this->data;
          }
        };
      }

      public function validateCredentials(array $data): void {
      }

      public function listDevices(TerminalCredentialsInterface $credentials): array {
        return [];
      }

      public function createCheckout(SalePaymentTerminalConnection $connection, string $deviceId, string $amount, string $description): TerminalCheckoutResult {
        return new TerminalCheckoutResult(clientTransactionId: 'tx');
      }

      public function getCheckoutStatus(SalePaymentTerminalConnection $connection, string $clientTransactionId): TerminalCheckoutStatusResult {
        throw new \RuntimeException('not implemented');
      }

      public function cancelCheckout(SalePaymentTerminalConnection $connection, string $deviceId): void {
      }

      protected function withDeviceId(array $credentials, string $deviceId): array {
        $credentials['readerId'] = $deviceId;
        return $credentials;
      }

      public function callToMinorUnits(string $amount, int $exponent = 2): int {
        return $this->toMinorUnits($amount, $exponent);
      }

      public function callCredentialsOf(SalePaymentTerminalConnection $connection, ?string $deviceId = null): TerminalCredentialsInterface {
        return $this->credentialsOf($connection, $deviceId);
      }
    };
  }

  public function testToMinorUnitsConvertsDecimalAmount(): void {
    $this->assertSame(1500, $this->provider()->callToMinorUnits('15.00'));
  }

  public function testToMinorUnitsRoundsFractionalCents(): void {
    $this->assertSame(1001, $this->provider()->callToMinorUnits('10.005'));
  }

  public function testToMinorUnitsHandlesZero(): void {
    $this->assertSame(0, $this->provider()->callToMinorUnits('0'));
  }

  public function testCredentialsOfThrowsWhenConnectionHasNoCredentials(): void {
    $connection = new SalePaymentTerminalConnection();
    $connection->setCredentials(null);

    $this->expectException(PaymentTerminalException::class);
    $this->expectExceptionMessage('has no credentials configured');
    $this->provider()->callCredentialsOf($connection);
  }

  public function testCredentialsOfMergesDeviceIdWhenProvided(): void {
    $connection = new SalePaymentTerminalConnection();
    $connection->setCredentials(['apiKey' => 'sk_test', 'merchantCode' => 'MC1']);

    $credentials = $this->provider()->callCredentialsOf($connection, 'reader-42');

    $this->assertSame([
      'apiKey' => 'sk_test',
      'merchantCode' => 'MC1',
      'readerId' => 'reader-42',
    ], $credentials->jsonSerialize());
  }

  public function testCredentialsForDeviceDelegatesToCredentialsOf(): void {
    $connection = new SalePaymentTerminalConnection();
    $connection->setCredentials(['apiKey' => 'sk_test', 'merchantCode' => 'MC1']);

    $credentials = $this->provider()->credentialsForDevice($connection, 'reader-42');

    $this->assertSame('reader-42', $credentials->jsonSerialize()['readerId']);
  }

  public function testCanListDevicesDefaultsToFalse(): void {
    $provider = new class(new NullLogger()) extends AbstractPaymentTerminalProvider {
      public function getProvider(): SalePaymentTerminalProvider {
        return SalePaymentTerminalProvider::sumup;
      }

      public function credentialsFromArray(array $data): TerminalCredentialsInterface {
        throw new \RuntimeException('not implemented');
      }

      public function validateCredentials(array $data): void {
      }

      public function createCheckout(SalePaymentTerminalConnection $connection, string $deviceId, string $amount, string $description): TerminalCheckoutResult {
        throw new \RuntimeException('not implemented');
      }

      public function getCheckoutStatus(SalePaymentTerminalConnection $connection, string $clientTransactionId): TerminalCheckoutStatusResult {
        throw new \RuntimeException('not implemented');
      }

      public function cancelCheckout(SalePaymentTerminalConnection $connection, string $deviceId): void {
      }

      protected function withDeviceId(array $credentials, string $deviceId): array {
        return $credentials;
      }
    };

    $this->assertFalse($provider->canListDevices());

    $credentials = new class() implements TerminalCredentialsInterface {
      public function assertComplete(): void {
      }

      public function getProvider(): SalePaymentTerminalProvider {
        return SalePaymentTerminalProvider::sumup;
      }

      public function jsonSerialize(): array {
        return [];
      }
    };

    $this->expectException(PaymentTerminalException::class);
    $this->expectExceptionMessage('does not support listing devices');
    $provider->listDevices($credentials);
  }
}
