<?php

declare(strict_types=1);

namespace Drupal\hux;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hux module handler.
 *
 * Various invoke methods do not call the inner implementation since we need
 * to be able to override the original implementation, where they would normally
 * delegate invokation to an invoke method on the original class.
 */
final class HuxModuleHandler implements ModuleHandlerInterface {

  use ContainerAwareTrait;

  use HuxModuleHandlerProxyTrait;

  private HuxDiscovery $discovery;

  /**
   * An array of hook implementations.
   *
   * @var array<string, array{callable, string, int}>
   */
  private array $hooks = [];

  /**
   * Hook replacement callables keyed by hook, then module name.
   *
   * @var array<string, array<string, \Drupal\hux\HuxReplacementHook>>
   */
  private array $hookReplacements;

  /**
   * Alter callables keyed by alter.
   *
   * @var array<string, callable[]>
   */
  private array $alters;

  /**
   * Constructs a new HuxModuleHandler.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $inner
   *   The inner module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   A fast cache backend.
   */
  public function __construct(
    protected ModuleHandlerInterface $inner,
    protected CacheBackendInterface $cacheBackend,
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
   * {@inheritdoc}
   */
  public function alter($type, &$data, &$context1 = NULL, &$context2 = NULL): void {
    $this->inner->alter($type, $data, $context1, $context2);

    $types = is_array($type) ? $type : [$type];
    foreach ($types as $alter) {
      foreach ($this->generateAlterInvokers($alter) as $alterInvoker) {
        $alterInvoker($data, $context1, $context2);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterDeprecated($description, $type, &$data, &$context1 = NULL, &$context2 = NULL): void {
    $this->inner->alterDeprecated($description, $type, $data, $context1, $context2);

    $types = is_array($type) ? $type : [$type];
    foreach ($types as $alter) {
      foreach ($this->generateAlterInvokers($alter) as $alterInvoker) {
        $alterInvoker($data, $context1, $context2);
      }
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
    foreach ($this->discovery->getHooks($hook) as [
      $serviceId,
      $moduleName,
      $methodName,
      $priority,
    ]) {
      $service = $this->container->get($serviceId);
      $hooks[] = [
        \Closure::fromCallable([$service, $methodName]),
        $moduleName,
        $priority,
      ];
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
    foreach ($this->discovery->getHookReplacements($hook) as [
      $serviceId,
      $moduleName,
      $methodName,
      $originalInvoker,
    ]) {
      $service = $this->container->get($serviceId);
      $hookInvoker = \Closure::fromCallable([$service, $methodName]);
      $this->hookReplacements[$hook][$moduleName] = new HuxReplacementHook(
        $hookInvoker,
        $originalInvoker,
      );
    }

    return $this->hookReplacements[$hook];
  }

  /**
   * Generates invokers for an alter.
   *
   * @param string $alter
   *   An alter.
   *
   * @return \Generator<array{callable, string}>
   *   A generator with hook callbacks and other metadata.
   */
  private function generateAlterInvokers(string $alter) {
    if (isset($this->alters[$alter])) {
      yield from $this->alters[$alter];
      return;
    }

    $this->alters[$alter] = [];
    foreach ($this->discovery->getAlters($alter) as [$serviceId, $methodName]) {
      $service = $this->container->get($serviceId);
      $this->alters[$alter][] = \Closure::fromCallable([$service, $methodName]);
    }

    yield from $this->alters[$alter];
  }

  /**
   * Initialises and caches, or unserializes discovery.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, array{string}> $implementations
   *   An array of module names keyed by service ID.
   * @param array{optimize: bool} $huxParameters
   *   Parameters from the container. Defaults are provided in hux.services.yml
   *   Sites can override the default value in their own services.yml files.
   */
  public function discovery(ContainerInterface $container, array $implementations, array $huxParameters): void {
    ['optimize' => $optimize] = $huxParameters;
    $optimize ?? throw new \Exception('Missing Hux parameters. App is misconfigured.');
    if ($optimize && ($cache = $this->cacheBackend->get('hux.discovery'))) {
      $this->discovery = $cache->data;
    }
    else {
      $this->discovery = new HuxDiscovery($implementations);
      $this->discovery->discovery($container);
      if ($optimize) {
        $this->cacheBackend->set('hux.discovery', $this->discovery);
      }
    }
  }

}
