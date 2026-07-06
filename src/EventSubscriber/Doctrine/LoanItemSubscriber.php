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
    $counts = $this->loanRepository->getUsageCounts($loanItem);
    $loanItem->setIsCurrentlyLoaned($counts['open'] > 0);
    $loanItem->setTimesLoaned($counts['total']);
  }
}
