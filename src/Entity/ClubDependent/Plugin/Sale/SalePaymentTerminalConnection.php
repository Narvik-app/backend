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
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalConnectionListDevices;
use App\Controller\ClubDependent\Plugin\Sale\SalePaymentTerminalConnectionSyncDevices;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Enum\Permission;
use App\Enum\SalePaymentTerminalProvider;
use App\Repository\ClubDependent\Plugin\Sale\SalePaymentTerminalConnectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A provider account connection (e.g. one SumUp merchant account): holds the
 * shared credentials once, and owns any number of discovered devices
 * (SalePaymentTerminal). Devices are created/refreshed via sync-devices.
 */
#[ORM\Entity(repositoryClass: SalePaymentTerminalConnectionRepository::class)]
#[UniqueEntity(fields: ['name', 'club'], ignoreNull: true)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/sale-payment-terminal-connections/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminal-connections.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_ACCESS->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminal-connections.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_EDIT->value."', request)",
      read: false,
    ),

    // Provider-agnostic credential validation used while adding/editing a connection
    new Post(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminal-connections/-/list-devices',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      controller: SalePaymentTerminalConnectionListDevices::class,
      openapi: new Model\Operation(
        summary: 'List the devices available for a provider + credentials (validates the credentials)',
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

    // Discover/refresh all devices for this connection: upserts SalePaymentTerminal rows
    new Post(
      uriTemplate: '/clubs/{clubUuid}/sale-payment-terminal-connections/{uuid}/sync-devices',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'uuid' => new Link(fromClass: self::class),
      ],
      controller: SalePaymentTerminalConnectionSyncDevices::class,
      openapi: new Model\Operation(
        summary: 'Discover devices for this connection and create/refresh matching terminals',
      ),
      security: "is_granted('".Permission::SALE_PAYMENT_TERMINALS_EDIT->value."', request)",
      read: false,
      deserialize: false,
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['sale-payment-terminal-connection', 'sale-payment-terminal-connection-read'],
  ],
  denormalizationContext: [
    'groups' => ['sale-payment-terminal-connection', 'sale-payment-terminal-connection-write'],
  ],
)]
class SalePaymentTerminalConnection extends UuidEntity implements ClubLinkedEntityInterface {
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(length: 255)]
  #[Groups(['sale-payment-terminal-connection', 'sale-read'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  #[ORM\Column(type: Types::STRING, enumType: SalePaymentTerminalProvider::class, options: ['default' => 'sumup'])]
  #[Groups(['sale-payment-terminal-connection', 'sale-read'])]
  #[Assert\NotNull]
  private SalePaymentTerminalProvider $provider = SalePaymentTerminalProvider::sumup;

  #[ORM\Column]
  #[Groups(['sale-payment-terminal-connection'])]
  #[Assert\NotNull]
  private ?bool $available = true;

  /**
   * Provider credentials stored as encrypted JSON (e.g. {apiKey, merchantCode} for SumUp).
   * Write-only: accepted in POST/PATCH body but never returned in responses.
   * Use isConfigured() to check if credentials have been set.
   */
  #[ORM\Column(type: 'encrypted_json', nullable: true)]
  #[Groups(['sale-payment-terminal-connection-write'])]
  private ?array $credentials = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  #[Groups(['sale-payment-terminal-connection-read'])]
  private ?\DateTimeImmutable $lastSyncedAt = null;

  /** @var Collection<int, SalePaymentTerminal> */
  #[ORM\OneToMany(targetEntity: SalePaymentTerminal::class, mappedBy: 'connection')]
  private Collection $devices;

  public function __construct() {
    parent::__construct();
    $this->devices = new ArrayCollection();
  }

  public function getName(): ?string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = ucfirst($name);
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

  public function getLastSyncedAt(): ?\DateTimeImmutable {
    return $this->lastSyncedAt;
  }

  public function setLastSyncedAt(?\DateTimeImmutable $lastSyncedAt): static {
    $this->lastSyncedAt = $lastSyncedAt;
    return $this;
  }

  /** @return Collection<int, SalePaymentTerminal> */
  public function getDevices(): Collection {
    return $this->devices;
  }

  /**
   * Returns true if credentials have been configured for this connection.
   * Exposed in API read responses instead of the raw credentials.
   */
  #[Groups(['sale-payment-terminal-connection-read'])]
  public function isConfigured(): bool {
    return !empty($this->credentials);
  }
}
