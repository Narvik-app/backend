<?php

namespace App\Entity\ClubDependent\Plugin\Loan;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\OpenApi\Model;
use App\Controller\ClubDependent\Plugin\Loan\LoanCategoryMove;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\SortableEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Enum\Permission;
use App\Repository\ClubDependent\Plugin\Loan\LoanCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanCategoryRepository::class)]
#[UniqueEntity(fields: ['weight', 'club'], ignoreNull: true)]
#[UniqueEntity(fields: ['name', 'club'], ignoreNull: true)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/loan-categories/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/loan-categories.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::LOAN_CATEGORIES_ACCESS->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/loan-categories.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::LOAN_CATEGORIES_EDIT->value."', request)",
      read: false
    ),

    new Get(
      security: "is_granted('".Permission::LOAN_CATEGORIES_ACCESS->value."', object)"
    ),
    new Patch(
      security: "is_granted('".Permission::LOAN_CATEGORIES_EDIT->value."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::LOAN_CATEGORIES_EDIT->value."', object)",
    ),

    new Put(
      uriTemplate: '/clubs/{clubUuid}/loan-categories/{uuid}/move',
      controller: LoanCategoryMove::class,
      openapi: new Model\Operation(
        description: 'Move `up` or `down` a loan category',
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
      security: "is_granted('".Permission::LOAN_CATEGORIES_EDIT->value."', object)",
    )
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['loan-category', 'loan-category-read']
  ],
  denormalizationContext: [
    'groups' => ['loan-category', 'loan-category-write']
  ],
  order: ['weight' => 'asc'],
)]
#[ApiFilter(OrderFilter::class, properties: ['weight' => 'ASC', 'name' => 'ASC'])]
class LoanCategory extends UuidEntity implements ClubLinkedEntityInterface, SortableEntityInterface {
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(length: 255)]
  #[Groups(['loan-category', 'loan-item-read'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  #[ORM\Column(nullable: true)]
  #[Groups(['loan-category'])]
  private ?int $weight = null;

  #[ORM\OneToMany(mappedBy: 'category', targetEntity: LoanItem::class, orphanRemoval: true)]
  #[Groups(['loan-category-read'])]
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
   * @return Collection<int, LoanItem>
   */
  public function getItems(): Collection {
    return $this->items;
  }

  public function addItem(LoanItem $item): static {
    if (!$this->items->contains($item)) {
      $this->items->add($item);
      $item->setCategory($this);
    }
    return $this;
  }

  public function removeItem(LoanItem $item): static {
    if ($this->items->removeElement($item)) {
      if ($item->getCategory() === $this) {
        $item->setCategory(null);
      }
    }
    return $this;
  }
}
