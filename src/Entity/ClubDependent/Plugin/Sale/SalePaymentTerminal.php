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
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalCheckout;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalCheckoutStatus;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalDevices;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalDeviceStatus;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalListDevices;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalSetDevice;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Enum\Permission;
use App\Enum\SalePaymentTerminalProvider;
use App\Repository\ClubDependent\Plugin\Sale\SalePaymentTerminalRepository;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SalePaymentTerminalRepository::class)]
#[UniqueEntity(fields: ['name', 'club'], ignoreNull: true)]
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
    new Post(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_EDIT->value."', request)",
      read: false,
    ),

    // Provider-agnostic device discovery used during setup (validates credentials + lists devices)
    new Post(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals/-/list-devices',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      controller: SalePaymentTerminalListDevices::class,
      openapi: new Model\Operation(
        summary: 'List the devices available for a provider + credentials',
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type' => 'object',
                'required' => ['provider', 'credentials'],
                'properties' => [
                  'provider' => ['type' => 'string', 'example' => 'sumup'],
                  'credentials' => ['type' => 'object'],
                ],
              ],
            ],
          ]),
        ),
      ),
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_EDIT->value."', request)",
      read: false,
      deserialize: false,
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

    // List devices for an existing terminal using its stored credentials (reconfiguration)
    new Get(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals/{uuid}/devices',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'uuid' => new Link(fromClass: self::class),
      ],
      controller: SalePaymentTerminalDevices::class,
      openapi: new Model\Operation(
        summary: 'List devices using the terminal\'s stored credentials',
      ),
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_EDIT->value."', request)",
      read: false,
    ),

    // Re-select the device for an existing terminal (merges into stored credentials)
    new Post(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals/{uuid}/device',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'uuid' => new Link(fromClass: self::class),
      ],
      controller: SalePaymentTerminalSetDevice::class,
      openapi: new Model\Operation(
        summary: 'Select the active device for this terminal',
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type' => 'object',
                'required' => ['deviceId'],
                'properties' => ['deviceId' => ['type' => 'string']],
              ],
            ],
          ]),
        ),
      ),
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_EDIT->value."', request)",
      read: false,
      deserialize: false,
    ),

    // Test connection: live status of the configured device
    new Get(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminals/{uuid}/device-status',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'uuid' => new Link(fromClass: self::class),
      ],
      controller: SalePaymentTerminalDeviceStatus::class,
      openapi: new Model\Operation(
        summary: 'Get the live status of the configured device (test connection)',
      ),
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_ACCESS->value."', request)",
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
  #[Groups(['sale-payment-terminal', 'sale-read'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['sale-payment-terminal', 'sale-read'])]
  private ?string $description = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['sale-payment-terminal', 'sale-read'])]
  private ?string $icon = null;

  #[ORM\ManyToOne(targetEntity: SalePaymentMode::class, inversedBy: 'paymentTerminals')]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['sale-payment-terminal', 'sale-payment-terminal-write'])]
  private ?SalePaymentMode $paymentMode = null;

  #[ORM\Column(type: Types::STRING, enumType: SalePaymentTerminalProvider::class, options: ['default' => 'sumup'])]
  #[Groups(['sale-payment-terminal', 'sale-read'])]
  #[Assert\NotNull]
  private SalePaymentTerminalProvider $provider = SalePaymentTerminalProvider::sumup;

  #[ORM\Column]
  #[Groups(['sale-payment-terminal'])]
  #[Assert\NotNull]
  private ?bool $available = true;

  /**
   * Provider credentials stored as encrypted JSON.
   * Write-only: accepted in POST/PATCH body but never returned in responses.
   * Use isConfigured() to check if credentials have been set.
   */
  #[ORM\Column(type: 'encrypted_json', nullable: true)]
  #[Groups(['sale-payment-terminal-write'])]
  private ?array $credentials = null;

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

  public function getProvider(): SalePaymentTerminalProvider {
    return $this->provider;
  }

  public function setProvider(SalePaymentTerminalProvider $provider): static {
    $this->provider = $provider;
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
   * Raw credentials array (for application code / service layer only).
   * Not exposed via API — use setCredentials() to update, isConfigured() to check.
   */
  public function getCredentials(): ?array {
    return $this->credentials;
  }

  public function setCredentials(?array $credentials): static {
    $this->credentials = $credentials;
    return $this;
  }

  /**
   * Returns true if credentials have been configured for this terminal.
   * Exposed in API read responses instead of the raw credentials.
   */
  #[Groups(['sale-payment-terminal-read'])]
  public function isConfigured(): bool {
    return !empty($this->credentials);
  }
}
