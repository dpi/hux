<?php

declare(strict_types=1);

namespace Drupal\hux;

/**
 * Replacement hook.
 *
 * @property callable $replacement
 */
final class HuxReplacementHook {

  /**
   * Constructs a replacement hook.
   *
   * @param callable $replacement
   *   The replacement callable.
   * @param bool $needsOriginal
   *   Whether the replacement callable needs the original invoker.
   */
  // @codingStandardsIgnoreLine
  public function __construct(
    public $replacement,
    public bool $needsOriginal,
  ) {
  }

  /**
   * Gets a callable to the replacement hook.
   *
   * @param callable $original
   *   A callable to the original hook implementation.
   *
   * @return callable
   *   A callable to the replacement hook implementation, optionally adding
   *   a callable to the original hook implementation before the argument list.
   */
  public function getCallable(callable $original): callable {
    if (!$this->needsOriginal) {
      return $this->replacement;
    }

    return function (...$args) use ($original) {
      return ($this->replacement)($original, ...$args);
    };
  }

}
