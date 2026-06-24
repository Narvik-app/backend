<?php

namespace App\Controller\ClubDependent\Plugin\Loan;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Service\ImageService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class LoanItemImageUpdate extends AbstractClubDependentController {

  public function __invoke(
    Request $request,
    #[MapEntity(mapping: ['uuid' => 'uuid'])]
    LoanItem $loanItem,
    ImageService $imageService
  ): JsonResponse {
    /** @var UploadedFile|null $uploadedFile */
    $uploadedFile = $request->files->get('file');
    if ($uploadedFile && !in_array(strtolower($uploadedFile->getClientOriginalExtension()), ['png', 'jpg', 'jpeg', 'webp'])) {
      throw new BadRequestHttpException('The "file" must be png / jpg / jpeg / webp.');
    }

    $imageService->importLoanItemImage($loanItem, $uploadedFile);
    return new JsonResponse();
  }

}
