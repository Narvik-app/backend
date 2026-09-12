<?php

namespace App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
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
use App\Enum\VehicleCategory;
use App\Enum\VehicleEngineType;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleRepository;
use App\Security\Voter\ClubBadgerVoter;
use App\Security\Voter\SelfMemberVoter;
use App\Security\Voter\TimeAndTravelSelfVoter;
use App\State\TimeAndTravelMemberVehicleProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MemberVehicleRepository::class)]
#[UniqueEntity(fields: ['member', 'licensePlate'], message: 'This vehicle is already registered for that member')]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/member-vehicles/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/member-vehicles.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', request) || is_granted('".ClubBadgerVoter::IS_CLUB_BADGER."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/member-vehicles.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      securityPostDenormalize: "is_granted('".Permission::TIME_TRAVEL_EDIT->value."', request) || is_granted('".TimeAndTravelSelfVoter::SELF_WRITE."', object)",
      read: false
    ),

    new Get(
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', object) || is_granted('".ClubBadgerVoter::IS_CLUB_BADGER."', object) || is_granted('".TimeAndTravelSelfVoter::SELF_READ."', object)",
    ),
    new Patch(
      security: "is_granted('".Permission::TIME_TRAVEL_EDIT->value."', object) || is_granted('".TimeAndTravelSelfVoter::SELF_WRITE."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::TIME_TRAVEL_EDIT->value."', object) || is_granted('".TimeAndTravelSelfVoter::SELF_WRITE."', object)",
      processor: TimeAndTravelMemberVehicleProcessor::class,
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['member-vehicle', 'member-vehicle-read']
  ],
  denormalizationContext: [
    'groups' => ['member-vehicle', 'member-vehicle-write']
  ],
  order: ['brand' => 'ASC', 'model' => 'ASC'],
)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/vehicles.{_format}',
  operations: [
    new GetCollection(
      security: "is_granted('".Permission::TIME_TRAVEL_ACCESS->value."', request) || is_granted('".ClubBadgerVoter::IS_CLUB_BADGER."', request) || is_granted('".SelfMemberVoter::READ."', request)",
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
  ],
  normalizationContext: [
    'groups' => ['member-vehicle', 'member-vehicle-read']
  ],
)]
#[ApiFilter(OrderFilter::class, properties: ['brand' => 'ASC', 'model' => 'ASC', 'createdAt' => 'DESC'])]
#[ApiFilter(SearchFilter::class, properties: ['member.uuid' => 'exact'])]
#[ApiFilter(BooleanFilter::class, properties: ['isEnabled'])]
class MemberVehicle extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['member-vehicle'])]
  #[ApiProperty(readableLink: true)]
  #[Assert\NotNull]
  private ?Member $member = null;

  #[ORM\Column(length: 255)]
  #[Groups(['member-vehicle', 'time-and-travel-declaration-read'])]
  #[Assert\NotBlank]
  private ?string $brand = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['member-vehicle', 'time-and-travel-declaration-read'])]
  private ?string $model = null;

  #[ORM\Column(length: 20)]
  #[Groups(['member-vehicle', 'time-and-travel-declaration-read'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 20)]
  private ?string $licensePlate = null;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, enumType: VehicleEngineType::class)]
  #[Groups(['member-vehicle', 'time-and-travel-declaration-read'])]
  #[Assert\NotNull]
  private VehicleEngineType $engineType = VehicleEngineType::petrol;

  /** Which official mileage scale table to look the vehicle up in. */
  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, enumType: VehicleCategory::class)]
  #[Groups(['member-vehicle', 'time-and-travel-declaration-read'])]
  #[Assert\NotNull]
  private VehicleCategory $category = VehicleCategory::car;

  #[ORM\Column(type: Types::INTEGER)]
  #[Groups(['member-vehicle', 'time-and-travel-declaration-read'])]
  #[Assert\NotNull]
  #[Assert\Positive]
  private ?int $fiscalPower = null;

  #[ORM\Column]
  #[Groups(['member-vehicle', 'time-and-travel-declaration-read'])]
  private bool $isEnabled = true;

  /**
   * Not persisted: hydrated by MemberVehicleSubscriber::postLoad() so a member can see, and
   * validate, the calculation that will actually apply to their declarations this calendar year.
   */
  #[Groups(['member-vehicle-read'])]
  private ?int $currentYearKilometers = null;

  #[Groups(['member-vehicle-read'])]
  private ?string $currentYearEstimatedAmount = null;

  #[Groups(['member-vehicle-read'])]
  private ?string $currentYearCalculationDescription = null;

  public function __construct() {
    parent::__construct();
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

  public function getBrand(): ?string {
    return $this->brand;
  }

  public function setBrand(string $brand): static {
    $this->brand = $brand;
    return $this;
  }

  public function getModel(): ?string {
    return $this->model;
  }

  public function setModel(?string $model): static {
    $this->model = $model;
    return $this;
  }

  public function getLicensePlate(): ?string {
    return $this->licensePlate;
  }

  public function setLicensePlate(string $licensePlate): static {
    $this->licensePlate = strtoupper(trim($licensePlate));
    return $this;
  }

  public function getEngineType(): VehicleEngineType {
    return $this->engineType;
  }

  public function setEngineType(VehicleEngineType $engineType): static {
    $this->engineType = $engineType;
    return $this;
  }

  public function getCategory(): VehicleCategory {
    return $this->category;
  }

  public function setCategory(VehicleCategory $category): static {
    $this->category = $category;
    return $this;
  }

  public function getFiscalPower(): ?int {
    return $this->fiscalPower;
  }

  public function setFiscalPower(int $fiscalPower): static {
    $this->fiscalPower = $fiscalPower;
    return $this;
  }

  public function isElectric(): bool {
    return $this->engineType === VehicleEngineType::electric;
  }

  public function getIsEnabled(): bool {
    return $this->isEnabled;
  }

  public function setIsEnabled(bool $isEnabled): static {
    $this->isEnabled = $isEnabled;
    return $this;
  }

  public function getCurrentYearKilometers(): ?int {
    return $this->currentYearKilometers;
  }

  public function setCurrentYearKilometers(?int $currentYearKilometers): static {
    $this->currentYearKilometers = $currentYearKilometers;
    return $this;
  }

  public function getCurrentYearEstimatedAmount(): ?string {
    return $this->currentYearEstimatedAmount;
  }

  public function setCurrentYearEstimatedAmount(?string $currentYearEstimatedAmount): static {
    $this->currentYearEstimatedAmount = $currentYearEstimatedAmount;
    return $this;
  }

  public function getCurrentYearCalculationDescription(): ?string {
    return $this->currentYearCalculationDescription;
  }

  public function setCurrentYearCalculationDescription(?string $currentYearCalculationDescription): static {
    $this->currentYearCalculationDescription = $currentYearCalculationDescription;
    return $this;
  }
}
