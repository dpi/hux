<?php

declare(strict_types=1);

namespace Drupal\hux;

/**
 * Replacement hook.
 *
 * @property callable $replacement
 */
final class HuxReplacementHook {

  public function __construct(
    public $replacement,
    public bool $needsOriginal,
  ) {
  }

  public function getCallable(callable $original): callable {
    if (!$this->needsOriginal) {
      return $this->replacement;
    }

    return function (...$args) use ($original) {
      return ($this->replacement)($original, ...$args);
    };
  }

}
