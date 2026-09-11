<?php

namespace App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\File;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\Permission;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportAttestationRepository;
use App\Security\Voter\SelfMemberVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One member's frozen totals + PDF attestation for a given export. Storing this
 * as real rows (rather than a JSON blob on the export) keeps the recap
 * queryable and gives the member's own board a direct link to their document.
 */
#[ORM\Entity(repositoryClass: TimeAndTravelExportAttestationRepository::class)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/time-and-travel-exports/{exportUuid}/attestations.{_format}',
  operations: [
    new GetCollection(
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', request)",
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'exportUuid' => new Link(toProperty: 'export', fromClass: TimeAndTravelExport::class),
  ],
  normalizationContext: [
    'groups' => ['time-and-travel-export-attestation']
  ],
)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/time-and-travel-attestations.{_format}',
  operations: [
    new GetCollection(
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', request) || is_granted('".SelfMemberVoter::READ."', request)",
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
  ],
  normalizationContext: [
    'groups' => ['time-and-travel-export-attestation']
  ],
)]
class TimeAndTravelExportAttestation extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\ManyToOne(targetEntity: TimeAndTravelExport::class, inversedBy: 'attestations')]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  #[Groups(['time-and-travel-export-attestation'])]
  #[ApiProperty(readableLink: false)]
  private ?TimeAndTravelExport $export = null;

  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-export-attestation'])]
  #[ApiProperty(readableLink: true)]
  #[Assert\NotNull]
  private ?Member $member = null;

  #[ORM\OneToOne(targetEntity: File::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-export-attestation'])]
  #[ApiProperty(readableLink: true)]
  private ?File $file = null;

  #[ORM\Column(type: Types::INTEGER)]
  #[Groups(['time-and-travel-export-attestation'])]
  private int $totalKilometers = 0;

  #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
  #[Groups(['time-and-travel-export-attestation'])]
  private string $totalHours = '0.00';

  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  #[Groups(['time-and-travel-export-attestation'])]
  private string $totalTravelAmount = '0.00';

  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  #[Groups(['time-and-travel-export-attestation'])]
  private string $totalTimeAmount = '0.00';

  /** Not directly exposed — read/written through getTotalAmount()/setTotalAmount() to avoid two competing property names in the serializer */
  #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
  private string $totalAmountPersisted = '0.00';

  public function __construct() {
    parent::__construct();
  }

  public function getExport(): ?TimeAndTravelExport {
    return $this->export;
  }

  public function setExport(?TimeAndTravelExport $export): static {
    $this->export = $export;
    if ($export) {
      $this->club = $export->getClub();
    }
    return $this;
  }

  public function getMember(): ?Member {
    return $this->member;
  }

  public function setMember(?Member $member): static {
    $this->member = $member;
    return $this;
  }

  public function getFile(): ?File {
    return $this->file;
  }

  public function setFile(?File $file): static {
    $this->file = $file;
    return $this;
  }

  public function getTotalKilometers(): int {
    return $this->totalKilometers;
  }

  public function setTotalKilometers(int $totalKilometers): static {
    $this->totalKilometers = $totalKilometers;
    return $this;
  }

  public function getTotalHours(): string {
    return $this->totalHours;
  }

  public function setTotalHours(string $totalHours): static {
    $this->totalHours = $totalHours;
    return $this;
  }

  public function getTotalTravelAmount(): string {
    return $this->totalTravelAmount;
  }

  public function setTotalTravelAmount(string $totalTravelAmount): static {
    $this->totalTravelAmount = $totalTravelAmount;
    return $this;
  }

  public function getTotalTimeAmount(): string {
    return $this->totalTimeAmount;
  }

  public function setTotalTimeAmount(string $totalTimeAmount): static {
    $this->totalTimeAmount = $totalTimeAmount;
    return $this;
  }

  #[Groups(['time-and-travel-export-attestation'])]
  public function getTotalAmount(): float {
    return (float) $this->totalAmountPersisted;
  }

  public function setTotalAmount(string $totalAmount): static {
    $this->totalAmountPersisted = $totalAmount;
    return $this;
  }
}
