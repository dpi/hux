<?php

declare(strict_types=1);

namespace Drupal\hux\Attribute;

/**
 * An alter.
 */
#[\Attribute]
class Alter {

  /**
   * Constructs a new Alter.
   *
   * @param string $alter
   *   The alter name, without the 'hook_' or '_alter' components.
   */
  public function __construct(
    public string $alter,
  ) {
    assert(!str_starts_with($alter, 'hook_'));
    assert(!str_ends_with($alter, '_alter'));
  }

}
