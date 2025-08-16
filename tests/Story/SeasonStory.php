<?php

namespace App\Tests\Story;

use App\Entity\Club;
use App\Entity\Season;
use App\Service\SeasonService;
use App\Tests\Factory\SeasonFactory;
use Zenstruck\Foundry\Story;

/**
 * @method static Season season_2019()
 * @method static Season season_2020()
 * @method static Season season_2021()
 * @method static Season season_2022()
 * @method static Season season_2023()
 * @method static Season season_previous()
 * @method static Season season_current()
 */
final class SeasonStory extends Story {
  public const SEASONS = [
    "2015/2016",
    "2016/2017",
    "2017/2018",
    "2018/2019",
    "2019/2020",
    "2020/2021",
    "2021/2022",
  ];

  public function build(): void {
    foreach (self::SEASONS as $season) {
      $this->addState('season_' . substr($season, 0, 4), SeasonFactory::createOne([
        'name' => $season
      ]), 'seasons');
    }

    // We dynamically set current and previous season (current season will have a end date of 31 december)
    $this->addState('season_previous', SeasonFactory::findOrCreate([
      'name' => SeasonService::getPreviousSeasonName()
    ]), 'seasons');

    $this->addState('season_current', SeasonFactory::findOrCreate([
      'name' => SeasonService::getCurrentSeasonName()
    ]), 'seasons');
  }
}
