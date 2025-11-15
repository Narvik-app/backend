<?php

namespace App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
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
use App\Filter\ClubDependent\CurrentSeasonFilter;
use App\Filter\ClubDependent\PreviousSeasonFilter;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationRepository;
use App\Security\Voter\TimeAndTravelDeclarationVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TimeAndTravelDeclarationRepository::class)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/time-and-travel-declarations/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/time-and-travel-declarations.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::admin->value."', request)",
    ),
    new Get(
      security: "is_granted('".ClubRole::admin->value."', request) || is_granted('".TimeAndTravelDeclarationVoter::READ."', object)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/time-and-travel-declarations.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      securityPostDenormalize: "is_granted('".ClubRole::admin->value."', request) || is_granted('".TimeAndTravelDeclarationVoter::CREATE."', request)",
      read: false
    ),
    new Patch(
      security: "is_granted('".ClubRole::admin->value."', object) || is_granted('".TimeAndTravelDeclarationVoter::UPDATE."', object)",
    ),
    new Delete(
      security: "is_granted('".ClubRole::admin->value."', object) || is_granted('".TimeAndTravelDeclarationVoter::DELETE."', object)",
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
)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/time-and-travel-declarations.{_format}',
  operations: [
    new GetCollection(
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".TimeAndTravelDeclarationVoter::READ."', request)",
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
  ],
  normalizationContext: [
    'groups' => ['time-and-travel-declaration', 'time-and-travel-declaration-read']
  ],
)]
#[ApiFilter(DateFilter::class, properties: ['date' => DateFilter::EXCLUDE_NULL])]
#[ApiFilter(OrderFilter::class, properties: ['date' => 'DESC', 'createdAt' => 'DESC'])]
#[ApiFilter(SearchFilter::class, properties: ['member.uuid' => 'exact'])]
#[ApiFilter(CurrentSeasonFilter::class, properties: ['date'])]
#[ApiFilter(PreviousSeasonFilter::class, properties: ['date'])]
class TimeAndTravelDeclaration extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\NotNull]
  private ?Member $member = null;

  #[ORM\Column(type: Types::DATE_MUTABLE)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\NotNull]
  private ?\DateTimeInterface $date = null;

  #[ORM\Column(length: 255)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 255)]
  private ?string $departureLocation = null;

  #[ORM\Column(length: 255)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 255)]
  private ?string $arrivalLocation = null;

  #[ORM\Column(type: Types::INTEGER)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\NotNull]
  #[Assert\Positive]
  #[Assert\LessThanOrEqual(value: 10000, message: 'Maximum 10000 kilometers per declaration')]
  private ?int $kilometers = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\NotNull]
  #[Assert\Positive]
  private string $hours = '4.00';

  #[ORM\Column(length: 60)]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 60)]
  private ?string $description = null;

  #[ORM\Column]
  #[Groups(['time-and-travel-declaration'])]
  private bool $isRoundtrip = true;

  #[ORM\ManyToOne(targetEntity: MemberVehicle::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['time-and-travel-declaration'])]
  #[Assert\NotNull]
  private ?MemberVehicle $memberVehicle = null;

  #[Groups(['time-and-travel-declaration-read'])]
  private float $fiscalReduction = 0.0;

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

  public function getDate(): ?\DateTimeInterface {
    return $this->date;
  }

  public function setDate(\DateTimeInterface $date): static {
    $this->date = $date;
    return $this;
  }

  public function getDepartureLocation(): ?string {
    return $this->departureLocation;
  }

  public function setDepartureLocation(string $departureLocation): static {
    $this->departureLocation = $departureLocation;
    return $this;
  }

  public function getArrivalLocation(): ?string {
    return $this->arrivalLocation;
  }

  public function setArrivalLocation(string $arrivalLocation): static {
    $this->arrivalLocation = $arrivalLocation;
    return $this;
  }

  public function getKilometers(): ?int {
    return $this->kilometers;
  }

  public function setKilometers(int $kilometers): static {
    $this->kilometers = $kilometers;
    return $this;
  }

  public function getHours(): ?string {
    return $this->hours;
  }

  public function setHours(string $hours): static {
    $this->hours = $hours;
    return $this;
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

  public function getFiscalReduction(): float {
    if (!$this->memberVehicle || !$this->kilometers) {
      return 0.0;
    }

    return $this->memberVehicle->calculateFiscalReduction($this->kilometers);
  }
}
