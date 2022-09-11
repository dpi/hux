<?php

declare(strict_types=1);

namespace Drupal\hux_replacement_test;

use Drupal\hux\Attribute\ReplaceOriginalHook;
use Drupal\hux_test\HuxTestCallTracker;

/**
 * Replacement test hooks.
 */
final class HuxReplacementTestHooks {

  /**
   * Replaces hux_test_foo().
   *
   * @see hux_test_foo()
   */
  #[ReplaceOriginalHook('foo', moduleName: 'hux_test')]
  public function myReplacement(string $something): mixed {
    HuxTestCallTracker::record([__CLASS__, __FUNCTION__, $something]);
    return __FUNCTION__ . ' return';
  }

  /**
   * Replaces hux_test_foo2().
   *
   * @see hux_test_foo2()
   */
  #[ReplaceOriginalHook('foo2', moduleName: 'hux_test', originalInvoker: TRUE)]
  public function myReplacementWithOriginal(callable $originalInvoker, string $something): mixed {
    HuxTestCallTracker::record([__CLASS__, __FUNCTION__, $something]);
    $originalInvoker($something);
    return __FUNCTION__ . ' return';
  }

  /**
   * Replaces hux_test_foo3().
   * Replaces hux_test_foo4().
   *
   * Tests a hook replacement listening for multiple hooks.
   */
  #[
    ReplaceOriginalHook('foo3', moduleName: 'hux_test'),
    ReplaceOriginalHook('foo4', moduleName: 'hux_test'),
  ]
  public function testHookMultiListener(string $something): void {
    HuxTestCallTracker::record([__CLASS__, __FUNCTION__, $something]);
  }

}
