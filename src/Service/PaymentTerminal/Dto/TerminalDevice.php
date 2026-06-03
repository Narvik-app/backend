<?php

namespace App\Service\PaymentTerminal\Dto;

/**
 * A payment terminal device discovered from a provider during setup.
 */
final readonly class TerminalDevice {
  public function __construct(
    public string $id,
    public string $name,
    public ?string $model = null,
    public bool $online = false,
    public bool $paired = true,
  ) {
  }

  /**
   * A device is selectable/available when it is reachable (online).
   */
  public function isAvailable(): bool {
    return $this->online;
  }

  public function toArray(): array {
    return [
      'id' => $this->id,
      'name' => $this->name,
      'model' => $this->model,
      'online' => $this->online,
      'paired' => $this->paired,
      'available' => $this->isAvailable(),
    ];
  }
}
