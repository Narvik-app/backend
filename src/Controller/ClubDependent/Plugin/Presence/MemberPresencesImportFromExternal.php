<?php

namespace App\Controller\ClubDependent\Plugin\Presence;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Service\MemberPresenceService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MemberPresencesImportFromExternal extends AbstractClubDependentController {

  public function __invoke(Request $request, MemberPresenceService $memberPresenceService): JsonResponse {
    $totalImported = $memberPresenceService->importFromExternalPresence($this->getClub($request));
    return new JsonResponse(["imported" => $totalImported]);
  }

}
