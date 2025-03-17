<?php

namespace App\Controller\ClubDependent;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Service\ImportItacCsvService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MemberImportSecondaryClubFromItac extends AbstractClubDependentController {

  public function __invoke(Request $request, ImportItacCsvService $itacCsvService): JsonResponse {
    /** @var UploadedFile|null $uploadedFile */
    $uploadedFile = $request->files->get('file');
    if (!$uploadedFile) {
      throw new BadRequestHttpException('"file" is required');
    }

    if (strtolower($uploadedFile->getClientOriginalExtension()) !== "csv") {
      throw new BadRequestHttpException('The "file" must be a CSV');
    }

    $response = $itacCsvService->importSecondaryFromFile($this->getClub($request), $uploadedFile->getPathname());

    return new JsonResponse(["lines" => $response]);
  }

}
