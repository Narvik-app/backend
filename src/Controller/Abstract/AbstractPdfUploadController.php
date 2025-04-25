<?php

namespace App\Controller\Abstract;

use App\Entity\Club;
use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\Service\RequestService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractPdfUploadController extends AbstractController {

  public function validateFileUpload(Request $request): UploadedFile {
    /** @var UploadedFile|null $uploadedFile */
    $uploadedFile = $request->files->get('file');

    if (!$uploadedFile) {
      throw new BadRequestHttpException('The "file" field is required.');
    }

    $allowedExtensions = ['pdf'];
    if (!in_array($uploadedFile->getClientOriginalExtension(), $allowedExtensions)) {
      throw new BadRequestHttpException('The "file" must be pdf.');
    }

    return $uploadedFile;
  }
}
