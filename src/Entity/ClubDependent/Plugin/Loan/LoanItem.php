<?php

namespace App\Entity\ClubDependent\Plugin\Loan;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
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
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Controller\ClubDependent\Plugin\Loan\LoanItemImageUpdate;
use App\Controller\ClubDependent\Plugin\Loan\LoanItemMove;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\File;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\SortableEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\LoanItemStatus;
use App\Enum\Permission;
use App\Repository\ClubDependent\Plugin\Loan\LoanItemRepository;
use App\State\LoanItemUsageProvider;
use App\Service\UtilsService;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanItemRepository::class)]
#[UniqueEntity(fields: ['name', 'category', 'club'], message: 'An item with the same name is already defined')]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/loan-items/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/loan-items.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::LOAN_ITEMS_ACCESS->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/loan-items.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::LOAN_ITEMS_EDIT->value."', request)",
      read: false
    ),

    new Get(
      security: "is_granted('".Permission::LOAN_ITEMS_ACCESS->value."', object)",
    ),
    new Patch(
      security: "is_granted('".Permission::LOAN_ITEMS_EDIT->value."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::LOAN_ITEMS_EDIT->value."', object)",
    ),

    new Put(
      uriTemplate: '/clubs/{clubUuid}/loan-items/{uuid}/move',
      controller: LoanItemMove::class,
      openapi: new Model\Operation(
        description: 'Move `up` or `down` a loan item',
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'direction' => ['type' => 'string'],
                ]
              ]
            ]
          ])
        )
      ),
      security: "is_granted('".Permission::LOAN_ITEMS_EDIT->value."', object)",
    ),

    // Image upload
    new Post(
      uriTemplate: '/clubs/{clubUuid}/loan-items/{uuid}/image',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'uuid' => new Link(fromClass: self::class),
      ],
      controller: LoanItemImageUpdate::class,
      openapi: new Model\Operation(
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'multipart/form-data' => [
              'schema' => ['type' => 'object', 'properties' => [
                'file' => ['type' => 'string', 'format' => 'binary']
              ]]
            ]
          ])
        )
      ),
      security: "is_granted('".Permission::LOAN_ITEMS_EDIT->value."', object)",
      read: true,
      deserialize: false,
      write: false,
    ),

    // Per-day usage aggregation (for the chart)
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/loan-items/{itemUuid}/usage-per-day.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'itemUuid' => new Link(fromClass: self::class, identifiers: ['uuid']),
      ],
      provider: LoanItemUsageProvider::class,
      security: "is_granted('".Permission::LOAN_ITEMS_ACCESS->value."', request)",
      paginationClientEnabled: true,
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['loan-item', 'loan-item-read']
  ],
  denormalizationContext: [
    'groups' => ['loan-item', 'loan-item-write']
  ],
  order: ['category.weight' => 'ASC', 'weight' => 'ASC', 'name' => 'ASC'],
  paginationClientEnabled: true,
)]
#[ApiFilter(OrderFilter::class, properties: ['name' => 'ASC', 'category.name' => 'ASC', 'category.weight' => 'ASC', 'weight' => 'ASC'])]
#[ApiFilter(SearchFilter::class, properties: ['category.uuid' => 'exact'])]
#[ApiFilter(BooleanFilter::class, properties: ['visibleOnSalePage'])]
class LoanItem extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface, SortableEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(length: 255)]
  #[Groups(['loan-item'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  #[ORM\Column(length: 1024, nullable: true)]
  #[Groups(['loan-item'])]
  private ?string $description = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
  #[Groups(['loan-item'])]
  private ?string $loanPrice = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
  #[Groups(['loan-item'])]
  private ?string $purchasePrice = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
  #[Groups(['loan-item'])]
  private ?string $soldPrice = null;

  #[ORM\Column(length: 50, enumType: LoanItemStatus::class)]
  #[Groups(['loan-item'])]
  private LoanItemStatus $status = LoanItemStatus::available;

  #[ORM\Column(nullable: true)]
  #[Groups(['loan-item'])]
  private ?int $weight = null;

  #[ORM\ManyToOne(targetEntity: LoanCategory::class, inversedBy: 'items')]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['loan-item'])]
  private ?LoanCategory $category = null;

  #[ORM\Column(options: ['default' => true])]
  #[Groups(['loan-item'])]
  private bool $visibleOnSalePage = true;

  #[ORM\OneToOne(targetEntity: File::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['loan-item-read'])]
  private ?File $image = null;

  public function getName(): ?string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = ucfirst($name);
    return $this;
  }

  public function getDescription(): ?string {
    return $this->description;
  }

  public function setDescription(?string $description): static {
    $this->description = $description;
    return $this;
  }

  public function getLoanPrice(): ?string {
    return $this->loanPrice;
  }

  public function setLoanPrice(?string $loanPrice): static {
    $this->loanPrice = UtilsService::convertStringToDbDecimal($loanPrice);
    return $this;
  }

  public function getPurchasePrice(): ?string {
    return $this->purchasePrice;
  }

  public function setPurchasePrice(?string $purchasePrice): static {
    $this->purchasePrice = UtilsService::convertStringToDbDecimal($purchasePrice);
    return $this;
  }

  public function getSoldPrice(): ?string {
    return $this->soldPrice;
  }

  public function setSoldPrice(?string $soldPrice): static {
    $this->soldPrice = UtilsService::convertStringToDbDecimal($soldPrice);
    return $this;
  }

  public function getStatus(): LoanItemStatus {
    return $this->status;
  }

  public function setStatus(LoanItemStatus $status): static {
    $this->status = $status;
    return $this;
  }

  public function getWeight(): ?int {
    return $this->weight;
  }

  public function setWeight(int $weight): static {
    $this->weight = $weight;
    return $this;
  }

  public function getCategory(): ?LoanCategory {
    return $this->category;
  }

  public function setCategory(?LoanCategory $category): static {
    $this->category = $category;
    return $this;
  }

  public function isVisibleOnSalePage(): bool {
    return $this->visibleOnSalePage;
  }

  public function setVisibleOnSalePage(bool $visibleOnSalePage): static {
    $this->visibleOnSalePage = $visibleOnSalePage;
    return $this;
  }

  public function getImage(): ?File {
    return $this->image;
  }

  public function setImage(?File $image): static {
    $this->image = $image;
    return $this;
  }
}
