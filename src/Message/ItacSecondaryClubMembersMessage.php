<?php

namespace App\Message;

use App\Enum\ClubJobKey;
use App\Message\Abstract\ClubLinkedMessage;

class ItacSecondaryClubMembersMessage extends ClubLinkedMessage {

  public function getJobKey(): ClubJobKey {
    return ClubJobKey::IMPORT_ITAC_SECONDARY;
  }

  public function __construct(
    string $clubUuid,
    private readonly array $records,
  ) {
    parent::__construct($clubUuid);
  }

  public function getRecords(): array {
    return $this->records;
  }
}
