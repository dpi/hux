<?php

declare(strict_types=1);

namespace Drupal\Tests\hux\Kernel;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\hux_test\HuxTestCallTracker;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests hooks.
 *
 * @group hux
 * @coversDefaultClass \Drupal\hux\HuxModuleHandler
 */
final class HuxTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hux',
    'hux_test',
  ];

  /**
   * Tests hook is invoked.
   *
   * @covers ::invokeAll
   * @see \Drupal\hux_test\HuxTestHooks::testHook
   */
  public function testInvokeAllInvoked(): void {
    $this->moduleHandler()->invokeAll('test_hook', ['bar']);
    $this->assertEquals([
      [
        'Drupal\hux_test\HuxTestHooks',
        'testHook',
        'bar',
      ],
    ], HuxTestCallTracker::$calls);
  }

  /**
   * Tests hook return value.
   *
   * @covers ::invokeAll
   * @see \Drupal\hux_test\HuxTestHooks::testHookReturns
   */
  public function testInvokeAllReturn(): void {
    $result = $this->moduleHandler()->invokeAll('test_hook_returns');
    $this->assertEquals([
      [
        'Drupal\hux_test\HuxTestHooks',
        'testHookReturns',
      ],
    ], HuxTestCallTracker::$calls);
    $this->assertEquals([
      'testHookReturns return',
    ], $result);
  }

  /**
   * @covers \Drupal\hux\HuxDiscovery::discovery
   * @see \Drupal\hux_test\HuxTestHooks::testHookMultiListener
   */
  public function testMultiListener(): void {
    $this->moduleHandler()->invokeAll('test_hook_multi_listener', ['bar1']);
    $this->moduleHandler()->invokeAll('test_hook_multi_listener2', ['bar2']);
    $this->assertEquals([
      [
        'Drupal\hux_test\HuxTestHooks',
        'testHookMultiListener',
        'bar1',
      ],
      [
        'Drupal\hux_test\HuxTestHooks',
        'testHookMultiListener',
        'bar2',
      ],
    ], HuxTestCallTracker::$calls);
    $this->moduleHandler()->invokeAll('test_hook', ['bar']);
  }

  /**
   * The module installer.
   */
  private function moduleInstaller(): ModuleInstallerInterface {
    return \Drupal::service('module_installer');
  }

  /**
   * The module handler.
   */
  private function moduleHandler(): ModuleHandlerInterface {
    return \Drupal::service('module_handler');
  }

}
