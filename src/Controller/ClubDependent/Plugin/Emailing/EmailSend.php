<?php

namespace App\Controller\ClubDependent\Plugin\Emailing;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Entity\ClubDependent\Plugin\Emailing\Email;
use App\Service\ClubService;
use App\Service\RequestService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EmailSend extends AbstractClubDependentController {
  public function __construct(
    RequestService $requestService,
    private readonly ClubService $clubService) {
    parent::__construct($requestService);
  }


  public function __invoke(Request $request): Email {

    $requiredFields = [
      'title',
      'content',
      'members',
    ];
    foreach ($requiredFields as $requiredField) {
      if (!$request->request->has($requiredField)) {
        throw new HttpException(Response::HTTP_BAD_REQUEST, "Missing required field: '$requiredField'");
      }
    }

    $title = $request->request->get('title');
    $content = $request->request->get('content');
    $members = $request->request->get('members');

    $isNewsLetter = $this->toBoolean($request->request->get('isNewsletter') ?? '1');
    $file = $this->getUploadedFile($request);

    $email = new Email();
    $email
      ->setIsNewsletter($isNewsLetter)
      ->setTitle($title)
      ->setContent($content);

    // We send it
    $this->clubService->sendClubEmail($this->getClub($request), $email, $members);

    // We persist the email object

    return $email;
  }

}
