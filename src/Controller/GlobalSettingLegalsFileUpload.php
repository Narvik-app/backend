<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Controller\Abstract\AbstractPdfUploadController;
use App\Entity\File;
use App\Enum\FileCategory;
use App\Enum\GlobalSetting;
use App\Repository\FileRepository;
use App\Service\FileService;
use App\Service\GlobalSettingService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GlobalSettingLegalsFileUpload extends AbstractPdfUploadController {


  public function __construct(
    private readonly GlobalSettingService $globalSettingService,
    private readonly FileRepository $fileRepository,
    private readonly FileService $fileService,
    private readonly EntityManagerInterface $em,
  ) {
  }

  public function __invoke(Request $request, FileService $fileService): File {
    $type = $request->request->get('type');

    $allowedTypes = [
      'cgu',
      'cgv',
      'privacy-policy',
    ];

    if (!$type || !in_array($type, $allowedTypes)) {
      throw new BadRequestHttpException("Missing or wrong type field.");
    }

    $uploadedFile = $this->validateFileUpload($request);

    $file = $fileService->importFile($uploadedFile, 'cgu.pdf', FileCategory::legals, true);
    $this->fileService->generateFileLinks($file);

    if ($type === 'cgu') {
      $this->updateFile($file, GlobalSetting::LEGALS_CGU);
    } elseif ($type === 'cgv') {
      $this->updateFile($file, GlobalSetting::LEGALS_CGV);
    } else {
      $this->updateFile($file, GlobalSetting::LEGALS_PRIVACY_POLICY);
    }

    return $file;
  }

  private function updateFile(File $file, GlobalSetting $fileType): void {
    $oldFile = $this->globalSettingService->getSettingValue($fileType);
    if ($oldFile) {
      $dbOldFile = $this->fileRepository->findOneByUuid($this->fileService->decodeEncodedUriId($oldFile));
      if ($dbOldFile) {
        $this->em->remove($dbOldFile);
      }
    }

    $this->globalSettingService->updateSettingValue($fileType, $file->getEncodedUuid());
  }

}
