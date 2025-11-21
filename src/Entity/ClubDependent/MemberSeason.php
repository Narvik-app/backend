<?php

namespace App\Entity\ClubDependent;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Entity\Abstract\UuidEntity;
use App\Entity\AgeCategory;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Season;
use App\Enum\ClubRole;
use App\Repository\ClubDependent\MemberSeasonRepository;
use App\Security\Voter\SelfMemberVoter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MemberSeasonRepository::class)]
#[UniqueEntity(fields: ['season', 'member'], message: 'Season already registered for that member.')]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/seasons/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/seasons.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
      ],
      security: "is_granted('".ClubRole::supervisor->value."', request)  || is_granted('" . SelfMemberVoter::READ . "', request)",
    ),

    new Post(
      uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/seasons',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
      ],
      securityPostDenormalize: "is_granted('".ClubRole::supervisor->value."', request)",
      read: false
    ),

    new Get(
      security: "is_granted('".ClubRole::supervisor->value."', object) || is_granted('" . SelfMemberVoter::READ . "', object)",
    ),
    new Delete(
      security: "is_granted('".ClubRole::supervisor->value."', object)",
    )
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['member-season', 'member-season-read', 'common-read']
  ],
  denormalizationContext: [
    'groups' => ['member-season', 'member-season-write']
  ],
  order: ['season.name' => 'DESC'],
)]
class MemberSeason extends UuidEntity implements ClubLinkedEntityInterface {
  public static function getClubSqlPath(): string {
    return "member.club";
  }

  #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'memberSeasons')]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  #[Groups(['member-season'])]
  private ?Member $member = null;

  #[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'memberSeasons')]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  #[Groups(['member-season'])]
  private ?Season $season = null;

  #[ORM\ManyToOne(targetEntity: AgeCategory::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['member-season', 'member-read', 'member-presence-read'])]
  private ?AgeCategory $ageCategory = null;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, options: ["default" => 0])]
  #[Groups(['member-season', 'member-read', 'member-presence-read'])]
  private bool $isSecondaryClub = false;

  public function getClub(): ?Club {
    return $this->member?->getClub();
  }

  public function setClub(?Club $club): static {
    // We don't set the club in the parent
    // $this->member?->setClub($club);
    return $this;
  }

  public function getSeason(): ?Season {
    return $this->season;
  }

  public function setSeason(?Season $season): static {
    $this->season = $season;
    return $this;
  }

  public function getAgeCategory(): ?AgeCategory {
    return $this->ageCategory;
  }

  public function setAgeCategory(?AgeCategory $ageCategory): static {
    $this->ageCategory = $ageCategory;
    return $this;
  }

  public function getMember(): ?Member {
    return $this->member;
  }

  public function setMember(?Member $member): static {
    $this->member = $member;
    return $this;
  }

  public function getIsSecondaryClub(): bool {
    return $this->isSecondaryClub;
  }

  public function setIsSecondaryClub(bool $isSecondaryClub): MemberSeason {
    $this->isSecondaryClub = $isSecondaryClub;
    return $this;
  }
}
