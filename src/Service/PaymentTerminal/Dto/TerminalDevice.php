<?php

namespace App\Service\PaymentTerminal\Dto;

/**
 * A payment terminal device discovered from a provider, with optional live diagnostics.
 */
final readonly class TerminalDevice {
  public function __construct(
    public string $id,
    public string $name,
    public ?string $model = null,
    public bool $online = false,
    public bool $paired = true,
    // Optional live diagnostics (when a status check is available)
    public ?string $state = null,
    public ?string $lastActivity = null,
    public ?float $batteryLevel = null,
    public ?string $connectionType = null,
  ) {
  }

  /**
   * A device is selectable/available when it is reachable (online).
   * Note: SumUp only accepts checkouts when status is ONLINE — a device reporting
   * a recent `state`/`lastActivity` but ONLINE=false is still not chargeable.
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
      'state' => $this->state,
      'lastActivity' => $this->lastActivity,
      'batteryLevel' => $this->batteryLevel,
      'connectionType' => $this->connectionType,
    ];
  }
}
