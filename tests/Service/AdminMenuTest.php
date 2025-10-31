<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Service\AdminMenu;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private AdminMenu $adminMenu;

    private LinkGeneratorInterface&MockObject $linkGenerator;

    protected function onSetUp(): void
    {
        $this->linkGenerator = $this->createMock(LinkGeneratorInterface::class);
        self::getContainer()->set(LinkGeneratorInterface::class, $this->linkGenerator);
        $this->adminMenu = self::getService(AdminMenu::class);
    }

    protected function getMenuProvider(): object
    {
        return $this->adminMenu;
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(AdminMenu::class, $this->adminMenu);
    }

    public function testInvokeCreatesSSLCertificateMenu(): void
    {
        $mainItem = $this->createMock(ItemInterface::class);
        $sslMenuItem = $this->createMock(ItemInterface::class);

        // 配置 LinkGenerator 的所有预期调用
        $this->linkGenerator->expects($this->exactly(8))
            ->method('getCurdListPage')
            ->willReturnCallback(function (string $entityClass): string {
                return '/admin/' . strtolower(basename(str_replace('\\', '/', $entityClass)));
            })
        ;

        // 第一次获取 SSL 证书菜单时返回 null，第二次返回创建的菜单项
        $mainItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('SSL 证书')
            ->willReturnOnConsecutiveCalls(null, $sslMenuItem)
        ;

        // 创建 SSL 证书主菜单
        $mainItem->expects($this->once())
            ->method('addChild')
            ->with('SSL 证书')
            ->willReturn($sslMenuItem)
        ;

        // 配置 SSL 子菜单项（包括日志管理）
        $this->configureSSLMenuItem($sslMenuItem);

        $this->adminMenu->__invoke($mainItem);
    }

    public function testInvokeWithExistingSSLCertificateMenu(): void
    {
        $mainItem = $this->createMock(ItemInterface::class);
        $sslMenuItem = $this->createMock(ItemInterface::class);

        // 配置 LinkGenerator 的所有预期调用
        $this->linkGenerator->expects($this->exactly(8))
            ->method('getCurdListPage')
            ->willReturnCallback(function (string $entityClass): string {
                return '/admin/' . strtolower(basename(str_replace('\\', '/', $entityClass)));
            })
        ;

        // SSL 证书菜单已存在
        $mainItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('SSL 证书')
            ->willReturn($sslMenuItem)
        ;

        // 不应该再次创建主菜单
        $mainItem->expects($this->never())
            ->method('addChild')
        ;

        // 配置 SSL 子菜单项（即使 SSL 菜单已存在，仍然需要添加子菜单项）
        $this->configureSSLMenuItem($sslMenuItem);

        $this->adminMenu->__invoke($mainItem);
    }

    public function testInvokeWithNullSSLMenuItem(): void
    {
        $mainItem = $this->createMock(ItemInterface::class);
        $sslMenuItem = $this->createMock(ItemInterface::class);

        // 配置 LinkGenerator 的所有预期调用
        $this->linkGenerator->expects($this->exactly(8))
            ->method('getCurdListPage')
            ->willReturnCallback(function (string $entityClass): string {
                return '/admin/' . strtolower(basename(str_replace('\\', '/', $entityClass)));
            })
        ;

        // 第一次 getChild 返回 null，第二次返回创建的菜单项
        $mainItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('SSL 证书')
            ->willReturnOnConsecutiveCalls(null, $sslMenuItem)
        ;

        $mainItem->expects($this->once())
            ->method('addChild')
            ->with('SSL 证书')
            ->willReturn($sslMenuItem)
        ;

        // 配置 SSL 子菜单项
        $this->configureSSLMenuItem($sslMenuItem);

        $this->adminMenu->__invoke($mainItem);
    }

    public function testMenuItemsHaveCorrectIcons(): void
    {
        $mainItem = $this->createMock(ItemInterface::class);
        $sslMenuItem = $this->createMock(ItemInterface::class);

        // 配置 LinkGenerator
        $this->linkGenerator->expects($this->exactly(8))
            ->method('getCurdListPage')
            ->willReturnCallback(function ($entityClass) {
                return '/admin/' . strtolower(basename(str_replace('\\', '/', $entityClass)));
            })
        ;

        // 配置主菜单
        $mainItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('SSL 证书')
            ->willReturnOnConsecutiveCalls(null, $sslMenuItem)
        ;

        $mainItem->expects($this->once())
            ->method('addChild')
            ->with('SSL 证书')
            ->willReturn($sslMenuItem)
        ;

        // 验证每个菜单项都设置了正确的图标
        $expectedIcons = [
            'ACME 账户' => 'fas fa-user-cog',
            'SSL 证书' => 'fas fa-certificate',
            '证书订单' => 'fas fa-shopping-cart',
            '域名授权' => 'fas fa-shield-alt',
            '验证质询' => 'fas fa-tasks',
            '域名标识' => 'fas fa-tag',
        ];

        $logMenuItem = $this->createMock(ItemInterface::class);

        // 配置 SSL 菜单项创建和属性验证
        $sslMenuItem->expects($this->exactly(7)) // 6个SSL菜单项 + 1个日志管理菜单
            ->method('addChild')
            ->willReturnCallback(function ($name) use ($expectedIcons, $logMenuItem) {
                $menuItem = $this->createMock(ItemInterface::class);

                if ('日志管理' === $name) {
                    return $logMenuItem;
                }

                // 验证菜单项设置了 URI
                $menuItem->expects($this->once())
                    ->method('setUri')
                    ->with(self::isString())
                    ->willReturn($menuItem)
                ;

                // 验证菜单项设置了正确的图标
                $menuItem->expects($this->exactly(2))
                    ->method('setAttribute')
                    ->willReturnCallback(function ($key, $value) use ($menuItem, $expectedIcons, $name) {
                        if ('icon' === $key && isset($expectedIcons[$name])) {
                            $this->assertEquals($expectedIcons[$name], $value);
                        } elseif ('description' === $key) {
                            $this->assertIsString($value);
                        }

                        return $menuItem;
                    })
                ;

                return $menuItem;
            })
        ;

        // 配置日志管理菜单
        $sslMenuItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('日志管理')
            ->willReturnOnConsecutiveCalls(null, $logMenuItem)
        ;

        // 配置日志子菜单
        $logMenuItem->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnCallback(function ($name) {
                $logSubMenuItem = $this->createMock(ItemInterface::class);

                $logSubMenuItem->expects($this->once())
                    ->method('setUri')
                    ->with(self::isString())
                    ->willReturn($logSubMenuItem)
                ;

                $expectedLogIcons = [
                    '操作日志' => 'fas fa-list-alt',
                    '异常日志' => 'fas fa-exclamation-triangle',
                ];

                $logSubMenuItem->expects($this->exactly(2))
                    ->method('setAttribute')
                    ->willReturnCallback(function ($key, $value) use ($logSubMenuItem, $expectedLogIcons, $name) {
                        if ('icon' === $key && isset($expectedLogIcons[$name])) {
                            $this->assertEquals($expectedLogIcons[$name], $value);
                        } elseif ('description' === $key) {
                            $this->assertIsString($value);
                        }

                        return $logSubMenuItem;
                    })
                ;

                return $logSubMenuItem;
            })
        ;

        $this->adminMenu->__invoke($mainItem);
    }

    public function testLinkGeneratorIntegration(): void
    {
        $mainItem = $this->createMock(ItemInterface::class);
        $sslMenuItem = $this->createMock(ItemInterface::class);

        // 验证 LinkGenerator 为每个实体类生成正确的链接
        $expectedEntityClasses = [
            Account::class,
            Certificate::class,
            Order::class,
            Authorization::class,
            Challenge::class,
            Identifier::class,
            AcmeOperationLog::class,
            AcmeExceptionLog::class,
        ];

        $this->linkGenerator->expects($this->exactly(8))
            ->method('getCurdListPage')
            ->willReturnCallback(function ($entityClass) use ($expectedEntityClasses) {
                $this->assertContains($entityClass, $expectedEntityClasses);

                return '/admin/crud/' . $entityClass;
            })
        ;

        // 简化的菜单配置
        $mainItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('SSL 证书')
            ->willReturnOnConsecutiveCalls(null, $sslMenuItem)
        ;

        $mainItem->expects($this->once())
            ->method('addChild')
            ->with('SSL 证书')
            ->willReturn($sslMenuItem)
        ;

        $this->configureSSLMenuItem($sslMenuItem);

        $this->adminMenu->__invoke($mainItem);
    }

    private function configureSSLMenuItem(ItemInterface $sslMenuItem): void
    {
        $logMenuItem = $this->createMock(ItemInterface::class);

        // 配置所有菜单项创建（7个：6个SSL菜单项 + 1个日志管理菜单）
        /** @var ItemInterface&MockObject $sslMenuItem */
        $sslMenuItem->expects($this->exactly(7))
            ->method('addChild')
            ->willReturnCallback(function ($name) use ($logMenuItem) {
                if ('日志管理' === $name) {
                    return $logMenuItem;
                }

                $menuItem = $this->createMock(ItemInterface::class);
                $menuItem->expects($this->once())
                    ->method('setUri')
                    ->with(self::isString())
                    ->willReturn($menuItem)
                ;
                $menuItem->expects($this->exactly(2))
                    ->method('setAttribute')
                    ->willReturn($menuItem)
                ;

                return $menuItem;
            })
        ;

        // 配置日志管理菜单的getChild调用
        /** @var ItemInterface&MockObject $sslMenuItem */
        $sslMenuItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('日志管理')
            ->willReturnOnConsecutiveCalls(null, $logMenuItem)
        ;

        // 配置日志子菜单
        $logMenuItem->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnCallback(function () {
                $logSubMenuItem = $this->createMock(ItemInterface::class);
                $logSubMenuItem->expects($this->once())
                    ->method('setUri')
                    ->with(self::isString())
                    ->willReturn($logSubMenuItem)
                ;
                $logSubMenuItem->expects($this->exactly(2))
                    ->method('setAttribute')
                    ->willReturn($logSubMenuItem)
                ;

                return $logSubMenuItem;
            })
        ;
    }
}
