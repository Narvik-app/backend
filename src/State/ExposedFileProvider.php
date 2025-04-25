<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\ImageService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageProvider implements ProviderInterface {

  public function __construct(
    private readonly ImageService $imageService,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null {
    if ($operation instanceof CollectionOperationInterface) {
      return null;
    }

    $response = null;

    $isInline = false;
    if (str_starts_with($operation->getName(), 'inline_')) {
      $isInline = true;
    }

    if (str_contains($operation->getName(), 'public_image')) {
      $response = $this->imageService->loadImageFromPublicPath($uriVariables['id'], $isInline);
    } else {
      $response = $this->imageService->loadImageFromProtectedPath($uriVariables['id'], $isInline);
    }

    if ($response && $isInline) {
      // We return the image directly
      return new BinaryFileResponse($response->getPath());
    }

    return $response;
  }


}
