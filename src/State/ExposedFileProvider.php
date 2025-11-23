<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\FileService;
use App\Service\ImageService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExposedFileProvider implements ProviderInterface {

  public function __construct(
    private readonly FileService $fileService,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null {
    if ($operation instanceof CollectionOperationInterface) {
      return null;
    }

    $response = null;

    $isInline = false;
    if (str_starts_with((string) $operation->getName(), 'inline_')) {
      $isInline = true;
    }

    if (str_contains((string) $operation->getName(), 'public_image')) {
      $response = $this->fileService->loadFileFromPublicPath($uriVariables['id'], $isInline);
    } else {
      $response = $this->fileService->loadFileFromProtectedPath($uriVariables['id'], $isInline);
    }

    if ($response && $isInline) {
      // We return the image directly
      return new BinaryFileResponse($response->getPath());
    }

    return $response;
  }


}
