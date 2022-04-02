<?php

declare(strict_types=1);

namespace Drupal\Tests\hux\Unit;

use Drupal\hux\HuxCompilerPass;
use Drupal\hux_auto_test\Hooks\HuxAutoContainerInjection;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests compiler pass.
 *
 * @group hux
 * @coversDefaultClass \Drupal\hux\HuxCompilerPass
 */
final class HuxCompilerPassUnitTest extends UnitTestCase {

  /**
   * Tests automatic discovery of classes in Hooks directories.
   */
  public function testAutoClassDiscovery(): void {
    $parameterBag = $this->createMock(ParameterBagInterface::class);
    $parameterBag->expects($this->any())
      ->method('get')
      ->with('container.namespaces')
      ->willReturn([
        'Drupal\hux_auto_test' => realpath(__DIR__ . '/../../modules/hux_auto_test/src'),
      ]);
    $containerBuilder = new ContainerBuilder($parameterBag);
    $huxModuleHandlerDefinition = $this->createMock(Definition::class);
    $huxModuleHandlerDefinition->expects($this->any())
      ->method('isPublic')
      ->willReturn(TRUE);
    $containerBuilder->setDefinition('hux.module_handler', $huxModuleHandlerDefinition);

    $huxModuleHandlerDefinition->expects($this->exactly(4))
      ->method('addMethodCall')
      ->withConsecutive(
        [
          'addHookImplementation',
          [
            'hux.auto.drupal_hux_auto_test__hooks__sub__hux_auto_sub_hooks',
            'hux_auto_test',
          ],
        ],
        [
          'addHookImplementation',
          [
            'hux.auto.drupal_hux_auto_test__hooks__hux_auto_single',
            'hux_auto_test',
          ],
        ],
        [
          'addHookImplementation',
          [
            'hux.auto.drupal_hux_auto_test__hooks__hux_auto_multiple',
            'hux_auto_test',
          ],
        ],
        [
          'addHookImplementation',
          [
            'hux.auto.drupal_hux_auto_test__hooks__hux_auto_container_injection',
            'hux_auto_test',
          ],
        ],
      );

    (new HuxCompilerPass())->process($containerBuilder);

    $definition = $containerBuilder->getDefinition('hux.auto.drupal_hux_auto_test__hooks__hux_auto_container_injection');
    $this->assertEquals([HuxAutoContainerInjection::class, 'create'], $definition->getFactory());
    /** @var \Symfony\Component\DependencyInjection\Reference $arg1 */
    $arg1 = $definition->getArgument(0);
    $this->assertInstanceOf(Reference::class, $arg1);
    $this->assertEquals('service_container', (string) $arg1);

    $definition = $containerBuilder->getDefinition('hux.auto.drupal_hux_auto_test__hooks__hux_auto_single');
    $this->assertNull($definition->getFactory());
    $this->assertCount(0, $definition->getArguments());
  }

}
