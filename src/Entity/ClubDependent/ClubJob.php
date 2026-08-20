<?php

namespace App\Entity\ClubDependent;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\ClubJobKey;
use App\Enum\ClubJobStatus;
use App\Enum\ClubRole;
use App\Repository\ClubDependent\ClubJobRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Generic progress tracker for a per-club background job
 */
#[ORM\Entity(repositoryClass: ClubJobRepository::class)]
#[ORM\UniqueConstraint(name: 'club_job_club_key_unique', fields: ['club', 'key'])]
#[UniqueEntity(fields: ['club', 'key'])]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/jobs/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/jobs.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::supervisor->value."', request)",
    ),
    new Get(
      security: "is_granted('".ClubRole::supervisor->value."', object)"
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['club-job']
  ],
)]
class ClubJob extends UuidEntity implements ClubLinkedEntityInterface, TimestampEntityInterface {
  use SelfClubLinkedEntityTrait;
  use TimestampTrait;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, enumType: ClubJobKey::class)]
  #[Groups(['club-job'])]
  private ClubJobKey $key;

  #[ORM\Column]
  #[Groups(['club-job'])]
  private int $total = 0;

  #[ORM\Column]
  #[Groups(['club-job'])]
  private int $remaining = 0;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, enumType: ClubJobStatus::class)]
  #[Groups(['club-job'])]
  private ClubJobStatus $status = ClubJobStatus::IN_PROGRESS;

  public function getKey(): ClubJobKey {
    return $this->key;
  }

  public function setKey(ClubJobKey $key): static {
    $this->key = $key;
    return $this;
  }

  public function getTotal(): int {
    return $this->total;
  }

  public function setTotal(int $total): static {
    $this->total = max(0, $total);
    return $this;
  }

  public function getRemaining(): int {
    return $this->remaining;
  }

  public function setRemaining(int $remaining): static {
    $this->remaining = max(0, $remaining);
    return $this;
  }

  public function getStatus(): ClubJobStatus {
    return $this->status;
  }

  public function setStatus(ClubJobStatus $status): static {
    $this->status = $status;
    return $this;
  }
}
