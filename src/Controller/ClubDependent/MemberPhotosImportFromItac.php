<?php

namespace App\Controller\ClubDependent;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Service\ImageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MemberPhotosImportFromItac extends AbstractClubDependentController {

  public function __invoke(Request $request, ImageService $importPhotosService): JsonResponse {
    /** @var UploadedFile|null $uploadedFile */
    $uploadedFile = $request->files->get('file');
    if (!$uploadedFile) {
      throw new BadRequestHttpException('"file" is required');
    }

    if (strtolower($uploadedFile->getClientOriginalExtension()) !== "zip") {
      throw new BadRequestHttpException('The "file" must be a ZIP');
    }

    $importPhotosService->importItacPhotos($this->getClub($request), $uploadedFile);

    return new JsonResponse();
  }

}
