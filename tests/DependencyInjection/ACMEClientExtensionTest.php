<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\ACMEClientBundle\DependencyInjection\ACMEClientExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(ACMEClientExtension::class)]
final class ACMEClientExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private ACMEClientExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new ACMEClientExtension();
    }

    public function testLoadMultipleConfigs(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $configs = [
            ['config1' => 'value1'],
            ['config2' => 'value2'],
        ];

        $this->extension->load($configs, $container);

        // 验证多个配置都能正常加载
        $this->assertNotEmpty($container->getDefinitions());
    }
}
