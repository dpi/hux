<?php

declare(strict_types=1);

namespace Drupal\Tests\hux\Kernel;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\hux_test\HuxTestCallTracker;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests hook replacements.
 *
 * @group hux
 * @coversDefaultClass \Drupal\hux\HuxModuleHandler
 */
final class HuxReplacementTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hux',
    'hux_test',
  ];

  /**
   * Tests with the replacement.
   *
   * @covers ::invoke
   */
  public function testInvokeBaseline(): void {
    $result = $this->moduleHandler()->invoke('hux_test', 'foo', ['bar']);
    $this->assertEquals(['hux_test_foo'], HuxTestCallTracker::$calls);
    $this->assertEquals('hux_test_foo return', $result);
  }

  /**
   * Tests with the replacement.
   *
   * @covers ::invoke
   */
  public function testInvokeReplacement(): void {
    $this->moduleInstaller()->install(['hux_replacement_test'], TRUE);
    $result = $this->moduleHandler()->invoke('hux_test', 'foo', ['bar']);
    $this->assertEquals([
      [
        'Drupal\hux_replacement_test\HuxReplacementTestHooks',
        'myReplacement',
        'bar',
      ],
    ], HuxTestCallTracker::$calls);
    $this->assertEquals('myReplacement return', $result);
  }

  /**
   * Tests with the replacement and original invoker.
   *
   * @covers ::invoke
   * @see \hux_test_foo2()
   */
  public function testInvokeReplacementWithOriginalInvoker(): void {
    $this->moduleInstaller()->install(['hux_replacement_test'], TRUE);
    $result = $this->moduleHandler()->invoke('hux_test', 'foo2', ['bar']);
    $this->assertEquals([
      [
        'Drupal\hux_replacement_test\HuxReplacementTestHooks',
        'myReplacementWithOriginal',
        'bar',
      ],
      'hux_test_foo2',
    ], HuxTestCallTracker::$calls);
    $this->assertEquals('myReplacementWithOriginal return', $result);
  }

  /**
   * Tests with the replacement.
   *
   * @covers ::invokeAllWith
   */
  public function testInvokeAllWithBaseline(): void {
    $result = [];
    $this->moduleHandler()->invokeAllWith(
      'foo',
      function (callable $hookInvoker, string $module) use (&$result) {
        $result[] = $hookInvoker('bar');
      }
    );
    $this->assertEquals([
      'hux_test_foo',
    ], HuxTestCallTracker::$calls);
    $this->assertEquals([
      'hux_test_foo return',
    ], $result);
  }

  /**
   * Tests with the replacement.
   *
   * @covers ::invokeAllWith
   */
  public function testInvokeAllWithReplacement(): void {
    $this->moduleInstaller()->install(['hux_replacement_test'], TRUE);
    $result = [];
    $this->moduleHandler()->invokeAllWith(
      'foo',
      function (callable $hookInvoker, string $module) use (&$result) {
        $result[] = $hookInvoker('bar');
      }
    );
    $this->assertEquals([
      'hux_replacement_test_foo',
      [
        'Drupal\hux_replacement_test\HuxReplacementTestHooks',
        'myReplacement',
        'bar',
      ],
    ], HuxTestCallTracker::$calls);
    $this->assertEquals([
      'hux_replacement_test_foo return',
      'myReplacement return',
    ], $result);
  }

  /**
   * Tests with the replacement.
   *
   * @covers ::invokeAllWith
   */
  public function testInvokeAllWithReplacementWithOriginalInvoker(): void {
    $this->moduleInstaller()->install(['hux_replacement_test'], TRUE);
    $result = [];
    $this->moduleHandler()->invokeAllWith(
      'foo2',
      function (callable $hookInvoker, string $module) use (&$result) {
        $result[] = $hookInvoker('bar');
      }
    );
    $this->assertEquals([
      'hux_replacement_test_foo2',
      [
        'Drupal\hux_replacement_test\HuxReplacementTestHooks',
        'myReplacementWithOriginal',
        'bar',
      ],
      'hux_test_foo2',
    ], HuxTestCallTracker::$calls);
    $this->assertEquals([
      'hux_replacement_test_foo2 return',
      'myReplacementWithOriginal return',
    ], $result);
  }

  /**
   * Tests without the replacement.
   *
   * @covers ::invokeAll
   */
  public function testInvokeAllBaseline(): void {
    $result = $this->moduleHandler()->invokeAll('foo', ['bar']);
    $this->assertEquals([
      'hux_test_foo',
    ], HuxTestCallTracker::$calls);
    $this->assertEquals([
      'hux_test_foo return',
    ], $result);
  }

  /**
   * Tests with the replacement.
   *
   * @covers ::invokeAll
   */
  public function testInvokeAllReplacement(): void {
    $this->moduleInstaller()->install(['hux_replacement_test'], TRUE);
    $result = $this->moduleHandler()->invokeAll('foo', ['bar']);
    $this->assertEquals([
      'hux_replacement_test_foo',
      [
        'Drupal\hux_replacement_test\HuxReplacementTestHooks',
        'myReplacement',
        'bar',
      ],
    ], HuxTestCallTracker::$calls);
    $this->assertEquals([
      'hux_replacement_test_foo return',
      'myReplacement return',
    ], $result);
  }

  /**
   * Tests with the replacement.
   *
   * @covers ::invokeDeprecated
   * @group legacy
   */
  public function testInvokeDeprecatedBaseline(): void {
    $result = $this->moduleHandler()->invokeDeprecated('Deprecation message!', 'hux_test', 'foo', ['bar']);
    $this->assertEquals(['hux_test_foo'], HuxTestCallTracker::$calls);
    $this->assertEquals('hux_test_foo return', $result);
  }

  /**
   * Tests with the replacement.
   *
   * @covers ::invokeDeprecated
   * @group legacy
   */
  public function testInvokeDeprecatedReplacement(): void {
    $this->moduleInstaller()->install(['hux_replacement_test'], TRUE);
    $result = $this->moduleHandler()->invokeDeprecated('Deprecation message!', 'hux_test', 'foo', ['bar']);
    $this->assertEquals([
      [
        'Drupal\hux_replacement_test\HuxReplacementTestHooks',
        'myReplacement',
        'bar',
      ],
    ], HuxTestCallTracker::$calls);
    $this->assertEquals('myReplacement return', $result);
  }

  /**
   * Tests without the replacement.
   *
   * @covers ::invokeAllDeprecated
   * @group legacy
   */
  public function testInvokeAllDeprecatedBaseline(): void {
    $result = $this->moduleHandler()->invokeAllDeprecated('Deprecation message!', 'foo', ['bar']);
    $this->assertEquals([
      'hux_test_foo',
    ], HuxTestCallTracker::$calls);
    $this->assertEquals([
      'hux_test_foo return',
    ], $result);
  }

  /**
   * Tests with the replacement.
   *
   * @covers ::invokeAllDeprecated
   * @group legacy
   */
  public function testInvokeAllDeprecatedReplacement(): void {
    $this->moduleInstaller()->install(['hux_replacement_test'], TRUE);
    $result = $this->moduleHandler()->invokeAllDeprecated('Deprecation message!', 'foo', ['bar']);
    $this->assertEquals([
      'hux_replacement_test_foo',
      [
        'Drupal\hux_replacement_test\HuxReplacementTestHooks',
        'myReplacement',
        'bar',
      ],
    ], HuxTestCallTracker::$calls);
    $this->assertEquals([
      'hux_replacement_test_foo return',
      'myReplacement return',
    ], $result);
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
