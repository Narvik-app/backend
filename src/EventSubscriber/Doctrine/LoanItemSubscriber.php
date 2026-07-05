<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Repository\ClubDependent\Plugin\Loan\LoanRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;

#[AsEntityListener(entity: LoanItem::class)]
class LoanItemSubscriber extends AbstractEventSubscriber {
  public function __construct(private readonly LoanRepository $loanRepository) {
  }

  public function postLoad(LoanItem $loanItem, PostLoadEventArgs $args): void {
    $loanItem->setIsCurrentlyLoaned($this->loanRepository->countOpenByItem($loanItem) > 0);
    $loanItem->setTimesLoaned($this->loanRepository->countByItem($loanItem));
  }
}
