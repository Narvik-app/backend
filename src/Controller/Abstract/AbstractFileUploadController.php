<?php

namespace App\Controller\Abstract;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractFileUploadController extends AbstractController {

  public function validateFileUpload(Request $request, string $fieldName = 'file'): UploadedFile {
    $uploadedFile = $this->getUploadedFile($request, $fieldName);

    if (!$uploadedFile) {
      throw new BadRequestHttpException('The "'.$fieldName.'" field is required.');
    }

    return $uploadedFile;
  }

  public function getUploadedFile(Request $request, string $fieldName = 'file'): ?UploadedFile {
    /** @var UploadedFile|null $uploadedFile */
    return $request->files->get($fieldName);
  }
}
