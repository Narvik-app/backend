<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Repository\ClubRepository;
use Symfony\Component\HttpFoundation\Request;

final readonly class RequestService {
  public function __construct(
    private ClubRepository $clubRepository,
  ) {
  }

  public function getSelectedProfileFromRequest(Request $request): ?string {
    return $request->headers->get('Profile');
  }

  public function getClubUuidFromRequest(Request $request): ?string {
    $uuid = $request->attributes->get("clubUuid");
    $resourceClass = $request->attributes->get('_api_resource_class');

    if (!$uuid && $resourceClass === Club::class) {
      $uuid = $request->attributes->get('uuid');
    }

    if (!$uuid) {
      if ($resourceClass && is_subclass_of($resourceClass, ClubLinkedEntityInterface::class)) {
        // We try getting the information from the body
        if ($request->getMethod() === Request::METHOD_POST) {
          $json = json_decode($request->getContent(), true);
          if ($json && array_key_exists('club', $json)) {
            $clubJson = $json['club'];
            if (is_string($clubJson)) {
              $uuid = substr($clubJson, strlen("/clubs/"));
            }
          }
        /*} elseif ($request->getMethod() === Request::METHOD_PATCH) {
          $uuid = $request->attributes->get("uuid");
          dump($uuid);*/
        } else {
          dump("Unsupported request method");
        }
      }
    }

    return $uuid;
  }

  public function getClubFromRequest(Request $request, bool $restrainedToOwn = true): ?Club {
    $uuid = $this->getClubUuidFromRequest($request);

    if ($uuid) {
      if ($restrainedToOwn) {
        return $this->clubRepository->findOneByUuidRestrained($uuid);
      }
      return $this->clubRepository->findOneByUuid($uuid);
    }
    return null;
  }
}
