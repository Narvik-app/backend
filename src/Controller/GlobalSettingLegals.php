<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Enum\GlobalSetting;
use App\Service\GlobalSettingService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class GlobalSettingLegals extends AbstractController {

  public function __invoke(Request $request, GlobalSettingService $globalSettingService) {
    $json = $this->checkAndGetJsonValues($request, ['date']);

    // We apply the settings
    $globalSettingService->updateSettingValue(GlobalSetting::LEGALS_LAST_UPDATE, $json['date']);

    return new JsonResponse();
  }

}
