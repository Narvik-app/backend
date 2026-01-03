<?php

namespace App\Controller\Abstract;

use App\Entity\ClubDependent\Member;
use App\Entity\Interface\SortableEntityInterface;
use App\Entity\User;
use App\Repository\Interface\SortableRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SortableController extends AbstractClubDependentController {
  public function move(Request $request, SortableEntityInterface $entity, SortableRepositoryInterface $repository): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof User) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    $payload = $this->checkAndGetJsonValues($request, ['direction']);
    $direction = strtolower((string) $payload['direction']);

    if (!in_array($direction, ['up', 'down'])) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, "Direction must be 'up' or 'down'");
    }

    if ($direction === 'up') {
      $repository->moveUp($this->getClub($request), $entity);
    } else {
      $repository->moveDown($this->getClub($request), $entity);
    }

    return new JsonResponse();
  }

  public function reorder(Request $request, SortableRepositoryInterface $repository): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof User) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    $payload = $this->checkAndGetJsonValues($request, ['uuids']);
    $uuids = $payload['uuids'];

    if (!is_array($uuids)) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, "UUIDs must be an array");
    }

    $repository->reorder($this->getClub($request), $uuids);

    return new JsonResponse();
  }
}
