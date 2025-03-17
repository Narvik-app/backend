<?php

namespace App\Controller\ClubDependent;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Service\ImportEdenService;
use App\Service\ImportItacCsvService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MemberImportFromEden extends AbstractClubDependentController {

  public function __invoke(Request $request, ImportEdenService $edenService): JsonResponse {
    /** @var UploadedFile|null $uploadedFile */
    $uploadedFile = $request->files->get('file');
    if (!$uploadedFile) {
      throw new BadRequestHttpException('"file" is required');
    }

    if (strtolower($uploadedFile->getClientOriginalExtension()) !== "xlsx") {
      throw new BadRequestHttpException('The "file" must be an XLSX.');
    }

    $response = $edenService->importFromFile($this->getClub($request), $uploadedFile->getPathname());

    return new JsonResponse(["lines" => $response]);
  }

}
