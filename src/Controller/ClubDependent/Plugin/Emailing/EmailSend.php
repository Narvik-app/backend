<?php

namespace App\Controller\ClubDependent\Plugin\Emailing;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Entity\ClubDependent\Plugin\Emailing\Email;
use App\Entity\User;
use App\Service\ClubService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmailSend extends AbstractClubDependentController {
  public function __construct(
    RequestService $requestService,
    private readonly ClubService $clubService) {
    parent::__construct($requestService);
  }


  public function __invoke(Request $request, EntityManagerInterface $em): Email {
    $user = $this->getUser();
    if (!$user instanceof User) {
      throw new NotFoundHttpException(); // User not logged, should never happen
    }

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

    $club = $this->getClub($request);

    $title = $request->request->get('title');
    $content = $request->request->get('content');
    $members = $request->request->get('members');

    $isNewsLetter = $this->toBoolean($request->request->get('isNewsletter') ?? '1');
    $replyTo = $request->request->get('replyTo');
    $file = $this->getUploadedFile($request);

    $email = new Email();
    $email
      ->setClub($club)
      ->setSender($user->getEmail())
      ->setReplyTo($replyTo ?? $club->getSettings()->getEmailReplyTo() ?? $club->getContactEmail())
      ->setIsNewsletter($isNewsLetter)
      ->setMembers(explode(",", $members))
      ->setTitle($title)
      ->setContent($content)
      ->setAttachment($file);

    // We send it
    $sent = $this->clubService->sendClubEmail($club, $email);

    // We persist the email object
    $em->persist($email);
    $em->flush();

    if (!$sent) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, $email->getExplanation());
    }

    return $email;
  }

}
