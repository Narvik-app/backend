<?php

namespace App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
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
use App\Enum\ClubRole;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleRepository;
use App\Security\Voter\SelfMemberVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MemberVehicleRepository::class)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/member-vehicles/{uuid}',
  operations: [
    new Get(
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".SelfMemberVoter::READ."', object)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/member-vehicles.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::admin->value."', request)",
      read: false
    ),
    new Patch(
      security: "is_granted('".ClubRole::admin->value."', request)",
    ),
    new Delete(
      security: "is_granted('".ClubRole::admin->value."', request)",
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
#[ApiFilter(OrderFilter::class, properties: ['brand' => 'ASC', 'model' => 'ASC', 'createdAt' => 'DESC'])]
#[ApiFilter(SearchFilter::class, properties: ['member.uuid' => 'exact'])]
class MemberVehicle extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['member-vehicle'])]
  #[Assert\NotNull]
  private ?Member $member = null;

  #[ORM\Column(length: 255)]
  #[Groups(['member-vehicle'])]
  #[Assert\NotBlank]
  private ?string $brand = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['member-vehicle'])]
  private ?string $model = null;

  #[ORM\Column(length: 20)]
  #[Groups(['member-vehicle'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 20)]
  private ?string $licensePlate = null;

  #[ORM\Column(type: "string", enumType: VehicleEngineType::class)]
  #[Groups(['member-vehicle'])]
  #[Assert\NotNull]
  private VehicleEngineType $engineType = VehicleEngineType::PETROL;

  #[ORM\Column(type: Types::INTEGER)]
  #[Groups(['member-vehicle'])]
  #[Assert\NotNull]
  #[Assert\Positive]
  private ?int $fiscalPower = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 4)]
  #[Groups(['member-vehicle'])]
  #[Assert\NotNull]
  #[Assert\Positive]
  private ?string $fiscalCoefficient = null;

  #[ORM\Column]
  #[Groups(['member-vehicle'])]
  private bool $isEnabled = true;

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

  public function getFiscalPower(): ?int {
    return $this->fiscalPower;
  }

  public function setFiscalPower(int $fiscalPower): static {
    $this->fiscalPower = $fiscalPower;
    return $this;
  }

  public function getFiscalCoefficient(): ?string {
    return $this->fiscalCoefficient;
  }

  public function setFiscalCoefficient(string $fiscalCoefficient): static {
    $this->fiscalCoefficient = $fiscalCoefficient;
    return $this;
  }

  public function etIsEnabled(): bool {
    return $this->isEnabled;
  }

  public function setIsEnabled(bool $isEnabled): static {
    $this->isEnabled = $isEnabled;
    return $this;
  }

  /**
   * Calculate the fiscal reduction amount based on kilometers driven
   */
  public function calculateFiscalReduction(int $kilometers): float {
    return (float) $kilometers * (float) $this->fiscalCoefficient;
  }
}
