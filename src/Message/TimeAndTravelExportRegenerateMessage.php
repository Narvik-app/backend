<?php

namespace App\Message;

/**
 * Regenerates a draft export's PDFs (recap + every attestation) from its currently exportable
 * declarations. Dispatched either automatically when a declaration changes within a draft export's
 * period, or from the lock endpoint — locking always regenerates first, to be sure the frozen
 * result reflects every declaration added right up until the click.
 */
class TimeAndTravelExportRegenerateMessage {
  public function __construct(
    private readonly string $exportUuid,
    private readonly bool $lockAfter = false,
    private readonly ?string $lockedByMemberUuid = null,
  ) {
  }

  public function getExportUuid(): string {
    return $this->exportUuid;
  }

  public function isLockAfter(): bool {
    return $this->lockAfter;
  }

  public function getLockedByMemberUuid(): ?string {
    return $this->lockedByMemberUuid;
  }
}
