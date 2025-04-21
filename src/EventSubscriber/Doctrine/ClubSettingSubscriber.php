<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\ClubSetting;
use App\Repository\SeasonRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;

#[AsEntityListener(entity: ClubSetting::class)]
class ClubSettingSubscriber extends AbstractEventSubscriber {
  public function __construct(
    private readonly SeasonRepository $seasonRepository,
  ) {
  }

  public function postLoad(ClubSetting $clubSetting, PostLoadEventArgs $args): void {
    $currentSeason = $this->seasonRepository->findCurrentSeason($clubSetting->getClub());
    $clubSetting->setCurrentSeason($currentSeason);
  }
}
