<?php

namespace App\Service\PaymentTerminal;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminalConnection;
use App\Enum\SalePaymentTerminalCheckoutStatus;
use App\Enum\SalePaymentTerminalProvider;
use App\Service\PaymentTerminal\Credentials\SumUpCredentials;
use App\Service\PaymentTerminal\Credentials\TerminalCredentialsInterface;
use App\Service\PaymentTerminal\Dto\TerminalCheckoutResult;
use App\Service\PaymentTerminal\Dto\TerminalCheckoutStatusResult;
use App\Service\PaymentTerminal\Dto\TerminalDevice;
use SumUp\Exception\ApiException;
use SumUp\Exception\SDKException;
use SumUp\SumUp;
use SumUp\Types\Reader;
use SumUp\Types\ReaderStatus;
use SumUp\Types\StatusResponseDataStatus;
use SumUp\Services\TransactionsGetParams;

/**
 * Payment terminal provider for SumUp Solo readers, backed by the official
 * sumup/sumup-php SDK (Cloud API).
 *
 * Flow:
 *  - createCheckout() → POST reader checkout, returns a client_transaction_id
 *  - getCheckoutStatus() → poll the Transactions API by client_transaction_id
 */
class SumUpPaymentTerminalProvider extends AbstractPaymentTerminalProvider {
  private const string CURRENCY = 'EUR';
  private const int MINOR_UNIT = 2;

  // Map SumUp transaction status strings to our internal enum
  private const array STATUS_MAP = [
    'SUCCESSFUL' => SalePaymentTerminalCheckoutStatus::successful,
    'FAILED' => SalePaymentTerminalCheckoutStatus::failed,
    'CANCELLED' => SalePaymentTerminalCheckoutStatus::cancelled,
    'REFUNDED' => SalePaymentTerminalCheckoutStatus::cancelled,
    'PENDING' => SalePaymentTerminalCheckoutStatus::pending,
  ];

  public function getProvider(): SalePaymentTerminalProvider {
    return SalePaymentTerminalProvider::sumup;
  }

  public function credentialsFromArray(array $data): TerminalCredentialsInterface {
    return SumUpCredentials::fromArray($data);
  }

  public function validateCredentials(array $data): void {
    // A saved terminal must be complete enough to charge (reader selected)
    SumUpCredentials::fromArray($data)->assertComplete();
  }

  #[\Override]
  public function canListDevices(): bool {
    return true;
  }

  public function listDevices(TerminalCredentialsInterface $credentials): array {
    /** @var SumUpCredentials $credentials */
    $sumup = $this->clientFor($credentials);

    try {
      $response = $sumup->readers()->list($credentials->merchantCode);
    }
    catch (SDKException $e) {
      throw $this->wrapException('Failed to retrieve terminals', $e);
    }

    $devices = [];
    foreach ($response->items as $reader) {
      $devices[] = $this->mapReaderToDevice($sumup, $credentials, $reader);
    }
    return $devices;
  }

  public function getDeviceStatus(TerminalCredentialsInterface $credentials): TerminalDevice {
    /** @var SumUpCredentials $credentials */
    $credentials->assertComplete();
    $sumup = $this->clientFor($credentials);

    try {
      $reader = $sumup->readers()->get($credentials->merchantCode, $credentials->readerId);
    }
    catch (SDKException $e) {
      throw $this->wrapException('Failed to retrieve terminal status', $e);
    }

    return $this->mapReaderToDevice($sumup, $credentials, $reader);
  }

  public function createCheckout(SalePaymentTerminalConnection $connection, string $deviceId, string $amount, string $description): TerminalCheckoutResult {
    /** @var SumUpCredentials $credentials */
    $credentials = $this->credentialsOf($connection, $deviceId);
    $credentials->assertComplete();
    $sumup = $this->clientFor($credentials);

    // Array form (canonical per the SumUp PHP SDK docs). Only total_amount and
    // description are sent.
    $body = [
      'total_amount' => [
        'currency' => self::CURRENCY,
        'minor_unit' => self::MINOR_UNIT,
        'value' => $this->toMinorUnits($amount),
      ],
    ];
    if ($description !== '') {
      $body['description'] = $description;
    }

    try {
      $response = $sumup->readers()->createCheckout(
        $credentials->merchantCode,
        $credentials->readerId,
        $body,
      );
    }
    catch (SDKException $e) {
      throw $this->wrapException('Failed to create payment on the terminal', $e);
    }

    $clientTransactionId = $response->data->clientTransactionId ?? null;
    if (empty($clientTransactionId)) {
      throw new PaymentTerminalException('SumUp checkout response did not include a client_transaction_id.');
    }

    return new TerminalCheckoutResult(clientTransactionId: $clientTransactionId);
  }

  public function getCheckoutStatus(SalePaymentTerminalConnection $connection, string $clientTransactionId): TerminalCheckoutStatusResult {
    /** @var SumUpCredentials $credentials */
    $credentials = $this->credentialsOf($connection);
    $sumup = $this->clientFor($credentials);

    $params = new TransactionsGetParams();
    $params->clientTransactionId = $clientTransactionId;

    try {
      $transaction = $sumup->transactions()->get($credentials->merchantCode, $params);
    }
    catch (ApiException $e) {
      // 404 = transaction not created yet on SumUp's side → still pending
      if ($e->getStatusCode() === 404) {
        return new TerminalCheckoutStatusResult(status: SalePaymentTerminalCheckoutStatus::pending);
      }
      throw $this->wrapException('Failed to retrieve payment status', $e);
    }
    catch (SDKException $e) {
      throw $this->wrapException('Failed to retrieve payment status', $e);
    }

    $rawStatus = strtoupper((string) ($transaction->status ?? 'PENDING'));
    $status = self::STATUS_MAP[$rawStatus] ?? SalePaymentTerminalCheckoutStatus::pending;
    $transactionId = $transaction->transactionCode ?? $transaction->id ?? null;

    return new TerminalCheckoutStatusResult(
      status: $status,
      transactionId: $transactionId !== null ? (string) $transactionId : null,
    );
  }

  public function cancelCheckout(SalePaymentTerminalConnection $connection, string $deviceId): void {
    /** @var SumUpCredentials $credentials */
    $credentials = $this->credentialsOf($connection, $deviceId);
    $credentials->assertComplete();
    $sumup = $this->clientFor($credentials);

    try {
      $sumup->readers()->terminateCheckout($credentials->merchantCode, $credentials->readerId);
    }
    catch (ApiException $e) {
      // 404 = nothing pending on the reader anymore (e.g. it already finished) — not an error
      if ($e->getStatusCode() === 404) {
        return;
      }
      throw $this->wrapException('Failed to cancel the payment on the terminal', $e);
    }
    catch (SDKException $e) {
      throw $this->wrapException('Failed to cancel the payment on the terminal', $e);
    }
  }

  private function clientFor(SumUpCredentials $credentials): SumUp {
    return new SumUp($credentials->apiKey);
  }

  /**
   * Build a TerminalDevice from a reader, checking its live online status.
   * A failed status check is treated as offline (device unreachable) rather than fatal.
   */
  private function mapReaderToDevice(SumUp $sumup, SumUpCredentials $credentials, Reader $reader): TerminalDevice {
    $paired = $reader->status === ReaderStatus::PAIRED;
    $online = false;
    $statusData = null;

    if ($paired) {
      try {
        $statusData = $sumup->readers()->getStatus($credentials->merchantCode, $reader->id)->data;
        $online = ($statusData->status ?? null) === StatusResponseDataStatus::ONLINE;
      }
      catch (SDKException $e) {
        // Status unavailable → treat as offline; log for diagnostics
        $this->logger->info('SumUp reader status unavailable', [
          'reader' => $reader->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    return new TerminalDevice(
      id: $reader->id,
      name: $reader->name !== '' ? $reader->name : $reader->id,
      model: $reader->device->model->value ?? null,
      online: $online,
      paired: $paired,
      state: $statusData?->state?->value,
      lastActivity: $statusData?->lastActivity,
      batteryLevel: $statusData?->batteryLevel,
      connectionType: $statusData?->connectionType?->value,
    );
  }

  private function wrapException(string $context, SDKException $e): PaymentTerminalException {
    $detail = $e->getMessage();

    // SumUp returns RFC-7807 problem bodies (e.g. 422 { title, detail }) — surface them
    $body = method_exists($e, 'getResponseBody') ? $e->getResponseBody() : null;
    if (is_array($body)) {
      $title = $body['title'] ?? null;
      $problemDetail = $body['detail'] ?? $body['message'] ?? $body['error_message'] ?? null;
      $parts = array_filter([$title, $problemDetail], fn($v) => is_string($v) && $v !== '');
      if (!empty($parts)) {
        $detail = implode(' — ', array_unique($parts));
      }
    }

    $this->logger->warning('SumUp terminal error', [
      'context' => $context,
      'status' => $e->getStatusCode(),
      'message' => $e->getMessage(),
      'body' => $body,
    ]);

    return new PaymentTerminalException($context.': '.$detail, $e);
  }
}
