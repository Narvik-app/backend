<?php

namespace App\Entity\ClubDependent;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\ClubRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: \App\Repository\ClubDependent\MemberControlRepository::class)]
#[ORM\UniqueConstraint(name: 'member_control_member_type_unique', fields: ['member', 'type'])]
#[UniqueEntity(fields: ['member', 'type'])]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/member-controls/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/member-controls.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::badger->value."', request) || is_granted('".ClubRole::member->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/member-controls.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      securityPostDenormalize: "is_granted('".ClubRole::supervisor->value."', request)",
      read: false
    ),

    new Get(
      security: "is_granted('".ClubRole::badger->value."', object) || is_granted('".ClubRole::member->value."', object)"
    ),
    new Patch(
      security: "is_granted('".ClubRole::supervisor->value."', object)",
    ),
    new Delete(
      security: "is_granted('".ClubRole::supervisor->value."', object)"
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['member-control', 'member-control-read']
  ],
  denormalizationContext: [
    'groups' => ['member-control', 'member-control-write']
  ],
)]
class MemberControl extends UuidEntity implements ClubLinkedEntityInterface, TimestampEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;
  #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'controls')]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  #[Groups(['member-control'])]
  private ?Member $member = null;

  #[ORM\ManyToOne(targetEntity: MemberControlType::class, inversedBy: 'controls')]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  #[Groups(['member-control', 'member-read', 'member-presence-read'])]
  private ?MemberControlType $type = null;

  #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
  #[Groups(['member-control', 'member-read', 'member-presence-read'])]
  private ?\DateTimeImmutable $date = null;

  #[ORM\Column(options: ['default' => false])]
  #[Groups(['member-control', 'member-read', 'member-presence-read'])]
  private bool $alertDisabled = false;

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  #[Groups(['member-control'])]
  private ?string $comment = null;

  public function getMember(): ?Member {
    return $this->member;
  }

  public function setMember(?Member $member): static {
    $this->member = $member;
    if ($member) {
      $this->setClub($member->getClub());
    }
    return $this;
  }

  public function getType(): ?MemberControlType {
    return $this->type;
  }

  public function setType(?MemberControlType $type): static {
    $this->type = $type;
    return $this;
  }

  public function getDate(): ?\DateTimeImmutable {
    return $this->date;
  }

  public function setDate(?\DateTimeImmutable $date): static {
    $this->date = $date;
    return $this;
  }

  public function isAlertDisabled(): bool {
    return $this->alertDisabled;
  }

  public function setAlertDisabled(bool $alertDisabled): static {
    $this->alertDisabled = $alertDisabled;
    return $this;
  }

  public function getComment(): ?string {
    return $this->comment;
  }

  public function setComment(?string $comment): static {
    $this->comment = $comment;
    return $this;
  }

  /**
   * Computed status of this control against its type's thresholds.
   * `null` means "nothing to alert on" (no date, or the alert is muted, or the type has no alert delay).
   */
  #[Groups(['member-control-read', 'member-read', 'member-presence-read'])]
  public function getStatus(): ?string {
    if (!$this->date || $this->alertDisabled || !$this->type) {
      return null;
    }

    $daysSince = new \DateTimeImmutable()->diff($this->date)->days;

    $alertDays = $this->type->getAlertDays();
    if ($alertDays !== null && $daysSince >= $alertDays) {
      return 'expired';
    }

    $warningDays = $this->type->getWarningDays();
    if ($warningDays !== null && $daysSince >= $warningDays) {
      return 'warning';
    }

    return 'valid';
  }
}
