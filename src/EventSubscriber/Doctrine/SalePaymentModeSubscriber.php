<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentMode;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsEntityListener(entity: SalePaymentMode::class)]
class SalePaymentModeSubscriber extends AbstractEventSubscriber {
  public function preUpdate(SalePaymentMode $mode, PreUpdateEventArgs $args): void {
    if ($args->hasChangedField('kind')) {
      throw new BadRequestHttpException("The type of a payment method cannot be changed after it has been created.");
    }
  }
}
