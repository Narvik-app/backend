<?php

namespace App\Entity\ClubDependent\Plugin\Sale;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
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
use App\Controller\ClubDependent\Plugin\Sale\InventoryCategoryMove;
use App\Controller\ClubDependent\Plugin\Sale\InventoryCategoryReorder;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\SortableEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Enum\ClubRole;
use App\Repository\ClubDependent\Plugin\Sale\InventoryCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: InventoryCategoryRepository::class)]
#[UniqueEntity(fields: ['weight', 'club'], ignoreNull: true)]
#[UniqueEntity(fields: ['name', 'club'], ignoreNull: true)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/inventory-categories/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/inventory-categories.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::supervisor->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/inventory-categories.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::admin->value."', request)",
      read: false
    ),
    new Put(
      uriTemplate: '/clubs/{clubUuid}/inventory-categories/-/reorder',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      controller: InventoryCategoryReorder::class,
      openapi: new Model\Operation(
        description: 'Reorder inventory categories',
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'uuids' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                  ],
                ]
              ]
            ]
          ])
        )
      ),
      security: "is_granted('".ClubRole::admin->value."', request)",
      read: false,
    ),

    new Get(
      security: "is_granted('".ClubRole::supervisor->value."', object)"
    ),
    new Patch(
      security: "is_granted('".ClubRole::admin->value."', object)",
    ),
    new Delete(
      security: "is_granted('".ClubRole::admin->value."', object)",
    ),

    new Put(
      uriTemplate: '/clubs/{clubUuid}/inventory-categories/{uuid}/move',
      controller: InventoryCategoryMove::class,
      openapi: new Model\Operation(
        description: 'Move `up` or `down` an inventory category',
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
      security: "is_granted('".ClubRole::admin->value."', object)",
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['inventory-category', 'inventory-category-read']
  ],
  denormalizationContext: [
    'groups' => ['inventory-category', 'inventory-category-write']
  ],
  order: ['weight' => 'asc'],
)]
#[ApiFilter(OrderFilter::class, properties: ['weight' => 'ASC', 'name' => 'ASC'])]
class InventoryCategory extends UuidEntity implements ClubLinkedEntityInterface, SortableEntityInterface {
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(length: 255)]
  #[Groups(['inventory-category', 'inventory-item-read'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  #[ORM\Column(nullable: true)]
  #[Groups(['inventory-category'])]
  private ?int $weight = null;

  #[ORM\OneToMany(mappedBy: 'category', targetEntity: InventoryItem::class, orphanRemoval: true)]
  #[Groups(['inventory-category-read'])]
  private Collection $items;

  public function __construct() {
    parent::__construct();
    $this->items = new ArrayCollection();
  }

  public function getName(): ?string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = ucfirst($name);
    return $this;
  }

  public function getWeight(): ?int {
    return $this->weight;
  }

  public function setWeight(int $weight): static {
    $this->weight = $weight;
    return $this;
  }

  /**
   * @return Collection<int, InventoryItem>
   */
  public function getItems(): Collection {
      return $this->items;
  }

  public function addItem(InventoryItem $item): static {
      if (!$this->items->contains($item)) {
          $this->items->add($item);
          $item->setCategory($this);
      }
      return $this;
  }

  public function removeItem(InventoryItem $item): static {
      if ($this->items->removeElement($item)) {
          // set the owning side to null (unless already changed)
          if ($item->getCategory() === $this) {
              $item->setCategory(null);
          }
      }
      return $this;
  }
}
