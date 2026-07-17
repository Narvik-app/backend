<?php

namespace App\Entity\ClubDependent\Plugin\Sale;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalCancelCheckout;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalCheckout;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalCheckoutStatus;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalDeviceStatus;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentMode;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Enum\Permission;
use App\Repository\ClubDependent\Plugin\Sale\SalePaymentTerminalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A physical payment terminal device (e.g. a SumUp reader), discovered under a
 * SalePaymentTerminalConnection via sync-devices. Local overrides (name, icon,
 * description, payment mode, available) live here; credentials live on the connection.
 */
#[ORM\Entity(repositoryClass: SalePaymentTerminalRepository::class)]
#[ORM\UniqueConstraint(name: 'sale_payment_terminal_connection_device_unique', fields: ['connection', 'externalDeviceId'])]
#[UniqueEntity(fields: ['name', 'club'], ignoreNull: true)]
#[UniqueEntity(fields: ['connection', 'externalDeviceId'], ignoreNull: true)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_ACCESS->value."', request)",
    ),
    new Get(
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_ACCESS->value."', object)",
    ),
    new Patch(
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_EDIT->value."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_EDIT->value."', object)",
    ),

    // Initiate a payment on this terminal
    new Post(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals/{uuid}/checkout',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'uuid' => new Link(fromClass: self::class),
      ],
      controller: SalePaymentTerminalCheckout::class,
      openapi: new Model\Operation(
        summary: 'Initiate a payment on this terminal',
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type' => 'object',
                'required' => ['amount'],
                'properties' => [
                  'amount' => ['type' => 'string', 'example' => '15.00'],
                  'description' => ['type' => 'string'],
                ],
              ],
            ],
          ]),
        ),
      ),
      security: "is_granted('".Permission::SALE_NEW->value."', request)",
      read: false,
    ),

    // Test connection: live status of the device
    new Get(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals/{uuid}/device-status',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'uuid' => new Link(fromClass: self::class),
      ],
      controller: SalePaymentTerminalDeviceStatus::class,
      openapi: new Model\Operation(
        summary: 'Get the live status of the device (test connection)',
      ),
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_ACCESS->value."', request)",
      read: false,
    ),

    // Abort a checkout the device is still waiting on (cashier cancelled in the UI)
    new Post(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals/{uuid}/cancel-checkout',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'uuid' => new Link(fromClass: self::class),
      ],
      controller: SalePaymentTerminalCancelCheckout::class,
      openapi: new Model\Operation(
        summary: 'Abort the checkout this device is currently waiting on',
      ),
      security: "is_granted('".Permission::SALE_NEW->value."', request)",
      read: false,
    ),

    // Poll the status of a pending checkout
    new Get(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals/{uuid}/checkout-status',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'uuid' => new Link(fromClass: self::class),
      ],
      controller: SalePaymentTerminalCheckoutStatus::class,
      openapi: new Model\Operation(
        summary: 'Get the status of a pending checkout',
        parameters: [
          new Model\Parameter(
            name: 'clientTransactionId',
            in: 'query',
            required: true,
            schema: ['type' => 'string'],
          ),
        ],
      ),
      security: "is_granted('".Permission::SALE_NEW->value."', request)",
      read: false,
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['sale-payment-terminal', 'sale-payment-terminal-read'],
  ],
  denormalizationContext: [
    'groups' => ['sale-payment-terminal', 'sale-payment-terminal-write'],
  ],
)]
class SalePaymentTerminal extends UuidEntity implements ClubLinkedEntityInterface {
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(length: 255)]
  #[Groups(['sale-payment-terminal', 'sale-read', 'sale-payment-mode-read'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['sale-payment-terminal', 'sale-read', 'sale-payment-mode-read'])]
  private ?string $description = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['sale-payment-terminal', 'sale-read', 'sale-payment-mode-read'])]
  private ?string $icon = null;

  #[ORM\ManyToOne(targetEntity: SalePaymentMode::class, inversedBy: 'paymentTerminals')]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['sale-payment-terminal', 'sale-payment-terminal-write'])]
  private ?SalePaymentMode $paymentMode = null;

  /**
   * The provider connection (shared credentials) this device belongs to.
   * A device cannot exist without its connection.
   */
  #[ORM\ManyToOne(targetEntity: SalePaymentTerminalConnection::class, inversedBy: 'devices')]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  #[Groups(['sale-payment-terminal', 'sale-read'])]
  private ?SalePaymentTerminalConnection $connection = null;

  /**
   * The provider's own device/reader id (e.g. SumUp's readerId). Fixed at
   * discovery time by sync-devices; not a secret, so stored in the clear.
   */
  #[ORM\Column(length: 255)]
  #[Groups(['sale-payment-terminal'])]
  #[Assert\NotBlank]
  private ?string $externalDeviceId = null;

  /**
   * Stamped by sync-devices whenever this device is still returned by the
   * provider. Compared against the connection's lastSyncedAt to flag a device
   * that went missing from the most recent sync, without deleting it.
   */
  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  #[Groups(['sale-payment-terminal-read'])]
  private ?\DateTimeImmutable $lastSeenAt = null;

  #[ORM\Column]
  #[Groups(['sale-payment-terminal', 'sale-read', 'sale-payment-mode-read'])]
  #[Assert\NotNull]
  private ?bool $available = true;

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

  public function getIcon(): ?string {
    return $this->icon;
  }

  public function setIcon(?string $icon): static {
    $this->icon = $icon;
    return $this;
  }

  public function getPaymentMode(): ?SalePaymentMode {
    return $this->paymentMode;
  }

  public function setPaymentMode(?SalePaymentMode $paymentMode): static {
    $this->paymentMode = $paymentMode;
    return $this;
  }

  public function getConnection(): ?SalePaymentTerminalConnection {
    return $this->connection;
  }

  public function setConnection(SalePaymentTerminalConnection $connection): static {
    $this->connection = $connection;
    return $this;
  }

  public function getExternalDeviceId(): ?string {
    return $this->externalDeviceId;
  }

  public function setExternalDeviceId(string $externalDeviceId): static {
    $this->externalDeviceId = $externalDeviceId;
    return $this;
  }

  public function getLastSeenAt(): ?\DateTimeImmutable {
    return $this->lastSeenAt;
  }

  public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static {
    $this->lastSeenAt = $lastSeenAt;
    return $this;
  }

  public function isAvailable(): ?bool {
    return $this->available;
  }

  public function setAvailable(bool $available): static {
    $this->available = $available;
    return $this;
  }

  /**
   * Whether this device is actually usable to initiate a checkout: enabled itself,
   * and its connection is enabled and configured.
   */
  #[Groups(['sale-payment-terminal-read', 'sale-read', 'sale-payment-mode-read'])]
  public function isUsable(): bool {
    return $this->available === true
      && $this->connection !== null
      && $this->connection->isAvailable() === true
      && $this->connection->isConfigured();
  }

  /**
   * Whether the checkout flow must always show the terminal picker for this
   * device, even if it's the only usable one for its payment mode. Delegated
   * to the connection: a connection whose devices span several payment modes
   * shouldn't have checkout silently pick one at random.
   */
  #[Groups(['sale-payment-terminal-read', 'sale-read', 'sale-payment-mode-read'])]
  public function isForceTerminalSelection(): bool {
    return $this->connection?->isForceTerminalSelection() ?? false;
  }
}
