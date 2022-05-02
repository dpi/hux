<?php

declare(strict_types=1);

namespace Drupal\hux;

use Drupal\hux\Attribute\Alter;
use Drupal\hux\Attribute\Hook;
use Drupal\hux\Attribute\ReplaceOriginalHook;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hux discovery.
 *
 * Discovers hooks, hook replacements, and alters in tagged services. This class
 * makes it easier to implement hook caching, potentially eliminating the need
 * to initialize some hook classes which are not utilized. Switching off caching
 * allows developers to quickly add new hooks to hook classes without the need
 * to clear the entire cache.
 *
 * @internal
 *   For internal use only, behavior and serialized data structure may change at
 *   any time.
 */
final class HuxDiscovery {

  /**
   * @var array<class-string, array<mixed>>
   */
  protected array $discovery = [];

  private ?array $implementations = NULL;

  /**
   * Constructs a new HuxDiscovery.
   *
   * @param array<string, array{string, string}> $implementations
   *   An array of module names and class names keyed by service ID.
   */
  public function __construct(array $implementations) {
    $this->implementations = $implementations;
  }

  /**
   * Discovers hook implementations in hook classes.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   *   If a service implementation was added but was removed unexpectedly.
   */
  public function discovery(ContainerInterface $container): void {
    if (!isset($this->implementations)) {
      throw new \Exception('Hook implementations were cleared after serialization. Re-construct the discovery class.');
    }

    $this->discovery = [];

    foreach ($this->implementations as $serviceId => [$moduleName, $className]) {
      $reflectionClass = new \ReflectionClass($className ?? $container->get($serviceId));
      $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

      foreach ($methods as $reflectionMethod) {
        $methodName = $reflectionMethod->getName();

        $attributesHooks = $reflectionMethod->getAttributes(Hook::class);
        if ($attribute = $attributesHooks[0] ?? NULL) {
          $instance = $attribute->newInstance();
          assert($instance instanceof Hook);
          $this->discovery[Hook::class][$instance->hook][] = [
            $serviceId,
            $instance->moduleName ?? $moduleName,
            $methodName,
            $instance->priority,
          ];
        }

        $attributesHookReplacements = $reflectionMethod->getAttributes(ReplaceOriginalHook::class);
        if ($attribute = $attributesHookReplacements[0] ?? NULL) {
          $instance = $attribute->newInstance();
          assert($instance instanceof ReplaceOriginalHook);
          $this->discovery[ReplaceOriginalHook::class][$instance->hook][] = [
            $serviceId,
            $instance->moduleName,
            $methodName,
            $instance->originalInvoker,
          ];
        }

        $attributesAlters = $reflectionMethod->getAttributes(Alter::class);
        if ($attribute = $attributesAlters[0] ?? NULL) {
          $instance = $attribute->newInstance();
          assert($instance instanceof Alter);
          $this->discovery[Alter::class][$instance->alter][] = [
            $serviceId,
            $methodName,
          ];
        }
      }
    }
  }

  /**
   * Get all Hux implementations of a hook.
   *
   * @param string $hook
   *   A hook.
   *
   * @return \Generator<array{string, string, string, int}>
   *   A generator yielding an array of service ID, module name, method name,
   *   and priority.
   */
  public function getHooks(string $hook) {
    yield from $this->discovery[Hook::class][$hook] ?? [];
  }

  /**
   * Get all Hux implementations of replacement hooks.
   *
   * @param string $hook
   *   A hook.
   *
   * @return \Generator<array{string, string, string, bool}>
   *   A generator yielding an array of service ID, module name, method name,
   *   and flag for whether the original implementation should be passed as a
   *   callable as first parameter.
   */
  public function getHookReplacements(string $hook) {
    yield from $this->discovery[ReplaceOriginalHook::class][$hook] ?? [];
  }

  /**
   * Get all Hux implementations of alters hooks.
   *
   * @param string $alter
   *   An alter.
   *
   * @return \Generator<array{string, string, string}>
   *   A generator yielding an array of service ID,  method name.
   */
  public function getAlters(string $alter) {
    yield from $this->discovery[Alter::class][$alter] ?? [];
  }

  /**
   * Optimises the object by removing $this->implementations.
   */
  public function __sleep(): array {
    return ['discovery'];
  }

}
