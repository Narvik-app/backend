<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserSelfLegalsAccepted extends AbstractController {

  public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof User || $user->getRole() === UserRole::badger) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    $user->setLegalsAccepted(new \DateTimeImmutable());

    $em->persist($user);
    $em->flush();

    return new JsonResponse();
  }

}
