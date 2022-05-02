<?php

declare(strict_types=1);

namespace Drupal\hux_alter_test;

use Drupal\hux\Attribute\Alter;
use Drupal\hux_test\HuxTestCallTracker;

/**
 * Test hooks.
 */
final class HuxAlterTestHooks {

  /**
   * Implements hook_fizz_alter().
   */
  #[Alter('fizz')]
  public function testAlter1(&$data, &$context1, &$context2): void {
    HuxTestCallTracker::record([__CLASS__, __FUNCTION__, $data, $context1, $context2]);
    $data = __FUNCTION__ . ' hit';
  }

  /**
   * Implements hook_buzz_alter().
   */
  #[Alter('buzz')]
  public function testAlter2(&$data, &$context1, &$context2): void {
    HuxTestCallTracker::record([__CLASS__, __FUNCTION__, $data, $context1, $context2]);
    $data = __FUNCTION__ . ' hit';
  }

}
