<?php

namespace App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportLock;
use App\Controller\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportRegenerate;
use App\Controller\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportUnlock;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\File;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\Permission;
use App\Enum\TimeAndTravelExportStatus;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportRepository;
use App\State\TimeAndTravelExportDeleteProcessor;
use App\State\TimeAndTravelExportProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TimeAndTravelExportRepository::class)]
#[ORM\Index(name: 'idx_tt_export_club_status', columns: ['club_id', 'status'])]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/time-and-travel-exports/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/time-and-travel-exports.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/time-and-travel-exports.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::TIME_TRAVEL_EXPORT->value."', request)",
      processor: TimeAndTravelExportProcessor::class,
    ),
    new Get(
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', object)",
    ),
    new Patch(
      security: "is_granted('".Permission::TIME_TRAVEL_EXPORT->value."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::TIME_TRAVEL_EXPORT->value."', object)",
      processor: TimeAndTravelExportDeleteProcessor::class,
    ),

    new Post(
      uriTemplate: '/clubs/{clubUuid}/time-and-travel-exports/{uuid}/regenerate',
      controller: TimeAndTravelExportRegenerate::class,
      security: "is_granted('".Permission::TIME_TRAVEL_EXPORT->value."', object)",
      deserialize: false,
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/time-and-travel-exports/{uuid}/lock',
      controller: TimeAndTravelExportLock::class,
      security: "is_granted('".Permission::TIME_TRAVEL_EXPORT->value."', object)",
      deserialize: false,
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/time-and-travel-exports/{uuid}/unlock',
      controller: TimeAndTravelExportUnlock::class,
      security: "is_granted('".Permission::TIME_TRAVEL_UNLOCK->value."', object)",
      deserialize: false,
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['time-and-travel-export', 'time-and-travel-export-read', 'common-read']
  ],
  denormalizationContext: [
    'groups' => ['time-and-travel-export-write']
  ],
  order: ['startDate' => 'DESC'],
  paginationClientEnabled: true,
)]
#[ApiFilter(OrderFilter::class, properties: ['startDate' => 'DESC', 'createdAt' => 'DESC'])]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact'])]
class TimeAndTravelExport extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(type: Types::STRING, enumType: TimeAndTravelExportStatus::class)]
  #[Groups(['time-and-travel-export-read'])]
  private TimeAndTravelExportStatus $status = TimeAndTravelExportStatus::draft;

  /**
   * True while a background job (triggered by a declaration change, or by locking) is
   * regenerating this draft's PDFs. Purely informational for the frontend to poll on —
   * see FEATURE_MERCURE.md for turning this into a push-based update later.
   */
  #[ORM\Column]
  #[Groups(['time-and-travel-export-read'])]
  private bool $isRegenerating = false;

  #[ORM\Column(type: Types::DATE_IMMUTABLE)]
  #[Groups(['time-and-travel-export', 'time-and-travel-export-write'])]
  #[Assert\NotNull]
  private ?\DateTimeImmutable $startDate = null;

  #[ORM\Column(type: Types::DATE_IMMUTABLE)]
  #[Groups(['time-and-travel-export', 'time-and-travel-export-write'])]
  #[Assert\NotNull]
  #[Assert\GreaterThanOrEqual(propertyPath: 'startDate')]
  private ?\DateTimeImmutable $endDate = null;

  /** Snapshot of ClubSetting::smicHourlyRate at generation time, so a later rate change never rewrites a locked export */
  #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
  #[Groups(['time-and-travel-export-read'])]
  private ?string $smicHourlyRate = null;

  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-export-read'])]
  #[ApiProperty(readableLink: false)]
  private ?Member $generatedBy = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  #[Groups(['time-and-travel-export-read'])]
  private ?\DateTimeImmutable $lockedAt = null;

  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-export-read'])]
  #[ApiProperty(readableLink: false)]
  private ?Member $lockedBy = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  #[Groups(['time-and-travel-export-read'])]
  private ?\DateTimeImmutable $unlockedAt = null;

  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-export-read'])]
  #[ApiProperty(readableLink: false)]
  private ?Member $unlockedBy = null;

  #[ORM\OneToOne(targetEntity: File::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-export-read'])]
  #[ApiProperty(readableLink: true)]
  private ?File $recapFile = null;

  /**
   * @var Collection<int, TimeAndTravelDeclaration>
   */
  #[ORM\OneToMany(targetEntity: TimeAndTravelDeclaration::class, mappedBy: 'export')]
  private Collection $declarations;

  /**
   * @var Collection<int, TimeAndTravelExportAttestation>
   */
  #[ORM\OneToMany(targetEntity: TimeAndTravelExportAttestation::class, mappedBy: 'export', cascade: ['persist', 'remove'], orphanRemoval: true)]
  #[Groups(['time-and-travel-export-read'])]
  private Collection $attestations;

  public function __construct() {
    parent::__construct();
    $this->declarations = new ArrayCollection();
    $this->attestations = new ArrayCollection();
  }

  public function getStatus(): TimeAndTravelExportStatus {
    return $this->status;
  }

  public function setStatus(TimeAndTravelExportStatus $status): static {
    $this->status = $status;
    return $this;
  }

  public function getIsRegenerating(): bool {
    return $this->isRegenerating;
  }

  public function setIsRegenerating(bool $isRegenerating): static {
    $this->isRegenerating = $isRegenerating;
    return $this;
  }

  public function getStartDate(): ?\DateTimeImmutable {
    return $this->startDate;
  }

  public function setStartDate(\DateTimeImmutable $startDate): static {
    $this->startDate = $startDate;
    return $this;
  }

  public function getEndDate(): ?\DateTimeImmutable {
    return $this->endDate;
  }

  public function setEndDate(\DateTimeImmutable $endDate): static {
    $this->endDate = $endDate;
    return $this;
  }

  public function getSmicHourlyRate(): ?string {
    return $this->smicHourlyRate;
  }

  public function setSmicHourlyRate(?string $smicHourlyRate): static {
    $this->smicHourlyRate = $smicHourlyRate;
    return $this;
  }

  public function getGeneratedBy(): ?Member {
    return $this->generatedBy;
  }

  public function setGeneratedBy(?Member $generatedBy): static {
    $this->generatedBy = $generatedBy;
    return $this;
  }

  public function getLockedAt(): ?\DateTimeImmutable {
    return $this->lockedAt;
  }

  public function setLockedAt(?\DateTimeImmutable $lockedAt): static {
    $this->lockedAt = $lockedAt;
    return $this;
  }

  public function getLockedBy(): ?Member {
    return $this->lockedBy;
  }

  public function setLockedBy(?Member $lockedBy): static {
    $this->lockedBy = $lockedBy;
    return $this;
  }

  public function getUnlockedAt(): ?\DateTimeImmutable {
    return $this->unlockedAt;
  }

  public function setUnlockedAt(?\DateTimeImmutable $unlockedAt): static {
    $this->unlockedAt = $unlockedAt;
    return $this;
  }

  public function getUnlockedBy(): ?Member {
    return $this->unlockedBy;
  }

  public function setUnlockedBy(?Member $unlockedBy): static {
    $this->unlockedBy = $unlockedBy;
    return $this;
  }

  public function getRecapFile(): ?File {
    return $this->recapFile;
  }

  public function setRecapFile(?File $recapFile): static {
    $this->recapFile = $recapFile;
    return $this;
  }

  /**
   * @return Collection<int, TimeAndTravelDeclaration>
   */
  public function getDeclarations(): Collection {
    return $this->declarations;
  }

  /**
   * @return Collection<int, TimeAndTravelExportAttestation>
   */
  public function getAttestations(): Collection {
    return $this->attestations;
  }

  public function addAttestation(TimeAndTravelExportAttestation $attestation): static {
    if (!$this->attestations->contains($attestation)) {
      $this->attestations->add($attestation);
      $attestation->setExport($this);
    }
    return $this;
  }

  #[Groups(['time-and-travel-export-read'])]
  public function getDeclarationCount(): int {
    return $this->declarations->count();
  }

  #[Groups(['time-and-travel-export-read'])]
  public function getMemberCount(): int {
    return $this->attestations->count();
  }

  #[Groups(['time-and-travel-export-read'])]
  public function getTotalAmount(): float {
    $total = 0.0;
    foreach ($this->attestations as $attestation) {
      $total += $attestation->getTotalAmount();
    }
    return $total;
  }

  #[Groups(['time-and-travel-export-read'])]
  public function getTotalKilometers(): int {
    $total = 0;
    foreach ($this->attestations as $attestation) {
      $total += $attestation->getTotalKilometers();
    }
    return $total;
  }
}
