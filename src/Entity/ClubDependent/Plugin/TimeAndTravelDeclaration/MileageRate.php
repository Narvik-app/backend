<?php

namespace App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\UserRole;
use App\Enum\VehicleCategory;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\MileageRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One cell of the official French kilometric mileage scale ("barème kilométrique", Arrêté du 27
 * mars 2023): for a vehicle category and fiscal power range, the formula `distance * rate + addend`
 * to apply once the volunteer's cumulative distance for the period falls at or under maxKm
 * (brackets are evaluated in tierOrder, and the whole distance uses whichever single bracket it
 * falls into — this is not a marginal/progressive scale).
 *
 * Global reference data (not per-club) so every club always calculates against the same,
 * up-to-date national schedule — see https://www.service-public.gouv.fr/particuliers/vosdroits/F1132
 */
#[ORM\Entity(repositoryClass: MileageRateRepository::class)]
#[ApiResource(
  operations: [
    new GetCollection(),
    new Get(),
    new Post(security: "is_granted('".UserRole::super_admin->value."')"),
    new Patch(security: "is_granted('".UserRole::super_admin->value."')"),
    new Delete(security: "is_granted('".UserRole::super_admin->value."')"),
  ],
  normalizationContext: [
    'groups' => ['mileage-rate']
  ],
  denormalizationContext: [
    'groups' => ['mileage-rate']
  ],
  order: ['category' => 'ASC', 'minFiscalPower' => 'ASC', 'tierOrder' => 'ASC'],
)]
class MileageRate {
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
  #[ORM\Column]
  #[Groups(['mileage-rate'])]
  private ?int $id = null;

  #[ORM\Column(type: Types::STRING, enumType: VehicleCategory::class)]
  #[Groups(['mileage-rate'])]
  #[Assert\NotNull]
  private VehicleCategory $category = VehicleCategory::car;

  /** Null means no lower bound (mopeds have a single, power-less bracket set). */
  #[ORM\Column(type: Types::INTEGER, nullable: true)]
  #[Groups(['mileage-rate'])]
  private ?int $minFiscalPower = null;

  /** Null means unbounded ("7 CV et plus"). */
  #[ORM\Column(type: Types::INTEGER, nullable: true)]
  #[Groups(['mileage-rate'])]
  private ?int $maxFiscalPower = null;

  /** 1, 2 or 3 — which of the (up to three) distance brackets this row is, for display ordering. */
  #[ORM\Column(type: Types::INTEGER)]
  #[Groups(['mileage-rate'])]
  #[Assert\Positive]
  private int $tierOrder = 1;

  /** Null means unbounded ("over 20 000 km") — the last tier of its power group. */
  #[ORM\Column(type: Types::INTEGER, nullable: true)]
  #[Groups(['mileage-rate'])]
  private ?int $tierMaxKm = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 4)]
  #[Groups(['mileage-rate'])]
  #[Assert\NotNull]
  #[Assert\PositiveOrZero]
  private ?string $rate = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
  #[Groups(['mileage-rate'])]
  #[Assert\NotNull]
  #[Assert\PositiveOrZero]
  private string $addend = '0.00';

  public function getId(): ?int {
    return $this->id;
  }

  public function getCategory(): VehicleCategory {
    return $this->category;
  }

  public function setCategory(VehicleCategory $category): static {
    $this->category = $category;
    return $this;
  }

  public function getMinFiscalPower(): ?int {
    return $this->minFiscalPower;
  }

  public function setMinFiscalPower(?int $minFiscalPower): static {
    $this->minFiscalPower = $minFiscalPower;
    return $this;
  }

  public function getMaxFiscalPower(): ?int {
    return $this->maxFiscalPower;
  }

  public function setMaxFiscalPower(?int $maxFiscalPower): static {
    $this->maxFiscalPower = $maxFiscalPower;
    return $this;
  }

  public function getTierOrder(): int {
    return $this->tierOrder;
  }

  public function setTierOrder(int $tierOrder): static {
    $this->tierOrder = $tierOrder;
    return $this;
  }

  public function getTierMaxKm(): ?int {
    return $this->tierMaxKm;
  }

  public function setTierMaxKm(?int $tierMaxKm): static {
    $this->tierMaxKm = $tierMaxKm;
    return $this;
  }

  public function getRate(): ?string {
    return $this->rate;
  }

  public function setRate(string $rate): static {
    $this->rate = $rate;
    return $this;
  }

  public function getAddend(): string {
    return $this->addend;
  }

  public function setAddend(string $addend): static {
    $this->addend = $addend;
    return $this;
  }

  public function matchesFiscalPower(int $fiscalPower): bool {
    if ($this->minFiscalPower !== null && $fiscalPower < $this->minFiscalPower) {
      return false;
    }
    if ($this->maxFiscalPower !== null && $fiscalPower > $this->maxFiscalPower) {
      return false;
    }
    return true;
  }

  public function calculateAmount(int $kilometers): float {
    return $kilometers * (float) $this->rate + (float) $this->addend;
  }
}
