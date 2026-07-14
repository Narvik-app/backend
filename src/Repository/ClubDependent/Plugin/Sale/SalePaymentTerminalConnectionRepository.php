<?php

namespace App\Repository\ClubDependent\Plugin\Sale;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminalConnection;
use App\Repository\Interface\ClubLinkedInterface;
use App\Repository\Trait\ClubLinkedTrait;
use App\Repository\Trait\UuidEntityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalePaymentTerminalConnection>
 */
class SalePaymentTerminalConnectionRepository extends ServiceEntityRepository implements ClubLinkedInterface {
  use UuidEntityRepositoryTrait;
  use ClubLinkedTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, SalePaymentTerminalConnection::class);
  }
}
