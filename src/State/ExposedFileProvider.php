<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Enum\ThumbnailSize;
use App\Service\FileService;
use App\Service\ImageService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class ExposedFileProvider implements ProviderInterface {

  public function __construct(
    private readonly FileService $fileService,
    private readonly RequestStack $requestStack,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null {
    if ($operation instanceof CollectionOperationInterface) {
      return null;
    }

    $name = (string) $operation->getName();
    $isInline = str_starts_with($name, 'inline_');
    $isPublic = str_contains($name, 'public_image');
    $isThumbnail = str_contains($name, 'thumbnail');

    if ($isThumbnail) {
      $size = ThumbnailSize::tryFrom((string) $this->requestStack->getCurrentRequest()?->query->get('size')) ?? ThumbnailSize::thumbnail;
      $response = $isPublic
        ? $this->fileService->loadThumbnailFromPublicPath($uriVariables['id'], $size, $isInline)
        : $this->fileService->loadThumbnailFromProtectedPath($uriVariables['id'], $size, $isInline);
    } else {
      $response = $isPublic
        ? $this->fileService->loadFileFromPublicPath($uriVariables['id'], $isInline)
        : $this->fileService->loadFileFromProtectedPath($uriVariables['id'], $isInline);
    }

    if ($response && $isInline) {
      // We return the image directly
      return new BinaryFileResponse($response->getPath());
    }

    return $response;
  }


}
