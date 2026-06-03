<?php

namespace App\Repository\ClubDependent\Plugin\Sale;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalePaymentTerminal>
 */
class SalePaymentTerminalRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, SalePaymentTerminal::class);
  }
}
