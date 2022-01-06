<?php

declare(strict_types=1);

namespace Drupal\hux;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\hux\Attribute\Hook;
use Drupal\hux\Attribute\ReplaceOriginalHook;

/**
 * Hux module handler.
 *
 * Various invoke methods do not call the inner implementation since we need
 * to be able to override the original implementation, where they would normally
 * delegate invokation to an invoke method on the original class.
 */
final class HuxModuleHandler implements ModuleHandlerInterface {

  use HuxModuleHandlerProxyTrait;

  /**
   * An array of services objects and module name.
   *
   * @var array<int,array{object, string}>
   */
  private array $implementations = [];

  /**
   * An array of hook implementations.
   *
   * @var array<string, callable[]>
   */
  private array $hooks;

  /**
   * Hook replacement callables keyed by hook, then module name.
   *
   * @var array<string, array<string, \Drupal\hux\HuxReplacementHook>>
   */
  private array $hookReplacements;

  /**
   * Constructs a new HuxModuleHandler.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $inner
   *   The inner module handler.
   */
  public function __construct(
    protected ModuleHandlerInterface $inner
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function invoke($module, $hook, array $args = []) {
    $replacements = $this->getOriginalHookReplacementInvokers($hook);
    $replacement = ($replacements[$module] ?? NULL)?->getCallable(
      fn (...$args) => $this->inner->invoke($module, $hook, [...$args])
    );
    return $replacement
      ? $replacement(...$args)
      : $this->inner->invoke($module, $hook, $args);
  }

  /**
   * {@inheritdoc}
   *
   * @param string $hook
   *   The name of the hook to invoke.
   * @param callable(callable, string): mixed $callback
   *   The callback.
   */
  public function invokeAllWith(string $hook, callable $callback): void {
    $replacements = $this->getOriginalHookReplacementInvokers($hook);
    // Wrap the callback if there are any replacements.
    $callback = function (callable $hookInvoker, string $module) use ($callback, $replacements) {
      $replacement = $replacements[$module] ?? NULL;
      $hookInvoker = $replacement?->getCallable($hookInvoker) ?? $hookInvoker;
      $callback($hookInvoker, $module);
    };

    $this->inner->invokeAllWith($hook, $callback);
    $this->invokeHux($hook, $callback);
  }

  /**
   * {@inheritdoc}
   */
  public function invokeAll($hook, array $args = []) {
    // Dont use inner so we get the replacement features of our invokeAllWith.
    $return = [];
    $this->invokeAllWith($hook, function (callable $hookInvoker, string $module) use ($args, &$return) {
      $result = $hookInvoker(...$args);
      if (isset($result) && is_array($result)) {
        $return = NestedArray::mergeDeep($return, $result);
      }
      elseif (isset($result)) {
        $return[] = $result;
      }
    });
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function invokeDeprecated($description, $module, $hook, array $args = []) {
    // To get replacement features we need to use our invoke method not inner.
    $result = $this->invoke($module, $hook, $args);
    $this->triggerDeprecationError($description, $hook);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function invokeAllDeprecated($description, $hook, array $args = []) {
    // To get replacement features we need to use our invoke method not inner.
    $result = $this->invokeAll($hook, $args);
    $this->triggerDeprecationError($description, $hook);
    return $result;
  }

  /**
   * Adds a service defining hooks.
   *
   * @param object $service
   *   A service.
   * @param string $moduleName
   *   The defining module name.
   */
  public function addHookImplementation(object $service, string $moduleName): void {
    $this->implementations[] = [$service, $moduleName];
  }

  /**
   * Invokes hooks with a callable.
   *
   * @param string $hook
   *   A hook.
   * @param callable $callback
   *   The callback to execute per implementation.
   */
  private function invokeHux(string $hook, callable $callback): void {
    foreach ($this->generateInvokers($hook) as [$hookInvoker, $moduleName]) {
      $callback($hookInvoker, $moduleName);
    }
  }

  /**
   * Generates invokers for a hook.
   *
   * @param string $hook
   *   A hook.
   *
   * @return \Generator<array{callable, string}>
   *   A generator with hook callbacks and other metadata.
   */
  private function generateInvokers(string $hook) {
    if (isset($this->hooks[$hook])) {
      yield from $this->hooks[$hook];
      return;
    }

    $hooks = [];
    foreach ($this->implementations as [$service, $moduleName]) {
      $reflectionClass = new \ReflectionClass($service);
      $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

      foreach ($methods as $reflectionMethod) {
        $attributes = $reflectionMethod->getAttributes(Hook::class);
        $attribute = $attributes[0] ?? NULL;
        if ($attribute) {
          $instance = $attribute->newInstance();
          assert($instance instanceof Hook);
          if ($hook === $instance->hook) {
            $hooks[] = [
              \Closure::fromCallable([$service, $reflectionMethod->getName()]),
              $instance->moduleName ?? $moduleName,
              $instance->priority,
            ];
          }
        }
      }
    }

    usort($hooks, function (array $a, array $b) {
      [2 => $aPriority] = $a;
      [2 => $bPriority] = $b;
      return $bPriority <=> $aPriority;
    });

    // Wait for all the [sorted] callables before caching.
    $this->hooks[$hook] = $hooks;

    yield from $this->hooks[$hook];
  }

  /**
   * Generates invokers for a hook.
   *
   * @param string $hook
   *   A hook.
   *
   * @return array<string, \Drupal\hux\HuxReplacementHook>
   *   Hook replacement callables keyed module name .
   */
  private function getOriginalHookReplacementInvokers(string $hook): array {
    if (isset($this->hookReplacements[$hook])) {
      return $this->hookReplacements[$hook];
    }

    $this->hookReplacements[$hook] = [];
    foreach ($this->implementations as [$service, $moduleName]) {
      $reflectionClass = new \ReflectionClass($service);
      $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

      foreach ($methods as $reflectionMethod) {
        $attributes = $reflectionMethod->getAttributes(ReplaceOriginalHook::class);
        $attribute = $attributes[0] ?? NULL;
        if ($attribute) {
          $instance = $attribute->newInstance();
          assert($instance instanceof ReplaceOriginalHook);
          if ($hook === $instance->hook) {
            $hookInvoker = \Closure::fromCallable([
              $service,
              $reflectionMethod->getName(),
            ]);

            $this->hookReplacements[$hook][$instance->moduleName] = new HuxReplacementHook(
              $hookInvoker,
              $instance->originalInvoker,
            );
          }
        }
      }
    }

    return $this->hookReplacements[$hook];
  }

}
