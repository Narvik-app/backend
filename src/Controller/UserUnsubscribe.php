<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserUnsubscribe extends AbstractController {

  public function __invoke(Request $request, ClubRepository $clubRepository, MemberRepository $memberRepository, EntityManagerInterface $em): JsonResponse {
    $payload = $this->checkAndGetJsonValues($request, ['club', 'email']);

    // We get the club
    /** @var Club|null $club */
    $club = $clubRepository->findOneByUuid($payload['club']);
    if (!$club) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'Club not found.');
    }

    /** @var Member|null $member */
    $member = $memberRepository->findOneByEmail($club, $payload['email']);
    if (!$member) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'Member not found.');
    }

    $member->setClubNewsletter(false);

    $em->persist($member);
    $em->flush();

    return new JsonResponse();
  }

}
