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
use App\Controller\ClubDependent\Plugin\Presence\MemberPresencesFromCerbere;
use App\Controller\ClubDependent\Plugin\Presence\MemberPresencesFromCsv;
use App\Controller\ClubDependent\Plugin\Presence\MemberPresencesImportFromExternal;
use App\Controller\ClubDependent\Plugin\Presence\MemberPresenceToday;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
use App\Entity\ClubDependent\Member;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\ClubRole;
use App\Filter\ClubDependent\CurrentSeasonFilter;
use App\Filter\ClubDependent\PreviousSeasonFilter;
use App\Filter\MultipleFilter;
use App\Repository\ClubDependent\Plugin\Presence\MemberPresenceRepository;
use App\Security\Voter\SelfMemberVoter;
use App\Validator\Constraints\ActivityMustBeEnabled;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MemberPresenceRepository::class)]
#[ORM\Index(name: 'idx_member_presence_member_date', columns: ['member_id', 'date'])]
#[ORM\Index(name: 'idx_member_presence_club_date', columns: ['club_id', 'date'])]
#[UniqueEntity(fields: ['member', 'club', 'date'], message: 'Member already registered for that day')]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/member-presences/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/member-presences.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request)",
    ),
    new Get(
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/member-presences',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      securityPostDenormalize: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request)",
      read: false
    ),
    new Patch(
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request)",
    ),
    new Delete(
      security: "is_granted('".ClubRole::supervisor->value."', request)",
    ),

    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/member-presences/-/today',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      controller: MemberPresenceToday::class,
      openapi: new Model\Operation(
        summary: 'Get all members present today',
      ),
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request)",
      read: false,
      write: false,
    ),

    new Post(
      uriTemplate: '/clubs/{clubUuid}/member-presences/-/from-cerbere',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      controller: MemberPresencesFromCerbere::class,
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
      deserialize: false,
    ),

    new Post(
      uriTemplate: '/clubs/{clubUuid}/member-presences/-/from-csv',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      controller: MemberPresencesFromCsv::class,
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
    new Post(
      uriTemplate: '/clubs/{clubUuid}/member-presences/-/import-from-external-presences',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      controller: MemberPresencesImportFromExternal::class,
      securityPostDenormalize: "is_granted('".ClubRole::admin->value."', request)",
      read: false,
      deserialize: false,
    )
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['member-presence', 'member-presence-read']
  ],
  denormalizationContext: [
    'groups' => ['member-presence', 'member-presence-write']
  ],
  paginationClientEnabled: true,
)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/presences.{_format}',
  operations: [
    new GetCollection(
      security: "is_granted('".ClubRole::supervisor->value."', request) || is_granted('".ClubRole::badger->value."', request) || is_granted('" . SelfMemberVoter::READ . "', request)",
    ),
  ], uriVariables: [
  'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
  'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
], normalizationContext: [
  'groups' => ['member-presence', 'member-presence-read']
],
  paginationClientEnabled: true,
)]
#[ApiFilter(DateFilter::class, properties: ['date' => DateFilter::EXCLUDE_NULL])]
#[ApiFilter(OrderFilter::class, properties: ['date' => 'DESC', 'createdAt' => 'DESC'])]
#[ApiFilter(MultipleFilter::class, properties: ['member.firstname', 'member.lastname', 'member.licence'])]
#[ApiFilter(SearchFilter::class, properties: ['activities.uuid' => 'exact'])]
#[ApiFilter(CurrentSeasonFilter::class, properties: ['date'])]
#[ApiFilter(PreviousSeasonFilter::class, properties: ['date'])]
class MemberPresence extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'memberPresences')]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['member-presence'])]
  private ?Member $member = null;

  #[ORM\Column(type: Types::DATE_IMMUTABLE)]
  #[Groups(['member-presence'])]
  private ?\DateTimeImmutable $date = null;

  #[ORM\ManyToMany(targetEntity: Activity::class, inversedBy: 'memberPresences')]
  #[Groups(['member-presence'])]
  #[ActivityMustBeEnabled]
  private Collection $activities;

  public function __construct() {
    parent::__construct();
    $this->activities = new ArrayCollection();
    $this->date = new \DateTimeImmutable();
  }

  public function getClub(): ?Club {
    return $this->club;
  }

  public function setClub(?Club $club): static {
    $this->club = $club;
    return $this;
  }


  public function getMember(): ?Member {
    return $this->member;
  }

  public function setMember(?Member $member): static {
    $this->member = $member;
    if ($member) {
      $this->club = $member->getClub();
    }

    return $this;
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
