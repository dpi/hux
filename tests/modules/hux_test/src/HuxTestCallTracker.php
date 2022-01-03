<?php

declare(strict_types=1);

namespace Drupal\hux_test;

final class HuxTestCallTracker {

  /**
   * @var mixed[]
   */
  public static $calls;

  public static function record(mixed $data): void {
    static::$calls[] = $data;
  }

}
