<?php

namespace App\Controller\ClubDependent\Plugin\Emailing;

use App\Controller\Abstract\AbstractFileUploadController;
use App\Entity\ClubDependent\Plugin\Emailing\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class EmailSend extends AbstractFileUploadController {

  public function __construct(
    private readonly EntityManagerInterface $em,
  ) {
  }

  public function __invoke(Request $request): Email {

    $requiredFields = [
      'title',
      'content',
      'members',
      'isNewsletter' // Optionnale should not be here
    ];
    $json = $this->checkAndGetJsonValues($request, $requiredFields); // If not working
    // $title = $request->request->get('title');

    $uploadedFile = $this->validateFileUpload($request);

    $email = new Email();

    return $email;
  }

}
