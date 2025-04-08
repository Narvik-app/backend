<?php

namespace App\Entity\ClubDependent\Plugin\Sale;

use App\Entity\Abstract\UuidEntity;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ClubDependent\Plugin\Sale\SalePurchasedItemRepository;
use App\Service\UtilsService;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SalePurchasedItemRepository::class)]
class SalePurchasedItem extends UuidEntity implements TimestampEntityInterface {
  use TimestampTrait;

  #[ORM\ManyToOne(targetEntity: InventoryItem::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['sale'])]
  #[Assert\NotBlank(allowNull: true)]
  private ?InventoryItem $item = null;

  #[ORM\ManyToOne(targetEntity: Sale::class, inversedBy: 'salePurchasedItems')]
  #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
  #[Assert\NotNull]
  private ?Sale $sale = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['sale'])]
  #[Assert\NotBlank(allowNull: true)]
  private ?string $itemName = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['sale'])]
  #[Assert\NotBlank(allowNull: true)]
  private ?string $itemCategory = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
  #[Groups(['sale'])]
  #[Assert\NotBlank(allowNull: true)]
  private ?string $itemPrice = null;

  #[ORM\Column]
  #[Groups(['sale'])]
  #[Assert\GreaterThanOrEqual(value: 1)]
  #[Assert\NotBlank]
  private int $quantity = 1;

  public function getItem(): ?InventoryItem {
    return $this->item;
  }

  public function setItem(?InventoryItem $item): static {
    $this->item = $item;
    return $this;
  }

  public function getSale(): ?Sale {
    return $this->sale;
  }

  public function setSale(?Sale $sale): static {
    $this->sale = $sale;
    return $this;
  }

  public function getItemName(): ?string {
    return $this->itemName;
  }

  public function setItemName(string $itemName): static {
    $this->itemName = $itemName;
    return $this;
  }

  public function getItemCategory(): ?string {
    return $this->itemCategory;
  }

  public function setItemCategory(?string $itemCategory): static {
    $this->itemCategory = $itemCategory;
    return $this;
  }

  public function getItemPrice(): ?string {
    return $this->itemPrice;
  }

  public function setItemPrice(?string $itemPrice): static {
    $this->itemPrice = UtilsService::convertStringToDbDecimal($itemPrice);
    return $this;
  }

  public function getQuantity(): int {
    return $this->quantity;
  }

  public function setQuantity(int $quantity): static {
    $this->quantity = $quantity;
    return $this;
  }
}
