<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\User;
use App\Entity\UserMember;
use App\Enum\ClubActivity;
use App\Enum\UserSecurityCodeTrigger;
use App\Repository\ClubRepository;
use App\Repository\UserRepository;
use App\Repository\UserSecurityCodeRepository;
use App\Service\ClubService;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BadgerQuickLogin extends AbstractController {
  public function __construct(
    private readonly UserSecurityCodeRepository $userSecurityCodeRepository,
    private readonly UserService $userService,
  ) {
  }


  public function __invoke(Request $request): JsonResponse {
    $payload = $this->checkAndGetJsonValues($request, ['securityCode']);

    $securityCode = $payload['securityCode'];
    $userSecurityCode = $this->userSecurityCodeRepository->findOneBySecurityCode($securityCode, UserSecurityCodeTrigger::badgerQuickLogin);
    $user = $userSecurityCode?->getUser();

    /** @var UserMember|null $userMember */
    $userMember = $user?->getMemberships()->first();
    $club = $userMember?->getBadgerClub();

    if (!$user || !$club) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, "Invalid security code.");
    }

    // We can now consume the security code
    $this->userService->consumeAllSecurityCodes($user, UserSecurityCodeTrigger::badgerQuickLogin);

    // We get the badger quick login url
    $data = [
      'club' => $club->getUuid(),
      'token' => $club->getBadgerToken(),
    ];

    return new JsonResponse($data);
  }

}
