<?php

namespace App\Message;

use App\Enum\ClubJobKey;
use App\Message\Abstract\ClubLinkedMessage;

class MemberControlSyncMessage extends ClubLinkedMessage {

  public function getJobKey(): ClubJobKey {
    return ClubJobKey::member_control_sync;
  }

  /**
   * @param string $clubUuid
   * @param string $typeUuid
   * @param string[] $memberUuids
   */
  public function __construct(
    string $clubUuid,
    private readonly string $typeUuid,
    private readonly array $memberUuids,
  ) {
    parent::__construct($clubUuid);
  }

  public function getTypeUuid(): string {
    return $this->typeUuid;
  }

  /**
   * @return string[]
   */
  public function getMemberUuids(): array {
    return $this->memberUuids;
  }
}
