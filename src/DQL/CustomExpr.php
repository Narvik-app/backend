<?php

namespace App\DQL;

use Doctrine\ORM\Query\Expr;

class CustomExpr {

  /**
   * Remove accent on char, for exemple é => e
   * @see https://www.postgresql.org/docs/current/unaccent.html
   *
   * @param string $x
   * @return string
   */
  public static function unaccent(string $x): string {
    return new Expr\Func('unaccent', [$x]);
  }

  /**
   * Remove accent and convert it to lowercase.
   * Useful for search query
   *
   * @param string $x
   * @return string
   */
  public static function unaccentInsensitive(string $x): string {
    return self::unaccent(new Expr\Func('LOWER', [$x]));
  }

  public static function dayOfWeek(string $x) {
    return new Expr\Func('dayofweek', [$x]);
  }

  public static function percentile(int $x, string $expr) {
    return new Expr\Func('percentile', [$x, $expr]);
  }
}
