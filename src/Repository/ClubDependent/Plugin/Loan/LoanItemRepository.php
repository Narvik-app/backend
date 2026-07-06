<?php

namespace App\Repository\ClubDependent\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Interface\SortableRepositoryInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\SortableEntityRepositoryTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanItem>
 */
class LoanItemRepository extends ServiceEntityRepository implements SortableRepositoryInterface, ClubLinkedInterface {
  use SortableEntityRepositoryTrait;
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, LoanItem::class);
  }

}
