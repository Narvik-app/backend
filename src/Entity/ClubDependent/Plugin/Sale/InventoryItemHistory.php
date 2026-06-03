<?php

namespace App\Entity\ClubDependent\Plugin\Sale;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\TimestampTrait;
use App\Enum\ClubRole;
use App\Repository\ClubDependent\Plugin\Sale\InventoryItemHistoryRepository;
use App\State\InventoryItemHistoryProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: InventoryItemHistoryRepository::class)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/inventory-items/{itemUuid}/histories.{_format}',
  operations: [
    new GetCollection(
      security: "is_granted('".ClubRole::supervisor->value."', request)",
    ),
  ],
  uriVariables: [
    'itemUuid' => new Link(toProperty: 'item', fromClass: InventoryItem::class),
    'clubUuid' => new Link(toClass: Club::class),
  ],
  normalizationContext: [
    'groups' => ['inventory-item-history', 'inventory-item-history-read']
  ],
  order: ['createdAt' => 'DESC'],
  provider: InventoryItemHistoryProvider::class,
)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/inventory-items/{itemUuid}/histories-per-day.{_format}',
  operations: [
    new GetCollection(
      security: "is_granted('".ClubRole::supervisor->value."', request)",
    ),
  ],
  uriVariables: [
    'itemUuid' => new Link(toProperty: 'item', fromClass: InventoryItem::class),
    'clubUuid' => new Link(toClass: Club::class),
  ],
  normalizationContext: [
    'groups' => ['common-read', 'timestamp', 'inventory-item-history-read']
  ],
  paginationEnabled: true,
  paginationItemsPerPage: 30,
  paginationClientItemsPerPage: true,
  paginationClientEnabled: true,
  provider: InventoryItemHistoryProvider::class,
)]
#[QueryParameter(key: 'start', schema: ['type' => 'string', 'format' => 'date'], required: false)]
#[QueryParameter(key: 'end', schema: ['type' => 'string', 'format' => 'date'], required: false)]
#[ApiFilter(OrderFilter::class, properties: ['createdAt' => 'DESC'])]
class InventoryItemHistory extends UuidEntity implements TimestampEntityInterface {
  use TimestampTrait;

  #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
  #[Groups(['inventory-item-history-read'])]
  private ?string $sellingPrice = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
  #[Groups(['inventory-item-history-read'])]
  private ?string $purchasePrice = null;

  #[ORM\Column(nullable: true)]
  #[Groups(['inventory-item-history-read'])]
  private ?int $quantity = null;


  #[ORM\ManyToOne]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  private ?InventoryItem $item = null;

  #[ORM\ManyToOne]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  private ?Sale $sale = null;

  public function getSellingPrice(): ?string {
    return $this->sellingPrice;
  }

  public function setSellingPrice(?string $sellingPrice): static {
    $this->sellingPrice = $sellingPrice;
    return $this;
  }

  public function getPurchasePrice(): ?string {
    return $this->purchasePrice;
  }

  public function setPurchasePrice(?string $purchasePrice): static {
    $this->purchasePrice = $purchasePrice;
    return $this;
  }

  public function getQuantity(): ?int {
    return $this->quantity;
  }

  public function setQuantity(?int $quantity): static {
    $this->quantity = $quantity;
    return $this;
  }

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
}
