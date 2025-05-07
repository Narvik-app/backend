<?php

namespace App\Entity\ClubDependent;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Controller\ClubDependent\ClubSettingImportLogo;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Presence\Activity;
use App\Entity\File;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Season;
use App\Enum\ClubActivity;
use App\Enum\ClubRole;
use App\Repository\ClubDependent\ClubSettingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ClubSettingRepository::class)]
#[UniqueEntity(fields: ['club'])]
#[ApiResource(uriTemplate: '/clubs/{clubUuid}/settings/{uuid}.{_format}', operations: [
    new Get(),
    new Patch(security: "is_granted('" . ClubRole::admin->value . "', object)"),

    new Post(
      uriTemplate: '//clubs/{clubUuid}/settings/{uuid}/logo',
      controller: ClubSettingImportLogo::class,
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
    )

  ], uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid'     => new Link(fromClass: self::class),
  ], normalizationContext: [
    'groups' => ['club-setting', 'club-setting-read'],
  ], denormalizationContext: [
    'groups' => ['club-setting', 'club-setting-write'],
  ], order: ['name' => 'asc'],)]
class ClubSetting extends UuidEntity implements ClubLinkedEntityInterface {
  public static function getClubSqlPath(): string {
    return "club";
  }

  #[ORM\OneToOne(inversedBy: 'settings', targetEntity: Club::class)]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  private ?Club $club = null;

  #[ORM\Column(type: "string", enumType: ClubActivity::class, options: ['default' => ClubActivity::generic])]
  #[Groups(['club-setting'])]
  private ClubActivity $activity = ClubActivity::generic;

  #[ORM\OneToOne(targetEntity: File::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['club-setting-read'])]
  private ?File $logo = null;

  #[ORM\Column(type: 'string', length: 5, options: ["default" => '08-31'])]
  #[Groups(['club-setting'])]
  #[Assert\Regex(pattern: '/^(0[1-9]|1[012])-[0-3][0-9]$/m')]
  private string $seasonEnd = "08-31";

  #[ORM\OneToOne(targetEntity: Activity::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['club-setting'])]
  private ?Activity $controlShootingActivity = null;

  /**
   * @var Collection<int, Activity>
   */
  #[ORM\ManyToMany(targetEntity: Activity::class)]
  #[ORM\JoinTable(
    name: 'club_setting_exclude_activities_od',
  )]
  #[Groups(['club-setting'])]
  private Collection $excludedActivitiesFromOpeningDays;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  #[Groups(['club-setting-read'])]
  #[ApiProperty(security: "is_granted('".ClubRole::admin->value."', object)")] // Property can be read by club admin
  private ?\DateTimeImmutable $itacImportDate = null;

  #[ORM\Column(options: ['default' => 0])]
  #[Groups(['club-setting-read'])]
  #[Assert\NotBlank]
  #[ApiProperty(security: "is_granted('".ClubRole::admin->value."', object)")] // Property can be read by club admin
  private int $itacImportRemaining = 0;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  #[Groups(['club-setting-read'])]
  #[ApiProperty(security: "is_granted('".ClubRole::admin->value."', object)")] // Property can be read by club admin
  private ?\DateTimeImmutable $itacSecondaryImportDate = null;

  #[ORM\Column(options: ['default' => 0])]
  #[Groups(['club-setting-read'])]
  #[Assert\NotBlank]
  #[ApiProperty(security: "is_granted('".ClubRole::admin->value."', object)")] // Property can be read by club admin
  private int $itacSecondaryImportRemaining = 0;

  #[ORM\Column(options: ['default' => 0])]
  #[Groups(['club-setting-read'])]
  #[Assert\NotBlank]
  #[ApiProperty(security: "is_granted('".ClubRole::admin->value."', object)")] // Property can be read by club admin
  private int $cerbereImportRemaining = 0;

  #[Groups(['common-read', 'club-setting-read'])]
  private ?Season $currentSeason = null;

  public function __construct() {
    parent::__construct();
    $this->excludedActivitiesFromOpeningDays = new ArrayCollection();
  }

  public function getClub(): ?Club {
    return $this->club;
  }

  public function setClub(?Club $club): static {
    $this->club = $club;
    return $this;
  }

  public function getControlShootingActivity(): ?Activity {
    return $this->controlShootingActivity;
  }

  public function setControlShootingActivity(?Activity $controlShootingActivity): ClubSetting {
    $this->controlShootingActivity = $controlShootingActivity;
    return $this;
  }

  public function getItacImportDate(): ?\DateTimeImmutable {
    return $this->itacImportDate;
  }

  public function setItacImportDate(?\DateTimeImmutable $itacImportDate): ClubSetting {
    $this->itacImportDate = $itacImportDate;
    return $this;
  }

  public function getItacImportRemaining(): int {
    return $this->itacImportRemaining;
  }

  public function setItacImportRemaining(int $itacImportRemaining): ClubSetting {
    if ($itacImportRemaining < 0) {
      $itacImportRemaining = 0;
    }
    $this->itacImportRemaining = $itacImportRemaining;
    return $this;
  }

  public function getItacSecondaryImportDate(): ?\DateTimeImmutable {
    return $this->itacSecondaryImportDate;
  }

  public function setItacSecondaryImportDate(?\DateTimeImmutable $itacSecondaryImportDate): ClubSetting {
    $this->itacSecondaryImportDate = $itacSecondaryImportDate;
    return $this;
  }

  public function getItacSecondaryImportRemaining(): int {
    return $this->itacSecondaryImportRemaining;
  }

  public function setItacSecondaryImportRemaining(int $itacSecondaryImportRemaining): ClubSetting {
    if ($itacSecondaryImportRemaining < 0) {
      $itacSecondaryImportRemaining = 0;
    }
    $this->itacSecondaryImportRemaining = $itacSecondaryImportRemaining;
    return $this;
  }

  public function getCerbereImportRemaining(): int {
    return $this->cerbereImportRemaining;
  }

  public function setCerbereImportRemaining(int $cerbereImportRemaining): ClubSetting {
    if ($cerbereImportRemaining < 0) {
      $cerbereImportRemaining = 0;
    }
    $this->cerbereImportRemaining = $cerbereImportRemaining;
    return $this;
  }

  /**
   * @return Collection<int, Activity>
   */
  public function getExcludedActivitiesFromOpeningDays(): Collection {
      return $this->excludedActivitiesFromOpeningDays;
  }

  public function addExcludedActivitiesFromOpeningDay(Activity $excludedActivitiesFromOpeningDay): ClubSetting {
      if (!$this->excludedActivitiesFromOpeningDays->contains($excludedActivitiesFromOpeningDay)) {
          $this->excludedActivitiesFromOpeningDays->add($excludedActivitiesFromOpeningDay);
      }
      return $this;
  }

  public function removeExcludedActivitiesFromOpeningDay(Activity $excludedActivitiesFromOpeningDay): ClubSetting {
      $this->excludedActivitiesFromOpeningDays->removeElement($excludedActivitiesFromOpeningDay);
      return $this;
  }

  public function getLogo(): ?File {
    return $this->logo;
  }

  public function setLogo(?File $logo): ClubSetting {
    $this->logo = $logo;
    return $this;
  }

  public function getSeasonEnd(): string {
    return $this->seasonEnd;
  }

  public function setSeasonEnd(string $seasonEnd): ClubSetting {
    // We force to always be in the mm-dd format
    $exploded = explode('-', $seasonEnd, 2);
    if (count($exploded) === 2) {
      if (strlen($exploded[0]) < 2) {
        $exploded[0] = '0' . $exploded[0];
      }

      if (strlen($exploded[1]) < 2) {
        $exploded[1] = '0' . $exploded[1];
      }
    }

    $seasonEnd = implode('-', $exploded);

    $this->seasonEnd = $seasonEnd;
    return $this;
  }

  public function getCurrentSeason(): ?Season {
    return $this->currentSeason;
  }

  public function setCurrentSeason(?Season $currentSeason): ClubSetting {
    $this->currentSeason = $currentSeason;
    return $this;
  }

  public function getActivity(): ClubActivity {
    return $this->activity;
  }

  public function setActivity(ClubActivity $activity): ClubSetting {
    $this->activity = $activity;
    return $this;
  }
}
