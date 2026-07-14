<?php

namespace App\Entity\ClubDependent\Plugin\Sale;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentModeMove;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\SortableEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Enum\Permission;
use App\Enum\SalePaymentModeKind;
use App\Repository\ClubDependent\Plugin\Sale\SalePaymentModeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;

#[ORM\Entity(repositoryClass: SalePaymentModeRepository::class)]
#[UniqueEntity(fields: ['weight', 'club'], ignoreNull: true)]
#[UniqueEntity(fields: ['name', 'club'], ignoreNull: true)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/sale-payment-modes/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-modes.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::SALE_PAYMENT_MODES_ACCESS->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-modes.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::SALE_PAYMENT_MODES_EDIT->value."', request)",
      read: false
    ),
    new Get(
      security: "is_granted('".Permission::SALE_PAYMENT_MODES_ACCESS->value."', object)"
    ),
    new Patch(
      security: "is_granted('".Permission::SALE_PAYMENT_MODES_EDIT->value."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::SALE_PAYMENT_MODES_EDIT->value."', object)",
    ),

    new Put(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-modes/{uuid}/move',
      controller: SalePaymentModeMove::class,
      openapi: new Model\Operation(
        description: 'Move `up` or `down` a payment mode',
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
        ),
      ),
      security: "is_granted('".Permission::SALE_PAYMENT_MODES_EDIT->value."', object)",
    )
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['sale-payment-mode', 'sale-payment-mode-read']
  ],
  denormalizationContext: [
    'groups' => ['sale-payment-mode', 'sale-payment-mode-write']
  ],
  order: ['weight' => 'asc'],
)]
#[ApiFilter(OrderFilter::class, properties: ['weight' => 'ASC'])]
#[ApiFilter(BooleanFilter::class, properties: ['available'])]
class SalePaymentMode extends UuidEntity implements SortableEntityInterface, ClubLinkedEntityInterface {
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(length: 255)]
  #[Groups(['sale-payment-mode', 'sale-read'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  #[ORM\Column(length: 255)]
  #[Assert\NotBlank]
  #[Groups(['sale-payment-mode', 'sale-read'])]
  private ?string $icon = null;

  #[ORM\Column]
  #[Groups(['sale-payment-mode'])]
  #[Assert\NotNull]
  private ?bool $available = true;

  #[ORM\Column(nullable: true)]
  #[Groups(['sale-payment-mode'])]
  private ?int $weight = null;

  #[ORM\Column(type: Types::STRING, enumType: SalePaymentModeKind::class, options: ['default' => 'payment'])]
  #[Groups(['sale-payment-mode', 'sale-read'])]
  #[Assert\NotNull]
  private SalePaymentModeKind $kind = SalePaymentModeKind::payment;

  /** @var Collection<int, SalePaymentTerminal> */
  #[ORM\OneToMany(targetEntity: SalePaymentTerminal::class, mappedBy: 'paymentMode')]
  #[ApiProperty(readableLink: true)]
  #[Groups(['sale-payment-mode', 'sale-read'])]
  private Collection $paymentTerminals;

  public function __construct() {
    parent::__construct();
    $this->paymentTerminals = new ArrayCollection();
  }

  public function getName(): ?string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = ucfirst($name);
    return $this;
  }

  public function getIcon(): ?string {
    return $this->icon;
  }

  public function setIcon(string $icon): static {
    $this->icon = $icon;
    return $this;
  }

  public function isAvailable(): ?bool {
    return $this->available;
  }

  public function setAvailable(bool $available): static {
    $this->available = $available;
    return $this;
  }

  public function getWeight(): ?int {
    return $this->weight;
  }

  public function setWeight(?int $weight): static {
    $this->weight = $weight;
    return $this;
  }

  public function getKind(): SalePaymentModeKind {
    return $this->kind;
  }

  public function setKind(SalePaymentModeKind $kind): static {
    $this->kind = $kind;
    return $this;
  }

  /** @return Collection<int, SalePaymentTerminal> */
  public function getPaymentTerminals(): Collection {
    return $this->paymentTerminals;
  }
}
