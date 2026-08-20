<?php

namespace App\Message\Abstract;

use App\Enum\ClubJobKey;

abstract class ClubLinkedMessage {
  public abstract function getJobKey(): ClubJobKey;

  public function __construct(
    private readonly string $clubUuid,
  ) {
  }

  public function getClubUuid(): string {
    return $this->clubUuid;
  }

}
