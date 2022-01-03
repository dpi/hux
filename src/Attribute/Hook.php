<?php

declare(strict_types=1);

namespace Drupal\hux\Attribute;

/**
 * A hook.
 */
#[\Attribute]
class Hook {

  /**
   * Constructs a new Hook.
   *
   * @param string $hook
   *   The hook name, without the 'hook_' component.
   * @param string|null $moduleName
   *   The module name, or NULL to use module name determined from class
   *   namespace. This is helpful if you are implementing the hook multiple
   *   times per module, and the invoking code has module-name specific code
   *   which may break if there are multiple invokations per module.
   * @param int $priority
   *   The order this hook should be executed. Larger numbers are executed
   *   first.
   */
  public function __construct(
    public string $hook,
    public ?string $moduleName = NULL,
    public int $priority = 0,
  ) {
    // Specifically look for 'hook_' at position zero.
    assert(strpos($hook, 'hook_') !== 0);
  }

}
