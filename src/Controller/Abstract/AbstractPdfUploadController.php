<?php

namespace App\Controller\Abstract;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractPdfUploadController extends AbstractFileUploadController {

  public function validateFileUpload(Request $request, string $fieldName = 'file'): UploadedFile {
    $uploadedFile = parent::validateFileUpload($request, $fieldName);

    $allowedExtensions = ['pdf'];
    if (!in_array($uploadedFile->getClientOriginalExtension(), $allowedExtensions)) {
      throw new BadRequestHttpException('The "file" must be a PDF.');
    }

    return $uploadedFile;
  }
}
