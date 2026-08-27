<?php

namespace App\Entity\ClubDependent\Plugin\Presence;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
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
use ApiPlatform\OpenApi\Model;
use App\Controller\ClubDependent\Plugin\Presence\ExternalPresencesFromCsv;
use App\Controller\ClubDependent\Plugin\Presence\ExternalPresenceToday;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\ClubRole;
use App\Filter\ClubDependent\CurrentSeasonFilter;
use App\Filter\ClubDependent\PreviousSeasonFilter;
use App\Filter\MultipleFilter;
use App\Repository\ClubDependent\Plugin\Presence\ExternalPresenceRepository;
use App\Validator\Constraints\ActivityMustBeEnabled;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ExternalPresenceRepository::class)]
#[ORM\Index(name: 'idx_external_presence_club_date', columns: ['club_id', 'date'])]
#[UniqueEntity(fields: ['licence', 'club', 'date'], message: 'Member already registered for that day')]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/external-presences/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/external-presences.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/external-presences',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      securityPostDenormalize: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request)",
      read: false
    ),
    new Get(
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request)",
    ),
    new Patch(
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request)",
    ),
    new Delete(
      security: "is_granted('".ClubRole::supervisor->value."', request)",
    ),

    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/external-presences/-/today',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      controller: ExternalPresenceToday::class,
      openapi: new Model\Operation(
        summary: 'Get all external presence for today',
      ),
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request)",
      read: false,
      write: false
    ),

    new Post(
      uriTemplate: '/clubs/{clubUuid}/external-presences/-/from-csv',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      controller: ExternalPresencesFromCsv::class,
      openapi: new Model\Operation(
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'multipart/form-data' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'file' => [
                    'type' => 'string',
                    'format' => 'binary'
                  ]
                ]
              ]
            ]
          ])
        )
      ),
      securityPostDenormalize: "is_granted('".ClubRole::admin->value."', request)",
      read: false,
      deserialize: false
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['external-presence', 'external-presence-read']
  ],
  denormalizationContext: [
    'groups' => ['external-presence', 'external-presence-write']
  ],
  paginationClientEnabled: true,
)]
#[ApiFilter(DateFilter::class, properties: ['date' => DateFilter::EXCLUDE_NULL])]
#[ApiFilter(OrderFilter::class, properties: ['date' => 'DESC', 'createdAt' => 'DESC'])]
#[ApiFilter(MultipleFilter::class, properties: ['firstname', 'lastname', 'licence'])]
#[ApiFilter(SearchFilter::class, properties: ['activities.uuid' => 'exact'])]
#[ApiFilter(CurrentSeasonFilter::class, properties: ['date'])]
#[ApiFilter(PreviousSeasonFilter::class, properties: ['date'])]
class ExternalPresence extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
  #[Groups(['external-presence'])]
  private ?string $licence = null;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING)]
  #[Groups(['external-presence'])]
  private string $firstname;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING)]
  #[Groups(['external-presence'])]
  private string $lastname;

  #[Groups(['external-presence-read'])]
  private string $fullName = "";

  #[ORM\Column(type: Types::DATE_IMMUTABLE)]
  #[Groups(['external-presence-read'])]
  private ?\DateTimeImmutable $date = null;

  #[ORM\ManyToMany(targetEntity: Activity::class, inversedBy: 'externalPresences')]
  #[Groups(['external-presence'])]
  #[ActivityMustBeEnabled]
  private Collection $activities;

  public function __construct() {
    parent::__construct();
    $this->activities = new ArrayCollection();
    $this->date = new \DateTimeImmutable();
  }

  public function getLicence(): ?string {
    return $this->licence;
  }

  public function setLicence(?string $licence): ExternalPresence {
    $this->licence = $licence;
    return $this;
  }

  public function getFirstname(): string {
    return $this->firstname;
  }

  public function setFirstname(string $firstname): ExternalPresence {
    $this->firstname = ucfirst($firstname);
    return $this;
  }

  public function getLastname(): string {
    return $this->lastname;
  }

  public function setLastname(string $lastname): ExternalPresence {
    $this->lastname = strtoupper($lastname);
    return $this;
  }

  public function getFullName(): string {
    return $this->lastname . " " . $this->firstname;
  }

  public function getDate(): ?\DateTimeImmutable {
    return $this->date;
  }

  public function setDate(\DateTimeImmutable $date): static {
    $this->date = $date;
    return $this;
  }

  /**
   * @return Collection<int, Activity>
   */
  public function getActivities(): Collection {
    return $this->activities;
  }

  public function addActivity(Activity $activity): static {
    if (!$this->activities->contains($activity)) {
      $this->activities->add($activity);
    }
    return $this;
  }

  public function removeActivity(Activity $activity): static {
    $this->activities->removeElement($activity);
    return $this;
  }
}
