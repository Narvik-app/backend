<?php

namespace App\Service\PaymentTerminal\Credentials;

use App\Enum\SalePaymentTerminalProvider;

final readonly class SumUpCredentials implements TerminalCredentialsInterface {
  public function __construct(
    public string $apiKey,
    public string $merchantCode,
    public ?string $readerId = null,
  ) {
  }

  /**
   * Lenient factory: requires only the fields needed to authenticate and list devices
   * (apiKey + merchantCode). readerId is optional here because it is discovered during
   * the setup device-listing step. Use assertComplete() to require a reader for charging.
   */
  public static function fromArray(array $data): self {
    foreach (['apiKey', 'merchantCode'] as $field) {
      if (empty($data[$field])) {
        throw new \InvalidArgumentException("Missing required SumUp credential field: '$field'.");
      }
    }

    return new self(
      apiKey: (string) $data['apiKey'],
      merchantCode: (string) $data['merchantCode'],
      readerId: isset($data['readerId']) && $data['readerId'] !== '' ? (string) $data['readerId'] : null,
    );
  }

  /**
   * Ensure the credentials are complete enough to charge a terminal (reader selected).
   *
   * @throws \InvalidArgumentException
   */
  public function assertComplete(): void {
    if (empty($this->readerId)) {
      throw new \InvalidArgumentException("Missing required SumUp credential field: 'readerId'.");
    }
  }

  public function getProvider(): SalePaymentTerminalProvider {
    return SalePaymentTerminalProvider::sumup;
  }

  public function jsonSerialize(): array {
    return array_filter([
      'apiKey' => $this->apiKey,
      'merchantCode' => $this->merchantCode,
      'readerId' => $this->readerId,
    ], fn($v) => $v !== null);
  }
}
