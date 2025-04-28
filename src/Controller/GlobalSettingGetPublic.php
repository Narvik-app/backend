<?php

namespace App\Controller;

use App\Entity\GlobalSetting;
use App\Enum\GlobalSetting as GlobalSettingEnum;
use App\Repository\GlobalSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GlobalSettingGetPublic extends AbstractController {
  public const array AVAILABLE_PUBLICLY = [
    GlobalSettingEnum::LEGALS_LAST_UPDATE->name,
    GlobalSettingEnum::LEGALS_CGU->name,
    GlobalSettingEnum::LEGALS_CGV->name,
    GlobalSettingEnum::LEGALS_PRIVACY_POLICY->name,
  ];

  public function __construct(
    private readonly GlobalsettingRepository $globalSettingRepository
  ) {
  }


  public function __invoke(string $name): ?GlobalSetting {
    if (!in_array($name, self::AVAILABLE_PUBLICLY)) {
      throw new NotFoundHttpException();
    }

    return $this->globalSettingRepository->findOneByName($name);
  }

}
