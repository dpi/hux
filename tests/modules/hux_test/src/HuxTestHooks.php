<?php

declare(strict_types=1);

namespace Drupal\hux_test;

use Drupal\hux\Attribute\Hook;

final class HuxTestHooks {

  /**
   * Implements hook_test_hook().
   *
   * Tests a hook without any side effects.
   */
  #[Hook('test_hook')]
  public function testHook(string $something): void {
    HuxTestCallTracker::record([__CLASS__, __FUNCTION__, $something]);
  }

  /**
   * Implements hook_test_hook_returns().
   *
   * Tests a hook with output side effects.
   */
  #[Hook('test_hook_returns')]
  public function testHookReturns(): string {
    HuxTestCallTracker::record([__CLASS__, __FUNCTION__]);
    return __FUNCTION__ . ' return';
  }

}
