<?php

declare(strict_types=1);

namespace Drupal\hux;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Hux compiler pass.
 *
 * Drupals' service_collector via TaggedHandlersPass requires the 'call' method
 * to implement an interface. We don't require Hook implementors to implement an
 * interface.
 */
final class HuxCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container) {
    $definition = $container->findDefinition('hux.module_handler');

    foreach ($container->findTaggedServiceIds('hooks') as $id => $tags) {
      $serviceDefinition = $container->getDefinition($id);
      $className = $serviceDefinition->getClass();
      preg_match_all('/^Drupal\\\\(?<moduleName>[a-z_0-9]{1,32})\\\\.*$/m', $className, $matches, PREG_SET_ORDER);
      $moduleName = $matches[0]['moduleName'] ?? throw new \Exception(sprintf('Could not determine module name from class %s', $className));

      $definition->addMethodCall('addHookImplementation', [
        $id,
        $moduleName,
      ]);
    }
  }

}
