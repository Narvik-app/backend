<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\File;
use App\Service\FileService;
use App\Service\UuidService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;

#[AsEntityListener(entity: File::class)]
class FileSubscriber extends AbstractEventSubscriber {
  public function __construct(
    private readonly FileService $fileService,
  ) {
  }


  public function postLoad(File $file, PostLoadEventArgs $args): void {
    $this->fileService->generateFileLinks($file);
  }

  public function postRemove(File $file, PostRemoveEventArgs $args): void {
    $this->fileService->remove($file);
  }
}
