<?php

namespace App\Entity\ClubDependent\Plugin\Loan;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
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
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\Permission;
use App\Repository\ClubDependent\Plugin\Loan\LoanRepository;
use App\Validator\Constraints\LoanItemNotAlreadyLoaned;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanRepository::class)]
#[LoanItemNotAlreadyLoaned]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/loans/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/loans.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::LOAN_ACCESS->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/loans.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::LOAN_EDIT->value."', request)",
      read: false
    ),

    new Get(
      security: "is_granted('".Permission::LOAN_ACCESS->value."', object)",
    ),
    new Patch(
      security: "is_granted('".Permission::LOAN_EDIT->value."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::LOAN_EDIT->value."', object)",
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['loan', 'loan-read', 'autocomplete']
  ],
  denormalizationContext: [
    'groups' => ['loan', 'loan-write', 'timestamp-write-create']
  ],
  order: ['startDate' => 'DESC'],
  paginationClientEnabled: true,
)]
#[ApiFilter(OrderFilter::class, properties: ['startDate' => 'DESC', 'endDate' => 'ASC'])]
#[ApiFilter(SearchFilter::class, properties: ['loanItem.uuid' => 'exact', 'member.uuid' => 'exact', 'author.uuid' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['startDate' => DateFilter::EXCLUDE_NULL, 'endDate'])]
#[ApiFilter(ExistsFilter::class, properties: ['endDate'])]
class Loan extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\ManyToOne(targetEntity: LoanItem::class)]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  #[Groups(['loan'])]
  #[Assert\NotNull]
  private ?LoanItem $loanItem = null;

  /** The member who borrows the item */
  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['loan'])]
  private ?Member $member = null;

  /** Free-text borrower name, used when the borrower is not a club member */
  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['loan'])]
  private ?string $borrowerName = null;

  /** The supervisor/admin who lends the item */
  #[ORM\ManyToOne(targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['loan'])]
  private ?Member $author = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  #[Groups(['loan'])]
  #[Assert\NotNull]
  private ?\DateTimeImmutable $startDate = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  #[Groups(['loan'])]
  private ?\DateTimeImmutable $endDate = null;

  #[ORM\Column(length: 1024, nullable: true)]
  #[Groups(['loan'])]
  private ?string $comment = null;

  public function __construct() {
    parent::__construct();
    $this->startDate = new \DateTimeImmutable();
  }

  public function getLoanItem(): ?LoanItem {
    return $this->loanItem;
  }

  public function setLoanItem(?LoanItem $loanItem): static {
    $this->loanItem = $loanItem;
    return $this;
  }

  public function getMember(): ?Member {
    return $this->member;
  }

  public function setMember(?Member $member): static {
    $this->member = $member;
    return $this;
  }

  public function getBorrowerName(): ?string {
    return $this->borrowerName;
  }

  public function setBorrowerName(?string $borrowerName): static {
    if (empty($borrowerName)) {
      $borrowerName = null;
    }
    $this->borrowerName = $borrowerName;
    return $this;
  }

  public function getAuthor(): ?Member {
    return $this->author;
  }

  public function setAuthor(?Member $author): static {
    $this->author = $author;
    return $this;
  }

  public function getStartDate(): ?\DateTimeImmutable {
    return $this->startDate;
  }

  public function setStartDate(\DateTimeImmutable $startDate): static {
    $this->startDate = $startDate;
    return $this;
  }

  public function getEndDate(): ?\DateTimeImmutable {
    return $this->endDate;
  }

  public function setEndDate(?\DateTimeImmutable $endDate): static {
    $this->endDate = $endDate;
    return $this;
  }

  public function getComment(): ?string {
    return $this->comment;
  }

  public function setComment(?string $comment): static {
    if (empty($comment)) {
      $comment = null;
    }
    $this->comment = $comment;
    return $this;
  }
}
