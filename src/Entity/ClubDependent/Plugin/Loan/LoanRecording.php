<?php

namespace App\Entity\ClubDependent\Plugin\Loan;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\Permission;
use App\Repository\ClubDependent\Plugin\Loan\LoanRecordingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanRecordingRepository::class)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/loan-recordings/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/loan-recordings.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::LOAN_RECORDINGS_ACCESS->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/loan-recordings.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::LOAN_RECORDINGS_EDIT->value."', request)",
      read: false
    ),

    new Get(
      security: "is_granted('".Permission::LOAN_RECORDINGS_ACCESS->value."', object)",
    ),
    new Patch(
      security: "is_granted('".Permission::LOAN_RECORDINGS_EDIT->value."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::LOAN_RECORDINGS_EDIT->value."', object)",
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['loan-recording', 'loan-recording-read', 'autocomplete']
  ],
  denormalizationContext: [
    'groups' => ['loan-recording', 'loan-recording-write', 'timestamp-write-create']
  ],
  order: ['date' => 'DESC'],
  paginationClientEnabled: true,
)]
#[ApiFilter(OrderFilter::class, properties: ['date' => 'DESC'])]
#[ApiFilter(SearchFilter::class, properties: ['loanItem.uuid' => 'exact', 'author.uuid' => 'exact', 'recordingType.uuid' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['date' => DateFilter::EXCLUDE_NULL])]
class LoanRecording extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\ManyToOne(targetEntity: LoanItem::class)]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  #[Groups(['loan-recording'])]
  // LoanItem's uuid/createdAt/updatedAt (auto-injected common-read/timestamp groups) would
  // otherwise make this embed as a full object, breaking the declared IRI-string API schema.
  #[ApiProperty(readableLink: false)]
  #[Assert\NotNull]
  private ?LoanItem $loanItem = null;

  #[ORM\Column(length: 2048, nullable: true)]
  #[Groups(['loan-recording'])]
  private ?string $description = null;

  #[ORM\ManyToOne(targetEntity: LoanRecordingType::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['loan-recording'])]
  private ?LoanRecordingType $recordingType = null;

  /** The supervisor/admin who performed the action */
  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['loan-recording'])]
  private ?Member $author = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  #[Groups(['loan-recording'])]
  #[Assert\NotNull]
  private ?\DateTimeImmutable $date = null;

  public function __construct() {
    parent::__construct();
    $this->date = new \DateTimeImmutable();
  }

  public function getLoanItem(): ?LoanItem {
    return $this->loanItem;
  }

  public function setLoanItem(?LoanItem $loanItem): static {
    $this->loanItem = $loanItem;
    return $this;
  }

  public function getDescription(): ?string {
    return $this->description;
  }

  public function setDescription(?string $description): static {
    if (empty($description)) {
      $description = null;
    }
    $this->description = $description;
    return $this;
  }

  public function getRecordingType(): ?LoanRecordingType {
    return $this->recordingType;
  }

  public function setRecordingType(?LoanRecordingType $recordingType): static {
    $this->recordingType = $recordingType;
    return $this;
  }

  public function getAuthor(): ?Member {
    return $this->author;
  }

  public function setAuthor(?Member $author): static {
    $this->author = $author;
    return $this;
  }

  public function getDate(): ?\DateTimeImmutable {
    return $this->date;
  }

  public function setDate(\DateTimeImmutable $date): static {
    $this->date = $date;
    return $this;
  }
}
