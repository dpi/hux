<?php

declare(strict_types=1);

namespace Drupal\hux;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Service provider for Hux.
 */
final class HuxServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->addCompilerPass(new HuxCompilerPass());
  }

}
