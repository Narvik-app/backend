<?php

namespace App\Message;

use App\Enum\ClubJobKey;
use App\Message\Abstract\ClubLinkedMessage;

class CerberePresencesDateMessage extends ClubLinkedMessage {
  public function getJobKey(): ClubJobKey {
    return ClubJobKey::IMPORT_CERBERE;
  }

  public function __construct(
    string $clubUuid,
    private readonly \DateTimeImmutable $date,
    private readonly array $datas
  ) {
    parent::__construct($clubUuid);
  }

  public function getDate(): \DateTimeImmutable {
    return $this->date;
  }

  public function getDatas(): array {
    return $this->datas;
  }
}
