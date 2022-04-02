<?php

declare(strict_types=1);

namespace Drupal\hux_auto_test\Hooks;

use Drupal\hux\Attribute\Hook;
use Drupal\hux_test\HuxTestCallTracker;

/**
 * A hooks class with a single hook.
 */
final class HuxAutoSingle {

  #[Hook('test_hook')]
  public function testHook(string $something): void {
    HuxTestCallTracker::record([__CLASS__, __FUNCTION__, $something]);
  }

}
