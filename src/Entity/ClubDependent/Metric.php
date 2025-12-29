<?php

namespace App\Entity\ClubDependent;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\State\MetricProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
  operations: [
    new Get(security: "is_granted('".UserRole::super_admin->value."')"),
    new GetCollection(security: "is_granted('".UserRole::super_admin->value."')"),

    new Get(
      uriTemplate: '/clubs/{clubUuid}/metrics/{name}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'name' => new Link(fromClass: self::class),
      ],
      security: "is_granted('".ClubRole::supervisor->value."', request)",
      name: 'club_metric',
    ),
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/metrics',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::supervisor->value."', request)",
      name: 'club_metrics',
    ),
  ],
  normalizationContext: [
    'groups' => ['metric'],
  ],
  provider: MetricProvider::class,
)]
#[QueryParameter(key: 'previous-season', schema: ['type' => 'boolean'], description: 'Filter for the previous season instead of the current one.', required: false)]
#[QueryParameter(key: 'start', schema: ['type' => 'string', 'format' => 'date'], description: '`end` filter must also be defined to work. Otherwise fallback to the current season filtering.', required: false)]
#[QueryParameter(key: 'end', schema: ['type' => 'string', 'format' => 'date'], description: 'Fallback to the current season filtering if not defined.', required: false)]
#[QueryParameter(key: 'order', schema: ['type' => 'string', 'enum' => ['ASC', 'DESC']], description: 'Sort order for member-presence-stats (ASC for least present first, DESC for most present first). Default: DESC', required: false)]
#[QueryParameter(key: 'page', schema: ['type' => 'integer', 'minimum' => 1], description: 'Page number for member-presence-stats pagination. Default: 1', required: false)]
#[QueryParameter(key: 'itemsPerPage', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], description: 'Number of items per page for member-presence-stats. Default: 30, Max: 100', required: false)]
#[Get]
#[GetCollection]
class Metric {
  private ?Club $club = null;

  #[ApiProperty(identifier: true)]
  #[Groups(['metric'])]
  private string $name;

  #[Groups(['metric'])]
  private ?float $value = null;

  #[Groups(['metric'])]
  private ?array $values = null;

  #[Groups(['metric'])]
  private ?array $pagination = null;

  /**
   * @var Collection<int, Metric>
   */
  #[Groups(['metric'])]
  private Collection $childMetrics;

  public function __construct() {
    $this->childMetrics = new ArrayCollection();
  }


  public function getClub(): ?Club {
    return $this->club;
  }

  public function setClub(?Club $club): static {
    $this->club = $club;
    return $this;
  }

  public function getName(): string {
    return $this->name;
  }

  public function setName(string $name): Metric {
    $this->name = str_replace('.', '-', $name);
    return $this;
  }

  public function getValue(): ?float {
    return $this->value;
  }

  public function setValue(?float $value): Metric {
    $this->value = $value;
    return $this;
  }

  public function getValues(): ?array {
    return $this->values;
  }

  public function setValues(?array $values): Metric {
    $this->values = $values;
    return $this;
  }

  public function getPagination(): ?array {
    return $this->pagination;
  }

  public function setPagination(?array $pagination): Metric {
    $this->pagination = $pagination;
    return $this;
  }

  public function getChildMetrics(): ?Collection {
    return $this->childMetrics;
  }

  public function setChildMetrics(?array $childMetrics): Metric {
    $this->childMetrics = new ArrayCollection($childMetrics);
    return $this;
  }

  public function addChildMetric(Metric $metric): Metric {
    $this->childMetrics->add($metric);
    return $this;
  }
}
