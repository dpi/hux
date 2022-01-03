<?php

declare(strict_types=1);

namespace Drupal\hux\Attribute;

/**
 * A hook.
 *
 * This instructs the module handler to ignore the original procedural
 * hook implementation and replace it with this implementation.
 *
 * The original implementation will be passed as the last argument.
 *
 * This does not extend the Hook attribute to simplify things.
 */
#[\Attribute]
final class ReplaceOriginalHook {

  /**
   * Constructs a ReplaceOriginalHook.
   *
   * @param string $hook
   *   The hook to implement.
   * @param string $moduleName
   *   The original implementation module name.
   * @param bool $originalInvoker
   *   Whether you want to receive a callable to the original implementation as
   *   the first parameter.
   */
  public function __construct(
    public string $hook,
    public string $moduleName,
    public bool $originalInvoker = FALSE,
  ) {
  }

}
