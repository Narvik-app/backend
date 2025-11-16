<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\ClubRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class RequestService {
  public function __construct(
    private ClubRepository $clubRepository,
    private TokenStorageInterface $tokenStorage,
    private RequestStack $requestStack,
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

  /**
   * Retrieves the active profile for the current user based on the request.
   *
   * @param Request|null $request The HTTP request to check for a selected profile header. If not set it will get it from the request stack.
   * @return Profile|null The active profile if found and valid.
   * @throws HttpException If no matching profile is found or if multiple profiles exist but none is selected.
   */
  public function getActiveProfile(?Request $request = null): ?Profile {
    $request = $request ?? $this->requestStack->getCurrentRequest();

    $user = $this->tokenStorage->getToken()?->getUser();
    if (!$user instanceof User) {
      return null;
    }

    $selectedProfile = $this->getSelectedProfileFromRequest($request);
    $linkedProfiles = $user->getLinkedProfiles();

    // Profile is selected in the header
    if ($selectedProfile) {
      foreach ($linkedProfiles as $linkedProfile) {
        if (!$linkedProfile->getId() || $linkedProfile->getId() !== $selectedProfile) {
          continue;
        }
        return $linkedProfile;
      }
      throw new HttpException(Response::HTTP_FORBIDDEN, "No matching profile found.");
    }

    // No profile selected, and we got multiple, we throw an exception here
    if (count($linkedProfiles) > 1) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, "Missing required 'Profile' header.");
    }

    return $linkedProfiles->first();
  }
}
