<?php

declare(strict_types=1);

namespace Drupal\hux;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\hux\Attribute\Alter;
use Drupal\hux\Attribute\Hook;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Hux compiler pass.
 *
 * Find files in src/Hooks directories in modules and adds them to the
 * container as a service with a 'hooks' tag.
 *
 * Adds services tagged with 'hooks' as a method call to the Hux module handler.
 * Drupals' service_collector cannot be used since TaggedHandlersPass requires
 * the 'call' method to implement an interface: We don't require Hook
 * implementors to implement an interface.
 */
final class HuxCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    /** @var class-string[] $hooksClasses */
    $hooksClasses = [];
    foreach ($container->findTaggedServiceIds('hooks') as $id => $tags) {
      $hooksClasses[] = $container->getDefinition($id)->getClass();
    }

    foreach ($this->getHuxClasses($container->getParameter('container.namespaces')) as $className) {
      // Don't create a service definition if this class is already a service.
      if (in_array($className, $hooksClasses, TRUE)) {
        continue;
      }

      $idSuffix = (new CamelCaseToSnakeCaseNameConverter())
        ->normalize(str_replace('\\', '_', $className));
      $definition = new Definition($className);
      $definition
        ->addTag('hooks')
        ->setPublic(TRUE);

      if ((new \ReflectionClass($className))->isSubclassOf(ContainerInjectionInterface::class)) {
        $definition
          ->setFactory([$className, 'create'])
          ->setArguments([new Reference('service_container')]);
      }

      $container->setDefinition('hux.auto.' . $idSuffix, $definition);
    }

    $huxModuleHandler = $container->findDefinition('hux.module_handler');

    $serviceIds = array_keys($container->findTaggedServiceIds('hooks'));
    $implementations = array_combine(
      $serviceIds,
      array_map(function (string $serviceId) use ($container): array {
        $serviceDefinition = $container->getDefinition($serviceId);
        /** @var class-string|null $className */
        $className = $serviceDefinition->getClass();
        preg_match_all('/^Drupal\\\\(?<moduleName>[a-z_0-9]{1,32})\\\\.*$/m', $className, $matches, PREG_SET_ORDER);
        $moduleName = $matches[0]['moduleName'] ?? throw new \Exception(sprintf('Could not determine module name from class %s', $className));
        return [$moduleName, $className];
      }, $serviceIds),
    );
    $huxModuleHandler->addMethodCall('discovery', [
      new Reference('service_container'),
      $implementations,
      new Parameter('hux'),
    ]);
  }

  /**
   * Get Hux classes for the provided namespaces.
   *
   * @param array<class-string, string> $namespaces
   *   An array of namespaces. Where keys are class strings and values are
   *   paths.
   *
   * @return \Generator<class-string>
   *   Generates class strings.
   *
   * @throws \ReflectionException
   */
  private function getHuxClasses(array $namespaces) {
    foreach ($namespaces as $namespace => $dirs) {
      $dirs = (array) $dirs;
      foreach ($dirs as $dir) {
        $dir .= '/Hooks';
        if (!file_exists($dir)) {
          continue;
        }
        $namespace .= '\\Hooks';

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $fileinfo) {
          if ($fileinfo->getExtension() !== 'php') {
            continue;
          }

          /** @var \RecursiveDirectoryIterator|null $subDir */
          $subDir = $iterator->getSubIterator();
          if (NULL === $subDir) {
            continue;
          }

          $subDir = $subDir->getSubPath();
          $subDir = $subDir ? str_replace(DIRECTORY_SEPARATOR, '\\', $subDir) . '\\' : '';
          $class = $namespace . '\\' . $subDir . $fileinfo->getBasename('.php');

          $reflectionClass = new \ReflectionClass($class);

          $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
          foreach ($methods as $reflectionMethod) {
            if (count($reflectionMethod->getAttributes(Hook::class)) > 0) {
              yield $class;
              break;
            }

            if (count($reflectionMethod->getAttributes(Alter::class)) > 0) {
              yield $class;
              break;
            }
          }
        }
      }
    }
  }

}
