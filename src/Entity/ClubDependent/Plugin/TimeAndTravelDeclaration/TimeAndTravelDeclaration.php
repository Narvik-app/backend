<?php

namespace App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
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
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\Permission;
use App\Enum\TimeAndTravelExportStatus;
use App\Filter\ClubDependent\CurrentSeasonFilter;
use App\Filter\ClubDependent\PreviousSeasonFilter;
use App\Filter\MultipleFilter;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationRepository;
use App\Security\Voter\ClubBadgerVoter;
use App\Security\Voter\SelfMemberVoter;
use App\Security\Voter\TimeAndTravelSelfVoter;
use App\State\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationProcessor;
use App\State\Plugin\TimeAndTravelDeclaration\TimeAndTravelSummaryProvider;
use App\Validator\Constraints\Plugin\TimeAndTravelDeclaration\DateNotAlreadyExported;
use App\Validator\Constraints\Plugin\TimeAndTravelDeclaration\DistanceFieldsRequiredWithKilometers;
use App\Validator\Constraints\Plugin\TimeAndTravelDeclaration\NotLocked;
use App\Validator\Constraints\Plugin\TimeAndTravelDeclaration\RequiresKilometersOrHours;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TimeAndTravelDeclarationRepository::class)]
#[ORM\Index(name: 'idx_tt_declaration_club_date', columns: ['club_id', 'date'])]
#[ORM\Index(name: 'idx_tt_declaration_member_date', columns: ['member_id', 'date'])]
#[NotLocked]
#[RequiresKilometersOrHours]
#[DistanceFieldsRequiredWithKilometers]
#[DateNotAlreadyExported]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/time-and-travel-declarations/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/time-and-travel-declarations.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', request) || is_granted('".ClubBadgerVoter::IS_CLUB_BADGER."', request)",
    ),
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/time-and-travel-declarations/-/summary-per-member.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      provider: TimeAndTravelSummaryProvider::class,
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', request)",
      paginationClientEnabled: true,
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/time-and-travel-declarations.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      securityPostDenormalize: "is_granted('".Permission::TIME_TRAVEL_EDIT->value."', request) || is_granted('".ClubBadgerVoter::IS_CLUB_BADGER."', request) || is_granted('".TimeAndTravelSelfVoter::SELF_WRITE."', object)",
      read: false
    ),
    new Get(
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', object) || is_granted('".ClubBadgerVoter::IS_CLUB_BADGER."', object) || is_granted('".TimeAndTravelSelfVoter::SELF_READ."', object)",
    ),
    new Patch(
      security: "is_granted('".Permission::TIME_TRAVEL_EDIT->value."', object) || is_granted('".ClubBadgerVoter::IS_CLUB_BADGER."', object) || is_granted('".TimeAndTravelSelfVoter::SELF_WRITE."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::TIME_TRAVEL_EDIT->value."', object) || is_granted('".ClubBadgerVoter::IS_CLUB_BADGER."', object) || is_granted('".TimeAndTravelSelfVoter::SELF_WRITE."', object)",
      processor: TimeAndTravelDeclarationProcessor::class,
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['time-and-travel-declaration', 'time-and-travel-declaration-read']
  ],
  denormalizationContext: [
    'groups' => ['time-and-travel-declaration', 'time-and-travel-declaration-write']
  ],
  order: ['date' => 'DESC'],
  paginationClientEnabled: true,
)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/time-and-travel-declarations.{_format}',
  operations: [
    new GetCollection(
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', request) || is_granted('".SelfMemberVoter::READ."', request)",
    ),
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/time-and-travel-declarations/-/summary.{_format}',
      provider: TimeAndTravelSummaryProvider::class,
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', request) || is_granted('".SelfMemberVoter::READ."', request)",
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
  ],
  normalizationContext: [
    'groups' => ['time-and-travel-declaration', 'time-and-travel-declaration-read']
  ],
  paginationClientEnabled: true,
)]
#[ApiFilter(DateFilter::class, properties: ['date' => DateFilter::EXCLUDE_NULL])]
#[ApiFilter(OrderFilter::class, properties: ['date' => 'DESC', 'createdAt' => 'DESC'])]
#[ApiFilter(SearchFilter::class, properties: ['member.uuid' => 'exact', 'export.uuid' => 'exact'])]
#[ApiFilter(MultipleFilter::class, properties: ['member.firstname', 'member.lastname', 'member.licence'])]
#[ApiFilter(ExistsFilter::class, properties: ['export'])]
#[ApiFilter(CurrentSeasonFilter::class, properties: ['date'])]
#[ApiFilter(PreviousSeasonFilter::class, properties: ['date'])]
class TimeAndTravelDeclaration extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-declaration'])]
  #[ApiProperty(readableLink: true)]
  #[Assert\NotNull]
  private ?Member $member = null;

  #[ORM\Column(type: Types::DATE_IMMUTABLE)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\NotNull]
  private ?\DateTimeImmutable $date = null;

  /** Kept short so a trajet still fits on one line in the PDF/CSV exports. */
  public const int LOCATION_MAX_LENGTH = 30;

  /** Kept longer than the location fields since it is free-form but still bounded for the exports. */
  public const int DESCRIPTION_MAX_LENGTH = 50;

  /** Only relevant (and required, see DistanceFieldsRequiredWithKilometers) when kilometers is declared */
  #[ORM\Column(length: self::LOCATION_MAX_LENGTH, nullable: true)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\Length(max: self::LOCATION_MAX_LENGTH)]
  private ?string $departureLocation = null;

  #[ORM\Column(length: self::LOCATION_MAX_LENGTH, nullable: true)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\Length(max: self::LOCATION_MAX_LENGTH)]
  private ?string $arrivalLocation = null;

  #[ORM\Column(type: Types::INTEGER, nullable: true)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\Positive]
  #[Assert\LessThanOrEqual(value: 10000, message: 'Maximum 10000 kilometers per declaration')]
  private ?int $kilometers = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2, nullable: true)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\Positive]
  #[Assert\LessThanOrEqual(value: 24, message: 'Maximum 24 hours per declaration')]
  private ?string $hours = null;

  #[ORM\Column(length: self::DESCRIPTION_MAX_LENGTH)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: self::DESCRIPTION_MAX_LENGTH)]
  private ?string $description = null;

  #[ORM\Column]
  #[Groups(['time-and-travel-declaration'])]
  private bool $isRoundtrip = true;

  /** Required when kilometers is declared (drives the travel amount calculation via its fiscalCoefficient); an hours-only declaration needs no vehicle */
  #[ORM\ManyToOne(targetEntity: MemberVehicle::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-declaration'])]
  #[ApiProperty(readableLink: true)]
  private ?MemberVehicle $memberVehicle = null;

  /** Set only once the declaration is attached to an export (comptable side); never client-writable */
  #[ORM\ManyToOne(targetEntity: TimeAndTravelExport::class, inversedBy: 'declarations')]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-declaration-read'])]
  #[ApiProperty(readableLink: false)]
  private ?TimeAndTravelExport $export = null;

  /** Hydrated by TimeAndTravelDeclarationSubscriber::postLoad() — not persisted. */
  #[Groups(['time-and-travel-declaration-read'])]
  private ?float $travelAmount = null;

  #[Groups(['time-and-travel-declaration-read'])]
  private ?float $timeAmount = null;

  #[Groups(['time-and-travel-declaration-read'])]
  private ?float $totalAmount = null;

  public function __construct() {
    parent::__construct();
    $this->date = new \DateTimeImmutable();
  }

  public function getMember(): ?Member {
    return $this->member;
  }

  public function setMember(?Member $member): static {
    $this->member = $member;
    if ($member) {
      $this->club = $member->getClub();
    }

    return $this;
  }

  public function getDate(): ?\DateTimeImmutable {
    return $this->date;
  }

  public function setDate(\DateTimeImmutable $date): static {
    $this->date = $date;
    return $this;
  }

  public function getDepartureLocation(): ?string {
    return $this->departureLocation;
  }

  public function setDepartureLocation(?string $departureLocation): static {
    $this->departureLocation = $departureLocation;
    return $this;
  }

  public function getArrivalLocation(): ?string {
    return $this->arrivalLocation;
  }

  public function setArrivalLocation(?string $arrivalLocation): static {
    $this->arrivalLocation = $arrivalLocation;
    return $this;
  }

  public function getKilometers(): ?int {
    return $this->kilometers;
  }

  public function setKilometers(?int $kilometers): static {
    $this->kilometers = $kilometers;
    return $this;
  }

  public function getHours(): ?string {
    return $this->hours;
  }

  public function setHours(?string $hours): static {
    $this->hours = $hours;
    return $this;
  }

  /** Half-hour granularity avoids ambiguous entries like "1.3" (meant as 1h30, actually 1.3h). */
  #[Assert\Callback]
  public function validateHoursGranularity(ExecutionContextInterface $context): void {
    if ($this->hours === null) {
      return;
    }

    $doubled = (float) $this->hours * 2;
    if (abs($doubled - round($doubled)) > 0.001) {
      $context->buildViolation('Hours must be a multiple of 0.5 (e.g. 1, 1.5, 2).')
        ->atPath('hours')
        ->addViolation();
    }
  }

  public function getDescription(): ?string {
    return $this->description;
  }

  public function setDescription(string $description): static {
    $this->description = $description;
    return $this;
  }

  public function getIsRoundtrip(): bool {
    return $this->isRoundtrip;
  }

  public function setIsRoundtrip(bool $isRoundtrip): static {
    $this->isRoundtrip = $isRoundtrip;
    return $this;
  }

  public function getMemberVehicle(): ?MemberVehicle {
    return $this->memberVehicle;
  }

  public function setMemberVehicle(?MemberVehicle $memberVehicle): static {
    $this->memberVehicle = $memberVehicle;
    return $this;
  }

  public function getExport(): ?TimeAndTravelExport {
    return $this->export;
  }

  public function setExport(?TimeAndTravelExport $export): static {
    $this->export = $export;
    return $this;
  }

  #[Groups(['time-and-travel-declaration-read'])]
  public function getIsLocked(): bool {
    return $this->export !== null && $this->export->getStatus() === TimeAndTravelExportStatus::locked;
  }

  /**
   * A per-declaration ESTIMATE only, as if this declaration's own kilometers were the vehicle's
   * whole cumulative distance for the year. The real, authoritative amount can only be known once
   * every declaration on that vehicle for the export period is known — it's computed once per
   * vehicle over its actual cumulative distance by TimeAndTravelExportGenerationService, not
   * summed from this per-declaration figure. Set by TimeAndTravelDeclarationSubscriber::postLoad().
   */
  public function setTravelAmount(?float $travelAmount): static {
    $this->travelAmount = $travelAmount;
    return $this;
  }

  public function getTravelAmount(): float {
    return $this->travelAmount ?? 0.0;
  }

  /**
   * Set by TimeAndTravelDeclarationSubscriber::postLoad(), which knows the club's SMIC rate.
   */
  public function setTimeAmount(?float $timeAmount): static {
    $this->timeAmount = $timeAmount;
    return $this;
  }

  public function getTimeAmount(): float {
    return $this->timeAmount ?? 0.0;
  }

  public function setTotalAmount(?float $totalAmount): static {
    $this->totalAmount = $totalAmount;
    return $this;
  }

  public function getTotalAmount(): float {
    return $this->totalAmount ?? $this->getTravelAmount();
  }
}
