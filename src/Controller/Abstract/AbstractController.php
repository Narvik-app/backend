<?php

namespace App\Controller\Abstract;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class AbstractController extends SymfonyAbstractController {

  /**
   * Return an array with all the json value
   * Also check if required parameters are well present
   *
   * @param Request $request
   * @param array $requiredParams
   * @return array
   */
  protected function checkAndGetJsonValues(Request $request, array $requiredParams = []): array {
    $json = $this->getJsonBody($request);

    foreach ($requiredParams as $requiredParam) {
      if (!array_key_exists($requiredParam, $json)) {
        throw new HttpException(Response::HTTP_BAD_REQUEST, "Missing required field: '$requiredParam'");
      }
    }
    return $json;
  }

  protected function getJsonBody(Request $request, bool $required = true): array {
    $json = json_decode($request->getContent(), true);
    if (!$json && $required) throw new HttpException(Response::HTTP_BAD_REQUEST, "Body must be in json");
    return $json;
  }

  protected function toBoolean($value): bool {
    return is_bool($value) ? $value : !in_array(strtolower((string) $value), ['', '0', 'false']);
  }

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
