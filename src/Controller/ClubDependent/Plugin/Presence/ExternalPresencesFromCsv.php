<?php

namespace App\Controller\ClubDependent\Plugin\Presence;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Importer\ImportExternalPresence;
use App\Importer\ImportMemberPresence;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ExternalPresencesFromCsv extends AbstractClubDependentController {

  public function __invoke(Request $request, ImportExternalPresence $importExternalPresence): JsonResponse {
    /** @var UploadedFile|null $uploadedFile */
    $uploadedFile = $request->files->get('file');
    if (!$uploadedFile) {
      throw new BadRequestHttpException('"file" is required');
    }

    if (strtolower($uploadedFile->getClientOriginalExtension()) !== "csv") {
      throw new BadRequestHttpException('The "file" must be a csv');
    }

    $importExternalPresence->setClub($this->getClub($request));
    $response = $importExternalPresence->fromFile($uploadedFile);

    return new JsonResponse($response);
  }

}
